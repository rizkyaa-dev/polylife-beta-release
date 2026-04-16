<?php

namespace App\Actions\User;

use App\Models\User;
use App\Support\Endmin\AuditLogger;

class UpdateUserVerificationAction
{
    public function __construct(
        private readonly CaptureUserSnapshotAction $captureUserSnapshotAction
    ) {
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function __invoke(User $actor, User $user, array $validated, bool $emailVerified): void
    {
        $before = ($this->captureUserSnapshotAction)($user);

        $user->email_verified_at = $emailVerified ? ($user->email_verified_at ?: now()) : null;
        $user->affiliation_status = $validated['affiliation_status'];

        if ($validated['affiliation_status'] === 'verified') {
            $user->affiliation_verified_at = $user->affiliation_verified_at ?: now();
            $user->affiliation_verified_by = $actor->id;
        } else {
            $user->affiliation_verified_at = null;
            $user->affiliation_verified_by = null;
        }

        $user->save();

        AuditLogger::log(
            actor: $actor,
            module: 'verification',
            action: 'quick_update',
            targetUser: $user,
            before: $before,
            after: ($this->captureUserSnapshotAction)($user)
        );
    }
}
