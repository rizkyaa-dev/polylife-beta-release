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
        $broadcastsQuery = AffiliationBroadcast::query()
            ->with([
                'creator:id,name,email,is_admin,role',
                'targets:id,broadcast_id,affiliation_name',
            ]);

        if ($search !== '') {
            $broadcastsQuery->where(function ($query) use ($search): void {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('body', 'like', '%' . $search . '%')
                    ->orWhereHas('creator', function ($creatorQuery) use ($search): void {
                        $creatorQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('targets', function ($targetQuery) use ($search): void {
                        $targetQuery->where('affiliation_name', 'like', '%' . $search . '%');
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
                'total' => AffiliationBroadcast::query()->count(),
                'published' => AffiliationBroadcast::query()->where('status', AffiliationBroadcast::STATUS_PUBLISHED)->count(),
                'archived' => AffiliationBroadcast::query()->where('status', AffiliationBroadcast::STATUS_ARCHIVED)->count(),
                'draft' => AffiliationBroadcast::query()->where('status', AffiliationBroadcast::STATUS_DRAFT)->count(),
            ],
        ];
    }
}
