<?php

namespace App\Actions\User;

use App\Models\User;
use App\Support\Endmin\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BulkProcessUsersAction
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array{selected_count: int, affected_count: int, skipped_count: int, processed_ids: array<int, int>}
     */
    public function __invoke(User $actor, array $validated): array
    {
        $actorId = (int) $actor->id;
        $action = (string) $validated['action'];
        $reason = trim((string) ($validated['reason'] ?? ''));
        $selectedIds = collect($validated['user_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $selectedCount = $selectedIds->count();
        $eligibleIds = collect();
        $affectedCount = 0;
        $now = now();

        DB::transaction(function () use ($action, $actorId, $reason, $selectedIds, $now, &$eligibleIds, &$affectedCount): void {
            $baseQuery = User::query()->whereIn('id', $selectedIds);

            switch ($action) {
                case 'verify_email':
                    $eligibleIds = (clone $baseQuery)
                        ->whereNull('email_verified_at')
                        ->pluck('id');

                    $affectedCount = User::query()
                        ->whereIn('id', $eligibleIds)
                        ->update([
                            'email_verified_at' => $now,
                            'updated_at' => $now,
                        ]);
                    break;

                case 'activate_accounts':
                    $eligibleIds = (clone $baseQuery)
                        ->where('account_status', '!=', 'active')
                        ->pluck('id');

                    $affectedCount = User::query()
                        ->whereIn('id', $eligibleIds)
                        ->update([
                            'account_status' => 'active',
                            'banned_at' => null,
                            'banned_by' => null,
                            'ban_reason_code' => null,
                            'ban_reason_text' => null,
                            'updated_at' => $now,
                        ]);
                    break;

                case 'ban_accounts':
                    $banReason = $reason !== '' ? $reason : 'Bulk ban oleh super admin.';

                    $eligibleIds = (clone $baseQuery)
                        ->where('id', '!=', $actorId)
                        ->where('is_admin', '!=', User::ADMIN_LEVEL_SUPER_ADMIN)
                        ->where('account_status', '!=', 'banned')
                        ->pluck('id');

                    $affectedCount = User::query()
                        ->whereIn('id', $eligibleIds)
                        ->update([
                            'account_status' => 'banned',
                            'banned_at' => $now,
                            'banned_by' => $actorId,
                            'ban_reason_code' => 'bulk_ban',
                            'ban_reason_text' => $banReason,
                            'updated_at' => $now,
                        ]);
                    break;

                case 'delete_accounts':
                    $eligibleIds = (clone $baseQuery)
                        ->where('id', '!=', $actorId)
                        ->where('is_admin', '!=', User::ADMIN_LEVEL_SUPER_ADMIN)
                        ->pluck('id');

                    $affectedCount = User::query()
                        ->whereIn('id', $eligibleIds)
                        ->delete();
                    break;

                case 'promote_to_admin':
                    $eligibleIds = (clone $baseQuery)
                        ->where('is_admin', User::ADMIN_LEVEL_USER)
                        ->pluck('id');

                    $affectedCount = User::query()
                        ->whereIn('id', $eligibleIds)
                        ->update([
                            'is_admin' => User::ADMIN_LEVEL_ADMIN,
                            'role' => 'admin',
                            'account_status' => 'active',
                            'banned_at' => null,
                            'banned_by' => null,
                            'ban_reason_code' => null,
                            'ban_reason_text' => null,
                            'updated_at' => $now,
                        ]);
                    break;

                case 'demote_to_user':
                    $eligibleIds = (clone $baseQuery)
                        ->where('id', '!=', $actorId)
                        ->where('is_admin', User::ADMIN_LEVEL_ADMIN)
                        ->pluck('id');

                    $affectedCount = User::query()
                        ->whereIn('id', $eligibleIds)
                        ->update([
                            'is_admin' => User::ADMIN_LEVEL_USER,
                            'role' => 'user',
                            'updated_at' => $now,
                        ]);
                    break;
            }
        });

        $processedIds = $eligibleIds->map(fn ($id) => (int) $id)->values();
        $skippedCount = max(0, $selectedCount - $affectedCount);

        AuditLogger::log(
            actor: $actor,
            module: 'users',
            action: 'bulk_' . $action,
            before: [
                'selected_count' => $selectedCount,
                'selected_user_ids' => $selectedIds->all(),
            ],
            after: [
                'affected_count' => $affectedCount,
                'processed_user_ids' => $processedIds->all(),
            ],
            context: [
                'skipped_count' => $skippedCount,
                'reason' => $reason !== '' ? $reason : null,
            ]
        );

        return [
            'selected_count' => $selectedCount,
            'affected_count' => $affectedCount,
            'skipped_count' => $skippedCount,
            'processed_ids' => $processedIds->all(),
        ];
    }
}
