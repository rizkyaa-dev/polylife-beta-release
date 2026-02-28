<?php

namespace App\Jobs;

use App\Models\AffiliationBroadcast;
use App\Services\AffiliationBroadcastPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchAffiliationBroadcastPushJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $broadcastId)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(AffiliationBroadcastPushService $service): void
    {
        $broadcast = AffiliationBroadcast::query()
            ->with('targets')
            ->find($this->broadcastId);

        if (! $broadcast || ! $broadcast->isPublished() || ! $broadcast->send_push) {
            return;
        }

        $service->dispatchForBroadcast($broadcast);
    }
}
