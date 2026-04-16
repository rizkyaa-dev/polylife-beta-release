<?php

namespace App\Actions\User;

use App\Models\User;
use App\Support\Endmin\AuditLogger;
use Illuminate\Validation\ValidationException;

class ActivateAdminAction
{
    public function __construct(
        private readonly CaptureUserSnapshotAction $captureUserSnapshotAction,
        private readonly UpsertAdminAssignmentAction $upsertAdminAssignmentAction
    ) {
    }

    public function __invoke(User $actor, User $user): void
    {
        if (! $user->isAdminOnly()) {
            throw ValidationException::withMessages([
                'role' => 'Hanya akun admin yang bisa diaktifkan dari menu ini.',
            ]);
        }

        $before = ($this->captureUserSnapshotAction)($user);

        $user->account_status = 'active';
        $user->banned_at = null;
        $user->banned_by = null;
        $user->ban_reason_code = null;
        $user->ban_reason_text = null;
        $user->save();

        ($this->upsertAdminAssignmentAction)($user, 'active', null, (int) $actor->id);

        AuditLogger::log(
            actor: $actor,
            module: 'admin-management',
            action: 'activate_admin',
            targetUser: $user,
            before: $before,
            after: ($this->captureUserSnapshotAction)($user)
        );
    }
}
