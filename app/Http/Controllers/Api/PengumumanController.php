<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PengumumanResource;
use App\Models\AffiliationBroadcast;
use App\Queries\Broadcast\VisiblePengumumanQuery;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PengumumanController extends Controller
{
    public function __construct(
        private readonly VisiblePengumumanQuery $visiblePengumumanQuery
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->visiblePengumumanQuery->paginateForUser(
            $request->user(),
            trim((string) $request->query('q', '')),
            $this->resolvePerPage($request->query('per_page'))
        );

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn (AffiliationBroadcast $broadcast) => (new PengumumanResource($broadcast, true))->resolve())
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

    public function show(Request $request, int $broadcast): JsonResponse
    {
        $item = $this->visiblePengumumanQuery->findVisibleForUser($request->user(), $broadcast);

        if (! $item) {
            throw (new ModelNotFoundException())->setModel(AffiliationBroadcast::class, [$broadcast]);
        }

        return response()->json([
            'data' => (new PengumumanResource($item))->resolve(),
        ]);
    }

    private function resolvePerPage(mixed $rawPerPage): int
    {
        $perPage = (int) $rawPerPage;
        if ($perPage <= 0) {
            return 12;
        }

        return min($perPage, 50);
    }
}
