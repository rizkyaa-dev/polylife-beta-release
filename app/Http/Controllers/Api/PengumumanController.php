<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AffiliationBroadcast;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PengumumanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $search = trim((string) $request->query('q', ''));
        $perPage = $this->resolvePerPage($request->query('per_page'));

        $query = AffiliationBroadcast::query()
            ->with(['creator:id,name', 'targets'])
            ->visibleToUser($user)
            ->latest('published_at')
            ->latest('id');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('title', 'like', '%'.$search.'%')
                    ->orWhere('body', 'like', '%'.$search.'%')
                    ->orWhereHas('creator', function ($creatorQuery) use ($search): void {
                        $creatorQuery->where('name', 'like', '%'.$search.'%');
                    });
            });
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(
                fn (AffiliationBroadcast $broadcast) => $this->broadcastPayload($broadcast, true)
            )->all(),
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
        $user = $request->user();

        $item = AffiliationBroadcast::query()
            ->with(['creator:id,name', 'targets'])
            ->visibleToUser($user)
            ->whereKey($broadcast)
            ->first();

        if (! $item) {
            throw (new ModelNotFoundException())->setModel(AffiliationBroadcast::class, [$broadcast]);
        }

        return response()->json([
            'data' => $this->broadcastPayload($item, false),
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

    private function broadcastPayload(AffiliationBroadcast $broadcast, bool $withExcerpt): array
    {
        $targets = $broadcast->target_mode === AffiliationBroadcast::TARGET_MODE_GLOBAL
            ? []
            : $broadcast->targets
                ->map(fn ($target) => [
                    'affiliation_type' => $target->affiliation_type,
                    'affiliation_name' => $target->affiliation_name,
                ])
                ->values()
                ->all();

        $payload = [
            'id' => (int) $broadcast->id,
            'title' => (string) ($broadcast->title ?? ''),
            'body' => (string) ($broadcast->body ?? ''),
            'image_url' => $broadcast->image_url,
            'status' => (string) ($broadcast->status ?? ''),
            'target_mode' => (string) ($broadcast->target_mode ?? ''),
            'published_at' => optional($broadcast->published_at)->toIso8601String(),
            'creator' => [
                'id' => (int) ($broadcast->creator?->id ?? 0),
                'name' => (string) ($broadcast->creator?->name ?? 'Admin'),
            ],
            'targets' => $targets,
        ];

        if ($withExcerpt) {
            $payload['excerpt'] = Str::limit((string) ($broadcast->body ?? ''), 220);
        }

        return $payload;
    }
}
