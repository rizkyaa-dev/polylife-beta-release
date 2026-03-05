<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JadwalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = $this->resolvePerPage($request->query('per_page'));

        $query = Jadwal::query()
            ->where('user_id', $user->id)
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('start_time')
            ->orderByDesc('id');

        $from = $request->query('from');
        if (is_string($from) && trim($from) !== '') {
            $query->whereDate('tanggal_selesai', '>=', $from);
        }

        $to = $request->query('to');
        if (is_string($to) && trim($to) !== '') {
            $query->whereDate('tanggal_mulai', '<=', $to);
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn (Jadwal $jadwal) => $this->jadwalPayload($jadwal))
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'links' => [
                'next' => $paginator->nextPageUrl(),
                'prev' => $paginator->previousPageUrl(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'type' => ['required', 'string', 'in:kuliah,tugas,ujian,rapat,personal'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'location' => ['nullable', 'string', 'max:180'],
            'notes' => ['nullable', 'string'],
            'completed' => ['nullable', 'boolean'],
        ]);

        $startAt = Carbon::parse((string) $validated['start_at']);
        $endAt = Carbon::parse((string) $validated['end_at']);

        $jadwal = Jadwal::query()->create([
            'user_id' => $request->user()->id,
            'matkul_id_list' => null,
            'jenis' => $this->typeToJenis((string) $validated['type']),
            'tanggal_mulai' => $startAt->toDateString(),
            'tanggal_selesai' => $endAt->toDateString(),
            'semester' => null,
            'catatan_tambahan' => $this->normalizeNullableText($validated['notes'] ?? null),
            'title' => trim((string) $validated['title']),
            'location' => $this->normalizeNullableText($validated['location'] ?? null),
            'start_time' => $startAt->format('H:i:s'),
            'end_time' => $endAt->format('H:i:s'),
            'is_completed' => (bool) ($validated['completed'] ?? false),
        ]);

        return response()->json([
            'message' => 'Jadwal berhasil ditambahkan.',
            'data' => $this->jadwalPayload($jadwal),
        ], 201);
    }

    public function show(Request $request, int $jadwal): JsonResponse
    {
        $item = $this->findOwnedJadwalOrFail($request, $jadwal);

        return response()->json([
            'data' => $this->jadwalPayload($item),
        ]);
    }

    public function update(Request $request, int $jadwal): JsonResponse
    {
        $item = $this->findOwnedJadwalOrFail($request, $jadwal);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'type' => ['required', 'string', 'in:kuliah,tugas,ujian,rapat,personal'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'location' => ['nullable', 'string', 'max:180'],
            'notes' => ['nullable', 'string'],
            'completed' => ['nullable', 'boolean'],
        ]);

        $startAt = Carbon::parse((string) $validated['start_at']);
        $endAt = Carbon::parse((string) $validated['end_at']);

        $item->update([
            'jenis' => $this->typeToJenis((string) $validated['type']),
            'tanggal_mulai' => $startAt->toDateString(),
            'tanggal_selesai' => $endAt->toDateString(),
            'catatan_tambahan' => $this->normalizeNullableText($validated['notes'] ?? null),
            'title' => trim((string) $validated['title']),
            'location' => $this->normalizeNullableText($validated['location'] ?? null),
            'start_time' => $startAt->format('H:i:s'),
            'end_time' => $endAt->format('H:i:s'),
            'is_completed' => (bool) ($validated['completed'] ?? false),
        ]);

        return response()->json([
            'message' => 'Jadwal berhasil diperbarui.',
            'data' => $this->jadwalPayload($item->fresh()),
        ]);
    }

    public function destroy(Request $request, int $jadwal): JsonResponse
    {
        $item = $this->findOwnedJadwalOrFail($request, $jadwal);
        $item->delete();

        return response()->json([
            'message' => 'Jadwal berhasil dihapus.',
        ]);
    }

    private function findOwnedJadwalOrFail(Request $request, int $id): Jadwal
    {
        $item = Jadwal::query()
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $item) {
            throw (new ModelNotFoundException())->setModel(Jadwal::class, [$id]);
        }

        return $item;
    }

    private function resolvePerPage(mixed $rawPerPage): int
    {
        $perPage = (int) $rawPerPage;
        if ($perPage <= 0) {
            return 30;
        }

        return min($perPage, 200);
    }

    private function normalizeNullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
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

    private function jenisToType(?string $jenis): string
    {
        $value = strtolower(trim((string) $jenis));

        return match ($value) {
            'kuliah' => 'kuliah',
            'tugas', 'deadline' => 'tugas',
            'ujian', 'uts', 'uas' => 'ujian',
            'rapat', 'meeting', 'organisasi' => 'rapat',
            default => 'personal',
        };
    }

    private function defaultTitleFromJenis(?string $jenis): string
    {
        return match ($this->jenisToType($jenis)) {
            'kuliah' => 'Agenda Kuliah',
            'tugas' => 'Agenda Tugas',
            'ujian' => 'Agenda Ujian',
            'rapat' => 'Agenda Rapat',
            default => 'Agenda Personal',
        };
    }

    private function jadwalPayload(Jadwal $jadwal): array
    {
        $startDate = optional($jadwal->tanggal_mulai)->toDateString() ?? (string) $jadwal->tanggal_mulai;
        $endDate = optional($jadwal->tanggal_selesai)->toDateString() ?? (string) $jadwal->tanggal_selesai;

        $startTime = trim((string) ($jadwal->start_time ?? ''));
        if ($startTime === '') {
            $startTime = '08:00:00';
        }

        $endTime = trim((string) ($jadwal->end_time ?? ''));
        if ($endTime === '') {
            $endTime = '09:00:00';
        }

        $startAt = Carbon::parse($startDate . ' ' . $startTime);
        $endAt = Carbon::parse($endDate . ' ' . $endTime);
        if ($endAt->lessThanOrEqualTo($startAt)) {
            $endAt = $startAt->copy()->addHour();
        }

        $title = trim((string) ($jadwal->title ?? ''));
        if ($title === '') {
            $title = $this->defaultTitleFromJenis($jadwal->jenis);
        }

        return [
            'id' => (int) $jadwal->id,
            'title' => $title,
            'type' => $this->jenisToType($jadwal->jenis),
            'start_at' => $startAt->toIso8601String(),
            'end_at' => $endAt->toIso8601String(),
            'location' => (string) ($jadwal->location ?? ''),
            'notes' => (string) ($jadwal->catatan_tambahan ?? ''),
            'completed' => (bool) ($jadwal->is_completed ?? false),
            'source_jenis' => (string) ($jadwal->jenis ?? ''),
            'created_at' => optional($jadwal->created_at)->toIso8601String(),
            'updated_at' => optional($jadwal->updated_at)->toIso8601String(),
        ];
    }
}
