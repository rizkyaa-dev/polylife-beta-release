<?php

namespace App\Queries\Broadcast;

use App\Models\AffiliationBroadcast;

class BroadcastVerificationIndexQuery
{
    /**
     * @return array<string, mixed>
     */
    public function build(string $search, string $statusFilter, string $targetModeFilter): array
    {
        $stats = AffiliationBroadcast::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as published', [AffiliationBroadcast::STATUS_PUBLISHED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as archived', [AffiliationBroadcast::STATUS_ARCHIVED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as draft', [AffiliationBroadcast::STATUS_DRAFT])
            ->first();

        $broadcastsQuery = AffiliationBroadcast::query()
            ->with([
                'creator:id,name,email,is_admin,role',
                'targets:id,broadcast_id,affiliation_name',
            ]);

        if ($search !== '') {
            $broadcastsQuery->where(function ($query) use ($search): void {
                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhere('body', 'like', '%'.$search.'%')
                    ->orWhereHas('creator', function ($creatorQuery) use ($search): void {
                        $creatorQuery->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('targets', function ($targetQuery) use ($search): void {
                        $targetQuery->where('affiliation_name', 'like', '%'.$search.'%');
                    });
            });
        }

        if (in_array($statusFilter, [
            AffiliationBroadcast::STATUS_DRAFT,
            AffiliationBroadcast::STATUS_PUBLISHED,
            AffiliationBroadcast::STATUS_ARCHIVED,
        ], true)) {
            $broadcastsQuery->where('status', $statusFilter);
        }

        if (in_array($targetModeFilter, [
            AffiliationBroadcast::TARGET_MODE_AFFILIATION,
            AffiliationBroadcast::TARGET_MODE_GLOBAL,
        ], true)) {
            $broadcastsQuery->where('target_mode', $targetModeFilter);
        }

        return [
            'broadcasts' => $broadcastsQuery
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
            'stats' => [
                'total' => (int) ($stats->total ?? 0),
                'published' => (int) ($stats->published ?? 0),
                'archived' => (int) ($stats->archived ?? 0),
                'draft' => (int) ($stats->draft ?? 0),
            ],
        ];
    }
}
