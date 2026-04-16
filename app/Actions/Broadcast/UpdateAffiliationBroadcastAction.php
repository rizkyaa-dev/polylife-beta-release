<?php

namespace App\Actions\Broadcast;

use App\Jobs\DispatchAffiliationBroadcastPushJob;
use App\Models\AffiliationBroadcast;
use App\Models\User;
use App\Queries\Broadcast\BroadcastTargetOptionsQuery;
use App\Services\BroadcastImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class UpdateAffiliationBroadcastAction
{
    public function __construct(
        private readonly BroadcastTargetOptionsQuery $broadcastTargetOptionsQuery,
        private readonly BroadcastImageService $broadcastImageService
    ) {
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function __invoke(
        AffiliationBroadcast $broadcast,
        User $actor,
        array $validated,
        ?UploadedFile $image,
        bool $removeImage,
        bool $sendPush,
        bool $publishNow
    ): AffiliationBroadcast {
        $targetContext = $this->broadcastTargetOptionsQuery->forActor($actor);
        $targetMode = $this->broadcastTargetOptionsQuery->resolveTargetMode(
            $actor,
            (string) ($validated['target_mode'] ?? AffiliationBroadcast::TARGET_MODE_AFFILIATION),
            $targetContext['canUseGlobal']
        );

        $selectedTargets = $this->broadcastTargetOptionsQuery->resolveSelectedTargets(
            (array) ($validated['targets'] ?? []),
            $targetContext['targetOptions'],
            $targetMode
        );

        if ($targetMode === AffiliationBroadcast::TARGET_MODE_AFFILIATION && $selectedTargets === []) {
            throw ValidationException::withMessages([
                'targets' => 'Pilih minimal satu target afiliasi.',
            ]);
        }

        if ($removeImage && $broadcast->image_path) {
            $this->broadcastImageService->delete($broadcast->image_path);
            $broadcast->image_path = null;
        }

        if ($image) {
            if ($broadcast->image_path) {
                $this->broadcastImageService->delete($broadcast->image_path);
            }

            $broadcast->image_path = $this->broadcastImageService->storeOptimized($image);
        }

        $broadcast->title = $validated['title'];
        $broadcast->body = $validated['body'];
        $broadcast->target_mode = $targetMode;
        $broadcast->send_push = $sendPush;

        if ($publishNow) {
            $broadcast->status = AffiliationBroadcast::STATUS_PUBLISHED;
            $broadcast->published_at = now();
        }

        $broadcast->save();
        $broadcast->targets()->delete();

        if ($selectedTargets !== []) {
            $broadcast->targets()->createMany($selectedTargets);
        }

        if ($publishNow && $broadcast->send_push) {
            DispatchAffiliationBroadcastPushJob::dispatch($broadcast->id);
        }

        return $broadcast->fresh('targets');
    }
}
