<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCatatanRequest;
use App\Http\Requests\Api\UpdateCatatanRequest;
use App\Http\Resources\Api\CatatanResource;
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

        $paginator = Catatan::query()
            ->selectSummary()
            ->where('user_id', $user->id)
            ->where('status_sampah', false)
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => CatatanResource::collection(collect($paginator->items()))->resolve(),
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
        $paginator = Catatan::query()
            ->selectSummary()
            ->where('user_id', $request->user()->id)
            ->where('status_sampah', true)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($this->resolvePerPage($request->query('per_page')));

        return response()->json([
            'data' => CatatanResource::collection(collect($paginator->items()))->resolve(),
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

    public function store(StoreCatatanRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $catatan = Catatan::query()->create([
            'user_id' => $request->user()->id,
            'judul' => trim((string) $validated['judul']),
            'isi' => (string) $validated['isi'],
            'preview_isi' => Catatan::makePreviewIsi((string) $validated['isi']),
            'tanggal' => $validated['tanggal'],
            'status_sampah' => false,
        ]);

        return response()->json([
            'message' => 'Catatan berhasil ditambahkan.',
            'data' => (new CatatanResource($catatan))->resolve(),
        ], 201);
    }

    public function show(Request $request, int $catatan): JsonResponse
    {
        $item = $this->findOwnedCatatanOrFail($request, $catatan);

        return response()->json([
            'data' => (new CatatanResource($item))->resolve(),
        ]);
    }

    public function update(UpdateCatatanRequest $request, int $catatan): JsonResponse
    {
        $item = $this->findOwnedCatatanOrFail($request, $catatan);
        $validated = $request->validated();

        $item->update([
            'judul' => trim((string) $validated['judul']),
            'isi' => (string) $validated['isi'],
            'preview_isi' => Catatan::makePreviewIsi((string) $validated['isi']),
            'tanggal' => $validated['tanggal'],
        ]);

        return response()->json([
            'message' => 'Catatan berhasil diperbarui.',
            'data' => (new CatatanResource($item->fresh()))->resolve(),
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
            'data' => (new CatatanResource($item->fresh()))->resolve(),
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
}
