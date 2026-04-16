<?php

namespace App\Actions\Broadcast;

use App\Models\AffiliationBroadcast;
use App\Services\BroadcastImageService;

class DeleteAffiliationBroadcastAction
{
    public function __construct(
        private readonly BroadcastImageService $broadcastImageService
    ) {
    }

    public function __invoke(AffiliationBroadcast $broadcast): void
    {
        if ($broadcast->image_path) {
            $this->broadcastImageService->delete($broadcast->image_path);
        }

        $broadcast->delete();
    }
}
