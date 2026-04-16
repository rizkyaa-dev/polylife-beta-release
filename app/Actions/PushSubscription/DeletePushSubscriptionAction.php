<?php

namespace App\Actions\PushSubscription;

use App\Models\PushSubscription;

class DeletePushSubscriptionAction
{
    public function __invoke(int $userId, string $endpoint): void
    {
        PushSubscription::query()
            ->where('user_id', $userId)
            ->where('endpoint', $endpoint)
            ->delete();
    }
}
