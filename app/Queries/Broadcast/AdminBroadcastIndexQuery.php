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
                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhere('body', 'like', '%'.$search.'%')
                    ->orWhereHas('creator', function ($creatorQuery) use ($search): void {
                        $creatorQuery->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($targetFilter !== '') {
            $broadcastsQuery->where(function ($query) use ($targetFilter): void {
                $query->where('target_mode', AffiliationBroadcast::TARGET_MODE_GLOBAL)
                    ->orWhereHas('targets', function ($targetQuery) use ($targetFilter): void {
                        $targetQuery->where('affiliation_name', 'like', '%'.$targetFilter.'%');
                    });
            });
        }

        $summaryQuery = AffiliationBroadcast::query();
        if (! $actor->isSuperAdmin()) {
            $summaryQuery->where('created_by', $actor->id);
        }
        $summary = $summaryQuery
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as draft', [AffiliationBroadcast::STATUS_DRAFT])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as published', [AffiliationBroadcast::STATUS_PUBLISHED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as archived', [AffiliationBroadcast::STATUS_ARCHIVED])
            ->first();

        return [
            'broadcasts' => $broadcastsQuery
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString(),
            'summary' => [
                'total' => (int) ($summary->total ?? 0),
                'draft' => (int) ($summary->draft ?? 0),
                'published' => (int) ($summary->published ?? 0),
                'archived' => (int) ($summary->archived ?? 0),
            ],
        ];
    }
}
