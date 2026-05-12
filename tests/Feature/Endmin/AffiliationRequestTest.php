<?php

use App\Models\AffiliationRequest;
use App\Models\User;

test('super admin can approve affiliation request and create template', function () {
    $superAdmin = User::factory()->create(['is_admin' => User::ADMIN_LEVEL_SUPER_ADMIN]);
    $user = User::factory()->create(['affiliation_status' => 'pending']);
    $request = AffiliationRequest::query()->create([
        'user_id' => $user->id,
        'affiliation_type' => 'university',
        'affiliation_name' => 'Univ Contoh',
        'student_id_type' => 'nim',
        'student_id_number' => '220001',
        'status' => AffiliationRequest::STATUS_PENDING,
    ]);

    $this->actingAs($superAdmin)
        ->patch(route('endmin.affiliations.requests.approve', $request), [
            'canonical_affiliation_name' => 'Universitas Contoh',
        ])
        ->assertRedirect(route('endmin.affiliations.index'));

    $this->assertDatabaseHas('affiliation_templates', [
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Contoh',
        'is_active' => true,
    ]);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Contoh',
        'student_id_type' => 'nim',
        'student_id_number' => '220001',
        'affiliation_status' => 'verified',
        'affiliation_verified_by' => $superAdmin->id,
    ]);

    $this->assertDatabaseHas('affiliation_requests', [
        'id' => $request->id,
        'status' => AffiliationRequest::STATUS_APPROVED,
        'reviewed_by' => $superAdmin->id,
    ]);
});

test('super admin can reject affiliation request', function () {
    $superAdmin = User::factory()->create(['is_admin' => User::ADMIN_LEVEL_SUPER_ADMIN]);
    $request = AffiliationRequest::query()->create([
        'user_id' => User::factory()->create()->id,
        'affiliation_type' => 'university',
        'affiliation_name' => 'Tidak Valid',
        'student_id_type' => 'nim',
        'student_id_number' => 'x',
        'status' => AffiliationRequest::STATUS_PENDING,
    ]);

    $this->actingAs($superAdmin)
        ->patch(route('endmin.affiliations.requests.reject', $request), [
            'rejection_reason' => 'Data tidak sesuai.',
        ])
        ->assertRedirect(route('endmin.affiliations.index'));

    $this->assertDatabaseHas('affiliation_requests', [
        'id' => $request->id,
        'status' => AffiliationRequest::STATUS_REJECTED,
        'rejection_reason' => 'Data tidak sesuai.',
        'reviewed_by' => $superAdmin->id,
    ]);
});

test('super admin can view users in affiliation detail page', function () {
    $superAdmin = User::factory()->create(['is_admin' => User::ADMIN_LEVEL_SUPER_ADMIN]);
    $regularUser = User::factory()->create([
        'name' => 'Mahasiswa Afiliasi',
        'email' => 'mahasiswa@example.test',
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Detail',
        'student_id_type' => 'nim',
        'student_id_number' => '220002',
        'affiliation_status' => 'verified',
    ]);
    $adminUser = User::factory()->create([
        'name' => 'Admin Afiliasi',
        'email' => 'admin-affiliation@example.test',
        'is_admin' => User::ADMIN_LEVEL_ADMIN,
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Detail',
        'student_id_type' => 'nidn',
        'student_id_number' => '990001',
        'affiliation_status' => 'verified',
    ]);

    $this->actingAs($superAdmin)
        ->get(route('endmin.affiliations.extend', [
            'affiliationName' => 'Universitas Detail',
            'type' => 'university',
        ]))
        ->assertOk()
        ->assertSee('Universitas Detail')
        ->assertSee($regularUser->email)
        ->assertSee($adminUser->email)
        ->assertSee('Admin');
});

test('super admin can filter and batch update affiliation verification in detail page', function () {
    $superAdmin = User::factory()->create(['is_admin' => User::ADMIN_LEVEL_SUPER_ADMIN]);
    $pendingUser = User::factory()->create([
        'name' => 'Cari Mahasiswa',
        'email' => 'cari-mahasiswa@example.test',
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Batch',
        'student_id_type' => 'nim',
        'student_id_number' => 'BATCH001',
        'affiliation_status' => 'pending',
    ]);
    $otherUser = User::factory()->create([
        'name' => 'User Lain',
        'email' => 'lain@example.test',
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Batch',
        'student_id_type' => 'nim',
        'student_id_number' => 'BATCH002',
        'affiliation_status' => 'pending',
    ]);

    $this->actingAs($superAdmin)
        ->get(route('endmin.affiliations.extend', [
            'affiliationName' => 'Universitas Batch',
            'type' => 'university',
            'q' => 'BATCH001',
        ]))
        ->assertOk()
        ->assertSee($pendingUser->email)
        ->assertDontSee($otherUser->email);

    $this->actingAs($superAdmin)
        ->post(route('endmin.affiliations.extend.batch', [
            'affiliationName' => 'Universitas Batch',
            'type' => 'university',
        ]), [
            'action' => 'verify',
            'user_ids' => [$pendingUser->id],
        ])
        ->assertRedirect(route('endmin.affiliations.extend', [
            'affiliationName' => 'Universitas Batch',
            'type' => 'university',
        ]));

    $this->assertDatabaseHas('users', [
        'id' => $pendingUser->id,
        'affiliation_status' => 'verified',
        'affiliation_verified_by' => $superAdmin->id,
    ]);

    $this->actingAs($superAdmin)
        ->post(route('endmin.affiliations.extend.batch', [
            'affiliationName' => 'Universitas Batch',
            'type' => 'university',
        ]), [
            'action' => 'unverify',
            'user_ids' => [$pendingUser->id],
        ])
        ->assertRedirect(route('endmin.affiliations.extend', [
            'affiliationName' => 'Universitas Batch',
            'type' => 'university',
        ]));

    $this->assertDatabaseHas('users', [
        'id' => $pendingUser->id,
        'affiliation_status' => 'pending',
        'affiliation_verified_at' => null,
        'affiliation_verified_by' => null,
    ]);
});
