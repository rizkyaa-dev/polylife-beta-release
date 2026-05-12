<?php

namespace App\Actions\Broadcast;

use App\Models\AffiliationBroadcast;
use App\Models\AffiliationBroadcastRead;
use App\Models\User;
use Illuminate\Support\Collection;

class MarkPengumumanReadAction
{
    public function __invoke(User $user, array $broadcastIds): array
    {
        $ids = collect($broadcastIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [
                'read_count' => 0,
                'unread_count' => $this->unreadCountForUser($user),
            ];
        }

        $visibleIds = $this->visibleIdsForUser($user, $ids);

        if ($visibleIds->isNotEmpty()) {
            $now = now();
            $rows = $visibleIds
                ->map(fn (int $broadcastId) => [
                    'user_id' => $user->id,
                    'broadcast_id' => $broadcastId,
                    'read_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all();

            AffiliationBroadcastRead::query()->upsert(
                $rows,
                ['user_id', 'broadcast_id'],
                ['read_at', 'updated_at']
            );
        }

        return [
            'read_count' => $visibleIds->count(),
            'unread_count' => $this->unreadCountForUser($user),
        ];
    }

    public function unreadCountForUser(User $user): int
    {
        return AffiliationBroadcast::query()
            ->visibleToUser($user)
            ->whereDoesntHave('reads', fn ($readQuery) => $readQuery->where('user_id', $user->id))
            ->count();
    }

    private function visibleIdsForUser(User $user, Collection $ids): Collection
    {
        return AffiliationBroadcast::query()
            ->visibleToUser($user)
            ->whereKey($ids->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
    }
}
