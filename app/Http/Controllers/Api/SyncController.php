<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CatatanResource;
use App\Http\Resources\Api\JadwalResource;
use App\Http\Resources\Api\KeuanganResource;
use App\Http\Resources\Api\ReminderItemResource;
use App\Http\Resources\Api\TodolistResource;
use App\Models\Catatan;
use App\Models\Jadwal;
use App\Models\Keuangan;
use App\Models\Reminder;
use App\Models\SyncOperation;
use App\Models\Todolist;
use App\Services\Jadwal\KuliahScheduleService;
use App\Services\Reminder\ReminderPayloadService;
use App\Services\Reminder\TodolistReminderService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SyncController extends Controller
{
    private const MAX_PUSH_OPERATIONS = 50;

    /**
     * @var array<string, class-string<Model>>
     */
    private array $models = [
        'catatan' => Catatan::class,
        'todolist' => Todolist::class,
        'jadwal' => Jadwal::class,
        'keuangan' => Keuangan::class,
        'reminder' => Reminder::class,
    ];

    public function __construct(
        private readonly KuliahScheduleService $kuliahScheduleService,
        private readonly ReminderPayloadService $reminderPayloadService,
        private readonly TodolistReminderService $todolistReminderService,
    ) {}

    public function push(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'operations' => ['required', 'array', 'max:'.self::MAX_PUSH_OPERATIONS],
            'operations.*.operation_id' => ['required', 'uuid'],
            'operations.*.entity_type' => ['required', Rule::in(array_keys($this->models))],
            'operations.*.action' => ['required', Rule::in(['create', 'update', 'delete', 'restore'])],
            'operations.*.client_uuid' => ['nullable', 'uuid'],
            'operations.*.server_id' => ['nullable', 'integer', 'min:1'],
            'operations.*.base_server_version' => ['nullable', 'integer', 'min:1'],
            'operations.*.payload' => ['nullable', 'array'],
        ])->validate();

        $results = [];
        foreach ($validated['operations'] as $operation) {
            $results[] = $this->processOperation($request->user()->id, $operation);
        }

        return response()->json([
            'data' => [
                'operations' => $results,
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }

    public function pull(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $cursor = $this->parseCursor($request->query('cursor'));
        $nextCursor = $cursor->copy();
        $entities = [];

        foreach (array_keys($this->models) as $entityType) {
            $rows = $this->queryChangedRows($entityType, $userId, $cursor)->get();
            $entities[$entityType] = $this->serializeCollection($entityType, $rows);

            foreach ($rows as $row) {
                $updatedAt = $row->updated_at ? Carbon::parse($row->updated_at) : null;
                if ($updatedAt && $updatedAt->greaterThan($nextCursor)) {
                    $nextCursor = $updatedAt->copy();
                }
            }
        }

        return response()->json([
            'data' => [
                'cursor' => $nextCursor->toIso8601String(),
                'entities' => $entities,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function processOperation(int $userId, array $operation): array
    {
        $existing = SyncOperation::query()
            ->where('user_id', $userId)
            ->where('operation_uuid', $operation['operation_id'])
            ->first();

        if ($existing) {
            return $existing->response_json ?: [
                'operation_id' => $operation['operation_id'],
                'status' => $existing->status,
                'error' => $existing->error_json,
            ];
        }

        try {
            return DB::transaction(function () use ($userId, $operation): array {
                $result = $this->applyOperation($userId, $operation);

                SyncOperation::query()->create([
                    'user_id' => $userId,
                    'operation_uuid' => $operation['operation_id'],
                    'entity_type' => $operation['entity_type'],
                    'entity_id' => $result['server_id'] ?? null,
                    'action' => $operation['action'],
                    'status' => $result['status'],
                    'response_json' => $result,
                    'error_json' => $result['error'] ?? null,
                ]);

                return $result;
            });
        } catch (\Throwable $exception) {
            report($exception);

            $result = [
                'operation_id' => $operation['operation_id'],
                'entity_type' => $operation['entity_type'],
                'status' => 'failed',
                'error' => [
                    'message' => 'Operasi sync gagal diproses.',
                ],
            ];

            SyncOperation::query()->create([
                'user_id' => $userId,
                'operation_uuid' => $operation['operation_id'],
                'entity_type' => $operation['entity_type'],
                'action' => $operation['action'],
                'status' => 'failed',
                'response_json' => $result,
                'error_json' => $result['error'],
            ]);

            return $result;
        }
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function applyOperation(int $userId, array $operation): array
    {
        $entityType = (string) $operation['entity_type'];
        $action = (string) $operation['action'];
        $clientUuid = $operation['client_uuid'] ?? null;
        $payload = is_array($operation['payload'] ?? null) ? $operation['payload'] : [];

        if ($action === 'create') {
            if (! is_string($clientUuid) || trim($clientUuid) === '') {
                return $this->failedResult($operation, 'client_uuid wajib diisi untuk create.');
            }

            $existing = $this->findByClientUuid($entityType, $userId, $clientUuid);
            if ($existing) {
                return $this->successResult($operation, $existing);
            }

            $record = $this->createRecord($entityType, $userId, $clientUuid, $payload);

            return $this->successResult($operation, $record);
        }

        $record = $this->findTargetRecord($entityType, $userId, $operation);
        if (! $record) {
            return $this->failedResult($operation, 'Record target tidak ditemukan.', 'not_found');
        }

        $baseVersion = $operation['base_server_version'] ?? null;
        if ($baseVersion !== null && (int) $baseVersion !== (int) ($record->server_version ?? 1)) {
            return [
                'operation_id' => $operation['operation_id'],
                'entity_type' => $entityType,
                'status' => 'conflict',
                'server_id' => (int) $record->id,
                'client_uuid' => (string) $record->sync_uuid,
                'server_version' => (int) ($record->server_version ?? 1),
                'server_record' => $this->serializeRecord($entityType, $record),
                'error' => [
                    'message' => 'Record sudah berubah di server.',
                ],
            ];
        }

        if ($action === 'delete') {
            if (method_exists($record, 'trashed') && ! $record->trashed()) {
                $record->delete();
                $record = $this->findTargetRecord($entityType, $userId, [
                    'server_id' => $record->id,
                ]) ?? $record;
            }

            return $this->successResult($operation, $record);
        }

        if ($action === 'restore') {
            if (method_exists($record, 'restore') && method_exists($record, 'trashed') && $record->trashed()) {
                $record->restore();
            }

            return $this->successResult($operation, $record->fresh() ?? $record);
        }

        $updated = $this->updateRecord($entityType, $record, $payload);

        return $this->successResult($operation, $updated);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createRecord(string $entityType, int $userId, string $clientUuid, array $payload): Model
    {
        return match ($entityType) {
            'catatan' => Catatan::query()->create([
                'user_id' => $userId,
                'sync_uuid' => $clientUuid,
                'judul' => trim((string) $this->validatePayload($entityType, $payload)['judul']),
                'isi' => (string) $payload['isi'],
                'preview_isi' => Catatan::makePreviewIsi((string) $payload['isi']),
                'show_preview' => (bool) ($payload['show_preview'] ?? false),
                'tanggal' => $payload['tanggal'],
                'status_sampah' => (bool) ($payload['status_sampah'] ?? false),
            ]),
            'keuangan' => Keuangan::query()->create([
                'user_id' => $userId,
                'sync_uuid' => $clientUuid,
                ...$this->validatedKeuanganPayload($payload),
            ]),
            'todolist' => $this->saveTodolist(null, $userId, $clientUuid, $payload),
            'jadwal' => $this->saveJadwal(null, $userId, $clientUuid, $payload),
            'reminder' => $this->saveReminder(null, $userId, $clientUuid, $payload),
            default => abort(422, 'Entity sync tidak valid.'),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function updateRecord(string $entityType, Model $record, array $payload): Model
    {
        return match ($entityType) {
            'catatan' => $this->updateCatatan($record, $payload),
            'keuangan' => $this->updateKeuangan($record, $payload),
            'todolist' => $this->saveTodolist($record, (int) $record->user_id, (string) $record->sync_uuid, $payload),
            'jadwal' => $this->saveJadwal($record, (int) $record->user_id, (string) $record->sync_uuid, $payload),
            'reminder' => $this->saveReminder($record, (int) $record->user_id, (string) $record->sync_uuid, $payload),
            default => abort(422, 'Entity sync tidak valid.'),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function updateCatatan(Model $record, array $payload): Model
    {
        $validated = $this->validatePayload('catatan', $payload);
        $record->update([
            'judul' => trim((string) $validated['judul']),
            'isi' => (string) $validated['isi'],
            'preview_isi' => Catatan::makePreviewIsi((string) $validated['isi']),
            'show_preview' => (bool) ($validated['show_preview'] ?? false),
            'tanggal' => $validated['tanggal'],
            'status_sampah' => (bool) ($validated['status_sampah'] ?? false),
        ]);

        return $record->fresh() ?? $record;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function updateKeuangan(Model $record, array $payload): Model
    {
        $record->update($this->validatedKeuanganPayload($payload));

        return $record->fresh() ?? $record;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function saveTodolist(?Model $record, int $userId, string $clientUuid, array $payload): Todolist
    {
        $validated = $this->validatePayload('todolist', $payload);
        $todolist = $record instanceof Todolist
            ? $record
            : new Todolist(['user_id' => $userId, 'sync_uuid' => $clientUuid]);

        $todolist->fill([
            'user_id' => $userId,
            'sync_uuid' => $clientUuid,
            'nama_item' => trim((string) $validated['nama_item']),
            'status' => (bool) ($validated['status'] ?? false),
        ]);
        $todolist->save();

        $this->todolistReminderService->sync(
            $todolist,
            $userId,
            (bool) ($validated['reminder_enabled'] ?? false),
            $validated['reminder_date'] ?? null,
            $validated['reminder_time'] ?? null
        );

        return $todolist->fresh(['reminders']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function saveJadwal(?Model $record, int $userId, string $clientUuid, array $payload): Jadwal
    {
        $validated = $this->validatePayload('jadwal', $payload);
        $startAt = Carbon::parse((string) $validated['start_at']);
        $endAt = Carbon::parse((string) $validated['end_at']);
        $jadwal = $record instanceof Jadwal
            ? $record
            : new Jadwal(['user_id' => $userId, 'sync_uuid' => $clientUuid]);

        $jadwal->fill([
            'user_id' => $userId,
            'sync_uuid' => $clientUuid,
            'matkul_id_list' => null,
            'jenis' => $this->typeToJenis((string) $validated['type']),
            'tanggal_mulai' => $startAt->toDateString(),
            'tanggal_selesai' => $endAt->toDateString(),
            'semester' => null,
            'catatan_tambahan' => $this->nullableText($validated['notes'] ?? null),
            'title' => trim((string) $validated['title']),
            'location' => $this->nullableText($validated['location'] ?? null),
            'start_time' => $startAt->format('H:i:s'),
            'end_time' => $endAt->format('H:i:s'),
            'is_completed' => (bool) ($validated['completed'] ?? false),
        ]);
        $jadwal->save();
        $this->kuliahScheduleService->appendMatkulDetailsForUser([$jadwal], $userId);

        return $jadwal->fresh() ?? $jadwal;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function saveReminder(?Model $record, int $userId, string $clientUuid, array $payload): Reminder
    {
        $validated = $this->validatePayload('reminder', $payload);
        $input = $payload;
        $built = $this->reminderPayloadService->build($userId, $validated, $input, $record instanceof Reminder ? $record : null);
        $built['sync_uuid'] = $clientUuid;
        if ($record instanceof Reminder) {
            $record->update($built);
            $reminder = $record;
        } else {
            $reminder = Reminder::query()->create($built);
        }

        return $reminder->fresh(['todolist', 'tugas', 'jadwal', 'kegiatan']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validatedKeuanganPayload(array $payload): array
    {
        $validated = $this->validatePayload('keuangan', $payload);

        return [
            'jenis' => $validated['jenis'],
            'kategori' => trim((string) $validated['kategori']),
            'deskripsi' => $this->nullableText($validated['deskripsi'] ?? null),
            'nominal' => $validated['nominal'],
            'tanggal' => $validated['tanggal'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validatePayload(string $entityType, array $payload): array
    {
        $rules = match ($entityType) {
            'catatan' => [
                'judul' => ['required', 'string', 'max:180'],
                'isi' => ['required', 'string'],
                'tanggal' => ['required', 'date'],
                'show_preview' => ['nullable', 'boolean'],
                'status_sampah' => ['nullable', 'boolean'],
            ],
            'keuangan' => [
                'jenis' => ['required', 'in:pemasukan,pengeluaran'],
                'kategori' => ['required', 'string', 'max:255'],
                'deskripsi' => ['nullable', 'string'],
                'nominal' => ['required', 'numeric', 'min:0'],
                'tanggal' => ['required', 'date'],
            ],
            'todolist' => [
                'nama_item' => ['required', 'string', 'max:150'],
                'status' => ['nullable', 'boolean'],
                'reminder_enabled' => ['nullable', 'boolean'],
                'reminder_date' => ['nullable', 'date'],
                'reminder_time' => ['nullable', 'date_format:H:i'],
            ],
            'jadwal' => [
                'title' => ['required', 'string', 'max:180'],
                'type' => ['required', 'string', 'in:kuliah,tugas,ujian,rapat,personal'],
                'start_at' => ['required', 'date'],
                'end_at' => ['required', 'date', 'after:start_at'],
                'location' => ['nullable', 'string', 'max:180'],
                'notes' => ['nullable', 'string'],
                'completed' => ['nullable', 'boolean'],
            ],
            'reminder' => [
                'reminder_target' => ['required', Rule::in(['todolist', 'tugas', 'jadwal', 'kegiatan'])],
                'todolist_id' => ['nullable', 'integer', 'exists:todolists,id'],
                'tugas_id' => ['nullable', 'integer', 'exists:tugas,id'],
                'jadwal_id' => ['nullable', 'integer', 'exists:jadwals,id'],
                'kegiatan_id' => ['nullable', 'integer', 'exists:kegiatans,id'],
                'waktu_reminder' => ['required', 'date'],
                'aktif' => ['nullable', 'boolean'],
            ],
            default => abort(422, 'Entity sync tidak valid.'),
        };

        return Validator::make($payload, $rules)->validate();
    }

    private function findTargetRecord(string $entityType, int $userId, array $operation): ?Model
    {
        $query = $this->baseEntityQuery($entityType, $userId);

        if (! empty($operation['server_id'])) {
            return (clone $query)->where('id', $operation['server_id'])->first();
        }

        if (! empty($operation['client_uuid'])) {
            return (clone $query)->where('sync_uuid', $operation['client_uuid'])->first();
        }

        return null;
    }

    private function findByClientUuid(string $entityType, int $userId, string $clientUuid): ?Model
    {
        return $this->baseEntityQuery($entityType, $userId)
            ->where('sync_uuid', $clientUuid)
            ->first();
    }

    private function baseEntityQuery(string $entityType, int $userId)
    {
        $class = $this->models[$entityType] ?? null;
        if (! $class) {
            abort(422, 'Entity sync tidak valid.');
        }

        return $class::query()
            ->withTrashed()
            ->where('user_id', $userId)
            ->lockForUpdate();
    }

    private function queryChangedRows(string $entityType, int $userId, Carbon $cursor)
    {
        $class = $this->models[$entityType];

        return $class::query()
            ->withTrashed()
            ->where('user_id', $userId)
            ->where('updated_at', '>', $cursor)
            ->orderBy('updated_at')
            ->orderBy('id');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serializeCollection(string $entityType, iterable $rows): array
    {
        if ($entityType === 'jadwal') {
            $this->kuliahScheduleService->appendMatkulDetailsForUser($rows, request()->user()->id);
        }

        return collect($rows)
            ->map(fn (Model $row) => $this->serializeRecord($entityType, $row))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRecord(string $entityType, Model $record): array
    {
        if ($entityType === 'jadwal' && $record instanceof Jadwal) {
            $this->kuliahScheduleService->appendMatkulDetailsForUser([$record], (int) $record->user_id);
        }

        return match ($entityType) {
            'catatan' => (new CatatanResource($record))->resolve(),
            'todolist' => (new TodolistResource($record->loadMissing('reminders')))->resolve(),
            'jadwal' => (new JadwalResource($record))->resolve(),
            'keuangan' => (new KeuanganResource($record))->resolve(),
            'reminder' => (new ReminderItemResource($record->loadMissing(['todolist', 'tugas', 'jadwal', 'kegiatan'])))->resolve(),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function successResult(array $operation, Model $record): array
    {
        return [
            'operation_id' => $operation['operation_id'],
            'entity_type' => $operation['entity_type'],
            'status' => 'synced',
            'server_id' => (int) $record->id,
            'client_uuid' => (string) $record->sync_uuid,
            'server_version' => (int) ($record->server_version ?? 1),
            'record' => $this->serializeRecord((string) $operation['entity_type'], $record),
        ];
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function failedResult(array $operation, string $message, string $status = 'failed'): array
    {
        return [
            'operation_id' => $operation['operation_id'],
            'entity_type' => $operation['entity_type'],
            'status' => $status,
            'error' => [
                'message' => $message,
            ],
        ];
    }

    private function parseCursor(mixed $rawCursor): Carbon
    {
        $value = trim((string) ($rawCursor ?? ''));
        if ($value === '') {
            return Carbon::createFromTimestamp(0);
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return Carbon::createFromTimestamp(0);
        }
    }

    private function typeToJenis(string $type): string
    {
        return match (strtolower(trim($type))) {
            'kuliah' => 'kuliah',
            'tugas' => 'tugas',
            'ujian' => 'ujian',
            'rapat' => 'rapat',
            default => 'personal',
        };
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
