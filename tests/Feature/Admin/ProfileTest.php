<?php

use App\Models\AffiliationRequest;
use App\Models\User;

test('admin profile page is displayed with adjusted profile features', function () {
    $admin = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_ADMIN,
        'account_status' => 'active',
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Admin',
        'affiliation_status' => 'verified',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.profile'))
        ->assertOk()
        ->assertSee('Profil Admin')
        ->assertSee('Kewenangan broadcast')
        ->assertSee('UNIVERSITY - Universitas Admin')
        ->assertSeeVolt('profile.update-profile-details-form')
        ->assertSeeVolt('profile.update-password-form')
        ->assertDontSeeVolt('profile.update-affiliation-request-form')
        ->assertDontSeeVolt('profile.delete-user-form')
        ->assertDontSee('Hari Libur Rutin')
        ->assertDontSee('Hari Libur Nasional Otomatis');
});

test('admin profile is opened from sidebar avatar instead of dedicated menu item', function () {
    $admin = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_ADMIN,
        'account_status' => 'active',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.broadcasts.index'))
        ->assertOk()
        ->assertSee('href="'.route('admin.profile').'"', false)
        ->assertSee('Buka profil admin')
        ->assertDontSee('sidebar-link-text">Profil', false);
});

test('admin profile does not expose broadcast target while affiliation is pending', function () {
    $admin = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_ADMIN,
        'account_status' => 'active',
        'affiliation_type' => null,
        'affiliation_name' => null,
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
        ->get(route('admin.profile'))
        ->assertOk()
        ->assertSeeVolt('profile.update-affiliation-request-form')
        ->assertSee('Pengajuan sedang menunggu review')
        ->assertSee('PENDING001')
        ->assertSee('Akun admin ini belum memiliki afiliasi terverifikasi.')
        ->assertDontSee('UNIVERSITY - Universitas Pending Admin');
});

test('admin profile treats verified status without affiliation data as unverified', function () {
    $admin = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_ADMIN,
        'account_status' => 'active',
        'affiliation_type' => null,
        'affiliation_name' => null,
        'affiliation_template_id' => null,
        'student_id_type' => null,
        'student_id_number' => null,
        'affiliation_status' => 'verified',
        'affiliation_verified_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.profile'))
        ->assertOk()
        ->assertSeeVolt('profile.update-affiliation-request-form')
        ->assertSee('Belum diisi')
        ->assertSee('Menunggu review')
        ->assertDontSee('Afiliasi sudah terverifikasi')
        ->assertDontSee('Diverifikasi');
});

test('admin without affiliation request can submit affiliation from profile', function () {
    $admin = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_ADMIN,
        'account_status' => 'active',
        'affiliation_type' => null,
        'affiliation_name' => null,
        'affiliation_status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.profile'))
        ->assertOk()
        ->assertSeeVolt('profile.update-affiliation-request-form')
        ->assertSee('Submit Pengajuan');
});
