<?php

namespace App\Support\Api;

use App\Models\User;
use App\Models\UserProfileAvatar;

class UserPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function fromUser(User $user): array
    {
        $user->loadMissing(['profile', 'pendingAffiliationRequest']);

        $profile = $user->profile;
        $avatar = UserProfileAvatar::query()
            ->where('user_id', $user->id)
            ->first(['id', 'user_id', 'mime_type', 'width', 'height', 'size', 'updated_at']);
        $pendingAffiliationRequest = $user->pendingAffiliationRequest;
        $latestRejectedAffiliationRequest = $user->affiliationRequests()
            ->where('status', \App\Models\AffiliationRequest::STATUS_REJECTED)
            ->latest()
            ->first();

        return [
            'id' => (int) $user->id,
            'name' => (string) ($user->name ?? ''),
            'email' => (string) $user->email,
            'role' => (string) $user->roleKeyFromAdminLevel(),
            'role_label' => (string) $user->roleLabel(),
            'admin_level' => (int) $user->adminLevel(),
            'account_status' => (string) ($user->account_status ?? 'active'),
            'email_verified_at' => optional($user->email_verified_at)->toIso8601String(),
            'affiliation' => [
                'type' => $user->affiliation_type,
                'name' => $user->affiliation_name,
                'student_id_type' => $user->student_id_type,
                'student_id_number' => $user->student_id_number,
                'status' => $user->affiliation_status,
                'verified_at' => optional($user->affiliation_verified_at)->toIso8601String(),
                'pending_request' => $pendingAffiliationRequest ? [
                    'id' => (int) $pendingAffiliationRequest->id,
                    'type' => $pendingAffiliationRequest->affiliation_type,
                    'name' => $pendingAffiliationRequest->affiliation_name,
                    'student_id_type' => $pendingAffiliationRequest->student_id_type,
                    'student_id_number' => $pendingAffiliationRequest->student_id_number,
                    'status' => $pendingAffiliationRequest->status,
                    'created_at' => optional($pendingAffiliationRequest->created_at)->toIso8601String(),
                ] : null,
                'latest_rejected_request' => $latestRejectedAffiliationRequest ? [
                    'id' => (int) $latestRejectedAffiliationRequest->id,
                    'type' => $latestRejectedAffiliationRequest->affiliation_type,
                    'name' => $latestRejectedAffiliationRequest->affiliation_name,
                    'student_id_type' => $latestRejectedAffiliationRequest->student_id_type,
                    'student_id_number' => $latestRejectedAffiliationRequest->student_id_number,
                    'status' => $latestRejectedAffiliationRequest->status,
                    'rejection_reason' => $latestRejectedAffiliationRequest->rejection_reason,
                    'reviewed_at' => optional($latestRejectedAffiliationRequest->reviewed_at)->toIso8601String(),
                ] : null,
            ],
            'profile' => [
                'display_name' => $profile?->display_name,
                'bio' => $profile?->bio,
                'phone' => $profile?->phone,
                'date_of_birth' => $profile?->date_of_birth?->toDateString(),
                'gender' => $profile?->gender,
                'location' => $profile?->location,
                'theme_preference' => $profile?->theme_preference ?? 'system',
                'timezone' => $profile?->timezone,
                'locale' => $profile?->locale,
                'has_avatar' => (bool) $avatar,
                'avatar_url' => $avatar ? route('api.v1.profile.avatar', [], false) : null,
                'avatar_updated_at' => optional($avatar?->updated_at)->toIso8601String(),
            ],
        ];
    }
}
