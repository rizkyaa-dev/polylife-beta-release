<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreJadwalRequest;
use App\Http\Requests\Api\UpdateJadwalRequest;
use App\Http\Resources\Api\JadwalResource;
use App\Models\Jadwal;
use App\Services\Jadwal\KuliahScheduleService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JadwalController extends Controller
{
    public function __construct(
        private readonly KuliahScheduleService $kuliahScheduleService
    ) {
    }

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
        $items = collect($paginator->items());

        $this->appendMatkulDetails($items, $user->id);

        return response()->json([
            'data' => JadwalResource::collection($items)->resolve(),
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

    public function store(StoreJadwalRequest $request): JsonResponse
    {
        $validated = $request->validated();

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

        $this->appendMatkulDetails([$jadwal], $request->user()->id);

        return response()->json([
            'message' => 'Jadwal berhasil ditambahkan.',
            'data' => (new JadwalResource($jadwal))->resolve(),
        ], 201);
    }

    public function show(Request $request, int $jadwal): JsonResponse
    {
        $item = $this->findOwnedJadwalOrFail($request, $jadwal);
        $this->appendMatkulDetails([$item], $request->user()->id);

        return response()->json([
            'data' => (new JadwalResource($item))->resolve(),
        ]);
    }

    public function update(UpdateJadwalRequest $request, int $jadwal): JsonResponse
    {
        $item = $this->findOwnedJadwalOrFail($request, $jadwal);
        $validated = $request->validated();

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

        $freshItem = $item->fresh();
        $this->appendMatkulDetails([$freshItem], $request->user()->id);

        return response()->json([
            'message' => 'Jadwal berhasil diperbarui.',
            'data' => (new JadwalResource($freshItem))->resolve(),
        ]);
    }

    public function destroy(Request $request, int $jadwal): JsonResponse
    {
        $item = $this->findOwnedJadwalOrFail($request, $jadwal);
        $item->reminders()->delete();
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

    private function appendMatkulDetails(iterable $jadwals, int $userId): void
    {
        $this->kuliahScheduleService->appendMatkulDetailsForUser($jadwals, $userId);
    }
}
