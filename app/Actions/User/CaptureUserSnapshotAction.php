<?php

namespace App\Actions\User;

use App\Models\User;

class CaptureUserSnapshotAction
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => (int) $user->is_admin,
            'role' => $user->role,
            'account_status' => $user->account_status,
            'email_verified_at' => optional($user->email_verified_at)->toDateTimeString(),
            'affiliation_type' => $user->affiliation_type,
            'affiliation_name' => $user->affiliation_name,
            'affiliation_template_id' => $user->affiliation_template_id,
            'affiliation_status' => $user->affiliation_status,
            'student_id_type' => $user->student_id_type,
            'student_id_number' => $user->student_id_number,
            'banned_at' => optional($user->banned_at)->toDateTimeString(),
            'banned_by' => $user->banned_by,
            'ban_reason_code' => $user->ban_reason_code,
            'ban_reason_text' => $user->ban_reason_text,
        ];
    }
}
