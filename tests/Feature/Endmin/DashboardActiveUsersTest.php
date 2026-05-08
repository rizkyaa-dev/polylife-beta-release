<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('endmin dashboard shows users active in the current session window', function () {
    $superAdmin = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_SUPER_ADMIN,
        'role' => 'super_admin',
        'account_status' => 'active',
        'affiliation_status' => 'verified',
    ]);
    $activeUser = User::factory()->create([
        'name' => 'Active Student',
        'account_status' => 'active',
        'affiliation_status' => 'verified',
        'affiliation_name' => 'Politeknik Aktif',
    ]);
    $inactiveUser = User::factory()->create([
        'name' => 'Inactive Student',
        'account_status' => 'active',
        'affiliation_status' => 'verified',
    ]);

    DB::table('sessions')->insert([
        [
            'id' => 'active-user-session',
            'user_id' => $activeUser->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Feature Test',
            'payload' => '',
            'last_activity' => now()->subMinutes(2)->timestamp,
        ],
        [
            'id' => 'inactive-user-session',
            'user_id' => $inactiveUser->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Feature Test',
            'payload' => '',
            'last_activity' => now()->subMinutes(30)->timestamp,
        ],
    ]);

    $this->actingAs($superAdmin)
        ->get(route('endmin.dashboard'))
        ->assertOk()
        ->assertSee('User Aktif Terbaru')
        ->assertSee('Active Student')
        ->assertSee('Politeknik Aktif')
        ->assertDontSee('Inactive Student');
});
