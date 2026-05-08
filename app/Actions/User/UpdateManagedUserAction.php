<?php

namespace App\Actions\User;

use App\Models\User;
use App\Support\Endmin\AuditLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UpdateManagedUserAction
{
    public function __construct(
        private readonly CaptureUserSnapshotAction $captureUserSnapshotAction
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function __invoke(User $actor, User $user, array $validated): void
    {
        $before = ($this->captureUserSnapshotAction)($user);
        $isSelf = (int) $actor->id === (int) $user->id;
        $targetIsSuperAdmin = $user->isSuperAdmin();
        $password = (string) ($validated['password'] ?? '');

        if ($targetIsSuperAdmin && ! $isSelf && $password !== '') {
            throw ValidationException::withMessages([
                'password' => 'Password super admin lain tidak dapat diubah.',
            ]);
        }

        if ($targetIsSuperAdmin && ! $isSelf && $validated['account_status'] === 'banned') {
            throw ValidationException::withMessages([
                'account_status' => 'Super admin tidak dapat membanned sesama super admin.',
            ]);
        }

        if ($validated['account_status'] === 'banned' && $isSelf) {
            throw ValidationException::withMessages([
                'account_status' => 'Tidak bisa memblokir akun sendiri.',
            ]);
        }

        $emailChanged = $validated['email'] !== $user->email;
        $user->email = $validated['email'];
        $user->account_status = $validated['account_status'];

        if ($password !== '') {
            $user->password = Hash::make($password);
        }

        if ($emailChanged) {
            $user->email_verified_at = $user->isSuperAdmin() ? now() : null;
        }

        if ($validated['account_status'] === 'banned') {
            $user->banned_at = $user->banned_at ?: now();
            $user->banned_by = $actor->id;
            $user->ban_reason_code = $validated['ban_reason_code'] ?? null;
            $user->ban_reason_text = $validated['ban_reason_text'] ?? null;
        } else {
            $user->banned_at = null;
            $user->banned_by = null;
            $user->ban_reason_code = null;
            $user->ban_reason_text = null;
        }

        $user->save();

        AuditLogger::log(
            actor: $actor,
            module: 'users',
            action: 'update_account',
            targetUser: $user,
            before: $before,
            after: ($this->captureUserSnapshotAction)($user),
            context: [
                'password_updated' => $password !== '',
            ]
        );
    }
}
