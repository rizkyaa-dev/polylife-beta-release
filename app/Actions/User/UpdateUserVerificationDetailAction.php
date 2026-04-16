<?php

namespace App\Actions\User;

use App\Models\User;
use App\Support\Endmin\AuditLogger;
use Illuminate\Support\Carbon;

class UpdateUserVerificationDetailAction
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

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->affiliation_type = $validated['affiliation_type'] ?: null;
        $user->affiliation_name = $validated['affiliation_name'] ?: null;
        $user->student_id_type = $validated['student_id_type'] ?: null;
        $user->student_id_number = $validated['student_id_number'] ?: null;
        $user->affiliation_status = $validated['affiliation_status'];
        $user->email_verified_at = $emailVerified ? ($user->email_verified_at ?: now()) : null;

        if ($validated['affiliation_status'] === 'verified') {
            $user->affiliation_verified_at = $validated['affiliation_verified_at']
                ? Carbon::parse($validated['affiliation_verified_at'])
                : ($user->affiliation_verified_at ?: now());
            $user->affiliation_verified_by = $validated['affiliation_verified_by'] ?: $actor->id;
        } else {
            $user->affiliation_verified_at = null;
            $user->affiliation_verified_by = null;
        }

        $user->save();

        AuditLogger::log(
            actor: $actor,
            module: 'verification',
            action: 'detail_update',
            targetUser: $user,
            before: $before,
            after: ($this->captureUserSnapshotAction)($user)
        );
    }
}
