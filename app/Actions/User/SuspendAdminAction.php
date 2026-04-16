<?php

namespace App\Actions\User;

use App\Models\AdminAssignment;
use App\Models\User;
use App\Support\Endmin\AuditLogger;
use Illuminate\Validation\ValidationException;

class SuspendAdminAction
{
    public function __construct(
        private readonly CaptureUserSnapshotAction $captureUserSnapshotAction
    ) {
    }

    public function __invoke(User $actor, User $user, ?string $reason): void
    {
        if (! $user->isAdminOnly()) {
            throw ValidationException::withMessages([
                'role' => 'Hanya akun admin yang bisa disuspend.',
            ]);
        }

        $before = ($this->captureUserSnapshotAction)($user);
        $reason = $reason ?: 'Admin disuspend oleh super admin.';

        $user->account_status = 'banned';
        $user->banned_at = now();
        $user->banned_by = $actor->id;
        $user->ban_reason_code = 'admin_suspended';
        $user->ban_reason_text = $reason;
        $user->save();

        AdminAssignment::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->update([
                'status' => 'revoked',
                'revoked_by' => $actor->id,
                'revoked_at' => now(),
                'revoke_reason' => $reason,
            ]);

        AuditLogger::log(
            actor: $actor,
            module: 'admin-management',
            action: 'suspend_admin',
            targetUser: $user,
            before: $before,
            after: ($this->captureUserSnapshotAction)($user),
            context: ['reason' => $reason]
        );
    }
}
