<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Catatan;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatatanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = $this->resolvePerPage($request->query('per_page'));

        $query = Catatan::query()
            ->where('user_id', $user->id)
            ->where('status_sampah', false)
            ->orderByDesc('tanggal')
            ->orderByDesc('id');

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(fn (Catatan $catatan) => $this->catatanPayload($catatan))->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'trash_count' => Catatan::query()
                    ->where('user_id', $user->id)
                    ->where('status_sampah', true)
                    ->count(),
            ],
            'links' => [
                'next' => $paginator->nextPageUrl(),
                'prev' => $paginator->previousPageUrl(),
            ],
        ]);
    }

    public function trash(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = $this->resolvePerPage($request->query('per_page'));

        $paginator = Catatan::query()
            ->where('user_id', $user->id)
            ->where('status_sampah', true)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(fn (Catatan $catatan) => $this->catatanPayload($catatan))->all(),
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
            'judul' => ['required', 'string', 'max:180'],
            'isi' => ['required', 'string'],
            'tanggal' => ['required', 'date'],
        ]);

        $catatan = Catatan::query()->create([
            'user_id' => $request->user()->id,
            'judul' => trim((string) $validated['judul']),
            'isi' => (string) $validated['isi'],
            'tanggal' => $validated['tanggal'],
            'status_sampah' => false,
        ]);

        return response()->json([
            'message' => 'Catatan berhasil ditambahkan.',
            'data' => $this->catatanPayload($catatan),
        ], 201);
    }

    public function show(Request $request, int $catatan): JsonResponse
    {
        $item = $this->findOwnedCatatanOrFail($request, $catatan);

        return response()->json([
            'data' => $this->catatanPayload($item),
        ]);
    }

    public function update(Request $request, int $catatan): JsonResponse
    {
        $item = $this->findOwnedCatatanOrFail($request, $catatan);

        $validated = $request->validate([
            'judul' => ['required', 'string', 'max:180'],
            'isi' => ['required', 'string'],
            'tanggal' => ['required', 'date'],
        ]);

        $item->update([
            'judul' => trim((string) $validated['judul']),
            'isi' => (string) $validated['isi'],
            'tanggal' => $validated['tanggal'],
        ]);

        return response()->json([
            'message' => 'Catatan berhasil diperbarui.',
            'data' => $this->catatanPayload($item->fresh()),
        ]);
    }

    public function destroy(Request $request, int $catatan): JsonResponse
    {
        $item = $this->findOwnedCatatanOrFail($request, $catatan);

        $item->update(['status_sampah' => true]);

        return response()->json([
            'message' => 'Catatan dipindahkan ke sampah.',
        ]);
    }

    public function restore(Request $request, int $catatan): JsonResponse
    {
        $item = $this->findOwnedCatatanOrFail($request, $catatan);

        $item->update(['status_sampah' => false]);

        return response()->json([
            'message' => 'Catatan berhasil dipulihkan.',
            'data' => $this->catatanPayload($item->fresh()),
        ]);
    }

    public function forceDelete(Request $request, int $catatan): JsonResponse
    {
        $item = $this->findOwnedCatatanOrFail($request, $catatan);

        $item->delete();

        return response()->json([
            'message' => 'Catatan dihapus permanen.',
        ]);
    }

    private function findOwnedCatatanOrFail(Request $request, int $id): Catatan
    {
        $item = Catatan::query()
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $item) {
            throw (new ModelNotFoundException())->setModel(Catatan::class, [$id]);
        }

        return $item;
    }

    private function resolvePerPage(mixed $rawPerPage): int
    {
        $perPage = (int) $rawPerPage;
        if ($perPage <= 0) {
            return 20;
        }

        return min($perPage, 100);
    }

    private function catatanPayload(Catatan $catatan): array
    {
        return [
            'id' => (int) $catatan->id,
            'judul' => (string) ($catatan->judul ?? ''),
            'isi' => (string) ($catatan->isi ?? ''),
            'tanggal' => optional($catatan->tanggal)->toDateString() ?? (string) $catatan->tanggal,
            'status_sampah' => (bool) $catatan->status_sampah,
            'created_at' => optional($catatan->created_at)->toIso8601String(),
            'updated_at' => optional($catatan->updated_at)->toIso8601String(),
        ];
    }
}
