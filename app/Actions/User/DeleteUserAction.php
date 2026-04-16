<?php

namespace App\Actions\User;

use App\Models\User;
use App\Support\Endmin\AuditLogger;

class DeleteUserAction
{
    public function __construct(
        private readonly CaptureUserSnapshotAction $captureUserSnapshotAction
    ) {
    }

    public function __invoke(User $actor, User $user): void
    {
        if ((int) $actor->id === (int) $user->id) {
            abort(403, 'Tidak bisa menghapus akun sendiri.');
        }

        $before = ($this->captureUserSnapshotAction)($user);
        $deletedUserId = $user->id;
        $user->delete();

        AuditLogger::log(
            actor: $actor,
            module: 'users',
            action: 'delete_account',
            targetUser: null,
            before: $before,
            after: ['deleted' => true],
            context: ['deleted_user_id' => $deletedUserId]
        );
    }
}
