<?php

namespace App\Actions\Broadcast;

use App\Models\AffiliationBroadcast;

class UnarchiveAffiliationBroadcastAction
{
    public function __invoke(AffiliationBroadcast $broadcast): void
    {
        if (! $broadcast->isArchived()) {
            return;
        }

        $broadcast->status = $broadcast->published_at
            ? AffiliationBroadcast::STATUS_PUBLISHED
            : AffiliationBroadcast::STATUS_DRAFT;
        $broadcast->save();
    }
}
