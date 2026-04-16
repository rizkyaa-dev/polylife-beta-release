<?php

namespace App\Actions\Broadcast;

use App\Models\AffiliationBroadcast;

class ArchiveAffiliationBroadcastAction
{
    public function __invoke(AffiliationBroadcast $broadcast): bool
    {
        if ($broadcast->isArchived()) {
            return false;
        }

        $broadcast->status = AffiliationBroadcast::STATUS_ARCHIVED;
        $broadcast->save();

        return true;
    }
}
