<?php

namespace App\Actions\User;

use App\Models\User;
use App\Support\Endmin\AuditLogger;
use Illuminate\Validation\ValidationException;

class PromoteAdminAction
{
    public function __construct(
        private readonly CaptureUserSnapshotAction $captureUserSnapshotAction,
        private readonly UpsertAdminAssignmentAction $upsertAdminAssignmentAction
    ) {
    }

    public function __invoke(User $actor, User $user): void
    {
        if ($user->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'role' => 'Akun super admin tidak dapat diubah melalui menu ini.',
            ]);
        }

        $before = ($this->captureUserSnapshotAction)($user);
        $user->is_admin = User::ADMIN_LEVEL_ADMIN;
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
            action: 'promote_to_admin',
            targetUser: $user,
            before: $before,
            after: ($this->captureUserSnapshotAction)($user)
        );
    }
}
