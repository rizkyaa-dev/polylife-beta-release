<?php

use App\Actions\User\BulkProcessUsersAction;
use App\Models\User;

test('bulk process users action bans only eligible accounts', function () {
    $action = app(BulkProcessUsersAction::class);

    $actor = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_SUPER_ADMIN,
        'role' => 'super_admin',
        'account_status' => 'active',
    ]);
    $eligible = User::factory()->create([
        'account_status' => 'active',
    ]);
    $otherSuperAdmin = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_SUPER_ADMIN,
        'role' => 'super_admin',
        'account_status' => 'active',
    ]);
    $alreadyBanned = User::factory()->create([
        'account_status' => 'banned',
    ]);

    $result = $action($actor, [
        'action' => 'ban_accounts',
        'user_ids' => [$actor->id, $eligible->id, $otherSuperAdmin->id, $alreadyBanned->id],
        'reason' => 'Pelanggaran policy',
    ]);

    $eligible->refresh();
    $otherSuperAdmin->refresh();
    $alreadyBanned->refresh();

    expect($result['affected_count'])->toBe(1);
    expect($result['skipped_count'])->toBe(3);
    expect($eligible->account_status)->toBe('banned');
    expect((int) $eligible->banned_by)->toBe((int) $actor->id);
    expect($eligible->ban_reason_text)->toBe('Pelanggaran policy');
    expect($otherSuperAdmin->account_status)->toBe('active');
    expect($alreadyBanned->account_status)->toBe('banned');
});
