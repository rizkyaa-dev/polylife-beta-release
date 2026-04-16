<?php

namespace App\Actions\User;

use App\Models\AdminAssignment;
use App\Models\User;

class UpsertAdminAssignmentAction
{
    public function __invoke(User $user, string $status, ?string $revokeReason, int $actorId): void
    {
        $affiliationType = trim((string) ($user->affiliation_type ?? '')) ?: 'other';
        $affiliationName = trim((string) ($user->affiliation_name ?? '')) ?: 'Unassigned Affiliation';

        $payload = [
            'status' => $status,
            'contact_email' => $user->email,
        ];

        if ($status === 'active') {
            $payload += [
                'assigned_by' => $actorId,
                'assigned_at' => now(),
                'revoked_by' => null,
                'revoked_at' => null,
                'revoke_reason' => null,
            ];
        } else {
            $payload += [
                'revoked_by' => $actorId,
                'revoked_at' => now(),
                'revoke_reason' => $revokeReason,
            ];
        }

        AdminAssignment::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'affiliation_type' => $affiliationType,
                'affiliation_name' => $affiliationName,
            ],
            $payload
        );
    }
}
