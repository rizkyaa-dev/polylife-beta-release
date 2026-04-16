<?php

namespace App\Actions\User;

use App\Models\AdminAssignment;
use App\Models\User;
use App\Support\Endmin\AuditLogger;
use Illuminate\Validation\ValidationException;

class DemoteAdminAction
{
    public function __construct(
        private readonly CaptureUserSnapshotAction $captureUserSnapshotAction
    ) {
    }

    public function __invoke(User $actor, User $user): void
    {
        if (! $user->isAdminOnly()) {
            throw ValidationException::withMessages([
                'role' => 'Hanya akun admin yang bisa dicabut status admin-nya.',
            ]);
        }

        $before = ($this->captureUserSnapshotAction)($user);

        $user->is_admin = User::ADMIN_LEVEL_USER;
        $user->save();

        AdminAssignment::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->update([
                'status' => 'revoked',
                'revoked_by' => $actor->id,
                'revoked_at' => now(),
                'revoke_reason' => 'Status admin dicabut oleh super admin.',
            ]);

        AuditLogger::log(
            actor: $actor,
            module: 'admin-management',
            action: 'demote_admin',
            targetUser: $user,
            before: $before,
            after: ($this->captureUserSnapshotAction)($user)
        );
    }
}
