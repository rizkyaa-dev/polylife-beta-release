<?php

use App\Models\AffiliationRequest;
use App\Models\User;

test('admin with pending affiliation cannot choose broadcast target yet', function () {
    $admin = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_ADMIN,
        'account_status' => 'active',
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Pending Admin',
        'affiliation_status' => 'pending',
    ]);

    AffiliationRequest::query()->create([
        'user_id' => $admin->id,
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Pending Admin',
        'student_id_type' => 'nim',
        'student_id_number' => 'PENDING001',
        'status' => AffiliationRequest::STATUS_PENDING,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.broadcasts.create'))
        ->assertOk()
        ->assertSee('Belum ada target afiliasi yang tersedia.')
        ->assertSee('Akun admin ini belum punya assignment afiliasi aktif.')
        ->assertDontSee('UNIVERSITY - Universitas Pending Admin');
});

test('admin pending affiliation target is rejected when storing broadcast', function () {
    $admin = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_ADMIN,
        'account_status' => 'active',
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Pending Admin',
        'affiliation_status' => 'pending',
    ]);

    AffiliationRequest::query()->create([
        'user_id' => $admin->id,
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Pending Admin',
        'student_id_type' => 'nim',
        'student_id_number' => 'PENDING001',
        'status' => AffiliationRequest::STATUS_PENDING,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.broadcasts.store'), [
            'title' => 'Info Kampus Pending',
            'body' => 'Isi broadcast untuk target pending.',
            'target_mode' => 'affiliation',
            'targets' => ['university||Universitas Pending Admin'],
            'send_push' => '0',
        ])
        ->assertSessionHasErrors('targets');

    $this->assertDatabaseMissing('affiliation_broadcast_targets', [
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Pending Admin',
    ]);
});

test('verified admin can choose own affiliation as broadcast target', function () {
    $admin = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_ADMIN,
        'account_status' => 'active',
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Verified Admin',
        'affiliation_status' => 'verified',
        'affiliation_verified_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.broadcasts.create'))
        ->assertOk()
        ->assertDontSee('Belum ada target afiliasi yang tersedia.')
        ->assertSee('UNIVERSITY - Universitas Verified Admin');
});
