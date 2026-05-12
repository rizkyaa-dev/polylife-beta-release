<?php

namespace App\Queries\Broadcast;

use App\Models\AffiliationBroadcast;
use App\Models\User;

class VisiblePengumumanQuery
{
    public function paginateForUser(User $user, string $search = '', int $perPage = 12)
    {
        $query = AffiliationBroadcast::query()
            ->with(['creator:id,name,email', 'creator.profileAvatar', 'targets'])
            ->visibleToUser($user)
            ->latest('published_at')
            ->latest('id');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('title', 'like', '%' . $search . '%')
                    ->orWhere('body', 'like', '%' . $search . '%')
                    ->orWhereHas('creator', function ($creatorQuery) use ($search): void {
                        $creatorQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    });
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function findVisibleForUser(User $user, int $broadcastId): ?AffiliationBroadcast
    {
        return AffiliationBroadcast::query()
            ->with(['creator:id,name,email', 'creator.profileAvatar', 'targets'])
            ->visibleToUser($user)
            ->whereKey($broadcastId)
            ->first();
    }

    public function relatedForUser(User $user, int $excludedBroadcastId, int $limit = 4)
    {
        return AffiliationBroadcast::query()
            ->visibleToUser($user)
            ->where('id', '!=', $excludedBroadcastId)
            ->latest('published_at')
            ->latest('id')
            ->limit($limit)
            ->get(['id', 'title', 'published_at']);
    }
}
