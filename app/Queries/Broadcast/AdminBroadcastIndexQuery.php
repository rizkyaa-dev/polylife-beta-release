<?php

namespace App\Queries\Broadcast;

use App\Models\AffiliationBroadcast;
use App\Models\User;

class AdminBroadcastIndexQuery
{
    /**
     * @return array<string, mixed>
     */
    public function build(User $actor, string $search, string $statusFilter, string $targetFilter): array
    {
        $broadcastsQuery = AffiliationBroadcast::query()
            ->with(['creator:id,name,email', 'targets']);

        if (! $actor->isSuperAdmin()) {
            $broadcastsQuery->where('created_by', $actor->id);
        }

        if (in_array($statusFilter, [
            AffiliationBroadcast::STATUS_DRAFT,
            AffiliationBroadcast::STATUS_PUBLISHED,
            AffiliationBroadcast::STATUS_ARCHIVED,
        ], true)) {
            $broadcastsQuery->where('status', $statusFilter);
        }

        if ($search !== '') {
            $broadcastsQuery->where(function ($query) use ($search): void {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('body', 'like', '%' . $search . '%')
                    ->orWhereHas('creator', function ($creatorQuery) use ($search): void {
                        $creatorQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($targetFilter !== '') {
            $broadcastsQuery->where(function ($query) use ($targetFilter): void {
                $query->where('target_mode', AffiliationBroadcast::TARGET_MODE_GLOBAL)
                    ->orWhereHas('targets', function ($targetQuery) use ($targetFilter): void {
                        $targetQuery->where('affiliation_name', 'like', '%' . $targetFilter . '%');
                    });
            });
        }

        $summaryQuery = AffiliationBroadcast::query();
        if (! $actor->isSuperAdmin()) {
            $summaryQuery->where('created_by', $actor->id);
        }

        return [
            'broadcasts' => $broadcastsQuery
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString(),
            'summary' => [
                'total' => (clone $summaryQuery)->count(),
                'draft' => (clone $summaryQuery)->where('status', AffiliationBroadcast::STATUS_DRAFT)->count(),
                'published' => (clone $summaryQuery)->where('status', AffiliationBroadcast::STATUS_PUBLISHED)->count(),
                'archived' => (clone $summaryQuery)->where('status', AffiliationBroadcast::STATUS_ARCHIVED)->count(),
            ],
        ];
    }
}
