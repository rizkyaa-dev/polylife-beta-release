<?php

namespace App\Actions\Broadcast;

use App\Jobs\DispatchAffiliationBroadcastPushJob;
use App\Models\AffiliationBroadcast;
use App\Models\User;
use App\Queries\Broadcast\BroadcastTargetOptionsQuery;
use App\Services\BroadcastImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class StoreAffiliationBroadcastAction
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
        User $actor,
        array $validated,
        ?UploadedFile $image,
        bool $sendPush,
        bool $publishNow
    ): AffiliationBroadcast {
        $targetContext = $this->broadcastTargetOptionsQuery->forActor($actor);
        if ($targetContext['creationBlocked']) {
            throw ValidationException::withMessages([
                'targets' => 'Akun admin belum memiliki assignment afiliasi aktif. Hubungi super admin untuk menetapkan afiliasi.',
            ]);
        }

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

        $broadcast = AffiliationBroadcast::query()->create([
            'created_by' => $actor->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'image_path' => $image ? $this->broadcastImageService->storeOptimized($image) : null,
            'target_mode' => $targetMode,
            'send_push' => $sendPush,
            'status' => $publishNow ? AffiliationBroadcast::STATUS_PUBLISHED : AffiliationBroadcast::STATUS_DRAFT,
            'published_at' => $publishNow ? now() : null,
        ]);

        if ($selectedTargets !== []) {
            $broadcast->targets()->createMany($selectedTargets);
        }

        if ($publishNow && $broadcast->send_push) {
            DispatchAffiliationBroadcastPushJob::dispatch($broadcast->id);
        }

        return $broadcast;
    }
}
