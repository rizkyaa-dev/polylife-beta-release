<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class SecurePasswordUpdateAction
{
    public function __invoke(User $user, string $currentPassword, string $newPassword): void
    {
        $this->logoutOtherWebSessions($currentPassword);

        $user->forceFill([
            'password' => Hash::make($newPassword),
        ])->save();

        $this->deleteOtherDatabaseSessions($user, $this->currentSessionId());
        $this->revokeApiTokens($user);

        $user->notify(new PasswordChangedNotification(
            ipAddress: request()->ip(),
            userAgent: request()->userAgent()
        ));
    }

    private function logoutOtherWebSessions(string $currentPassword): void
    {
        $guard = auth()->guard('web');

        if (method_exists($guard, 'logoutOtherDevices')) {
            $guard->logoutOtherDevices($currentPassword);
        }
    }

    private function deleteOtherDatabaseSessions(User $user, string $currentSessionId): void
    {
        $table = (string) config('session.table', 'sessions');

        if ($table === '' || ! Schema::hasTable($table)) {
            return;
        }

        DB::table($table)
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }

    private function currentSessionId(): string
    {
        if (request()->hasSession()) {
            return request()->session()->getId();
        }

        return (string) session()->getId();
    }

    private function revokeApiTokens(User $user): void
    {
        $user->tokens()->delete();
    }
}
