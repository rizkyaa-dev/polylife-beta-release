<?php

namespace App\Actions\User;

use App\Models\User;
use App\Support\Endmin\AuditLogger;

class VerifyUserEmailAction
{
    public function __construct(
        private readonly CaptureUserSnapshotAction $captureUserSnapshotAction
    ) {
    }

    public function __invoke(User $actor, User $user): void
    {
        $before = ($this->captureUserSnapshotAction)($user);

        if (! $user->email_verified_at) {
            $user->email_verified_at = now();
            $user->save();
        }

        AuditLogger::log(
            actor: $actor,
            module: 'users',
            action: 'verify_email',
            targetUser: $user,
            before: $before,
            after: ($this->captureUserSnapshotAction)($user)
        );
    }
}
