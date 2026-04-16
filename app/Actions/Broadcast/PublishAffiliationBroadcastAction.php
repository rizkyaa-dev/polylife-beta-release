<?php

namespace App\Actions\Broadcast;

use App\Jobs\DispatchAffiliationBroadcastPushJob;
use App\Models\AffiliationBroadcast;
use Illuminate\Validation\ValidationException;

class PublishAffiliationBroadcastAction
{
    public function __invoke(AffiliationBroadcast $broadcast): void
    {
        if (! $broadcast->isDraft()) {
            throw ValidationException::withMessages([
                'broadcast' => 'Hanya broadcast draft yang bisa dipublish.',
            ]);
        }

        if (
            $broadcast->target_mode === AffiliationBroadcast::TARGET_MODE_AFFILIATION
            && ! $broadcast->targets()->exists()
        ) {
            throw ValidationException::withMessages([
                'broadcast' => 'Broadcast belum memiliki target afiliasi.',
            ]);
        }

        $broadcast->status = AffiliationBroadcast::STATUS_PUBLISHED;
        $broadcast->published_at = now();
        $broadcast->save();

        if ($broadcast->send_push) {
            DispatchAffiliationBroadcastPushJob::dispatch($broadcast->id);
        }
    }
}
