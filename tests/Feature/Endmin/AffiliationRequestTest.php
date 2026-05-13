<?php

use App\Models\AffiliationRequest;
use App\Models\AffiliationBroadcast;
use App\Models\AffiliationTemplate;
use App\Models\AdminAssignment;
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

test('super admin can override affiliation type before approving request', function () {
    $superAdmin = User::factory()->create(['is_admin' => User::ADMIN_LEVEL_SUPER_ADMIN]);
    $user = User::factory()->create(['affiliation_status' => 'pending']);
    $request = AffiliationRequest::query()->create([
        'user_id' => $user->id,
        'affiliation_type' => 'university',
        'affiliation_name' => 'Politeknik Negeri Indramayu',
        'student_id_type' => 'nim',
        'student_id_number' => '230001',
        'status' => AffiliationRequest::STATUS_PENDING,
    ]);

    $this->actingAs($superAdmin)
        ->get(route('endmin.affiliations.index'))
        ->assertOk()
        ->assertSee('Tipe diajukan: Universitas')
        ->assertSee('name="canonical_affiliation_type"', false)
        ->assertSee('Politeknik');

    $this->actingAs($superAdmin)
        ->patch(route('endmin.affiliations.requests.approve', $request), [
            'canonical_affiliation_type' => 'polytechnic',
            'canonical_affiliation_name' => 'Politeknik Negeri Indramayu',
        ])
        ->assertRedirect(route('endmin.affiliations.index'));

    $this->assertDatabaseHas('affiliation_templates', [
        'affiliation_type' => 'polytechnic',
        'affiliation_name' => 'Politeknik Negeri Indramayu',
        'is_active' => true,
    ]);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'affiliation_type' => 'polytechnic',
        'affiliation_name' => 'Politeknik Negeri Indramayu',
        'student_id_number' => '230001',
        'affiliation_status' => 'verified',
    ]);

    $this->assertDatabaseHas('affiliation_requests', [
        'id' => $request->id,
        'affiliation_type' => 'polytechnic',
        'affiliation_name' => 'Politeknik Negeri Indramayu',
        'status' => AffiliationRequest::STATUS_APPROVED,
    ]);
});

test('affiliation suggestions avoid conflicting institution type keywords', function () {
    $superAdmin = User::factory()->create(['is_admin' => User::ADMIN_LEVEL_SUPER_ADMIN]);
    AffiliationTemplate::query()->create([
        'affiliation_type' => 'university',
        'affiliation_name' => 'Politeknik Negeri Semarang',
        'aliases' => [],
        'is_active' => true,
        'created_by' => $superAdmin->id,
    ]);
    AffiliationRequest::query()->create([
        'user_id' => User::factory()->create()->id,
        'affiliation_type' => 'university',
        'affiliation_name' => 'Politeknik Negeri Indramayu',
        'student_id_type' => 'nim',
        'student_id_number' => '230002',
        'status' => AffiliationRequest::STATUS_PENDING,
    ]);

    $this->actingAs($superAdmin)
        ->get(route('endmin.affiliations.index'))
        ->assertOk()
        ->assertDontSee('Saran: Universitas - Politeknik Negeri Semarang');
});

test('approving admin affiliation request creates active admin assignment', function () {
    $superAdmin = User::factory()->create(['is_admin' => User::ADMIN_LEVEL_SUPER_ADMIN]);
    $admin = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_ADMIN,
        'account_status' => 'active',
        'affiliation_status' => 'pending',
    ]);
    $request = AffiliationRequest::query()->create([
        'user_id' => $admin->id,
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Admin Approve',
        'student_id_type' => 'nidn',
        'student_id_number' => 'ADM-1',
        'status' => AffiliationRequest::STATUS_PENDING,
    ]);

    $this->actingAs($superAdmin)
        ->patch(route('endmin.affiliations.requests.approve', $request), [
            'canonical_affiliation_name' => 'Universitas Admin Approve',
        ])
        ->assertRedirect(route('endmin.affiliations.index'));

    $admin->refresh();

    $this->assertDatabaseHas('admin_assignments', [
        'user_id' => $admin->id,
        'affiliation_template_id' => $admin->affiliation_template_id,
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Admin Approve',
        'status' => 'active',
        'assigned_by' => $superAdmin->id,
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

test('quick verification cannot mark affiliation verified without affiliation data', function () {
    $superAdmin = User::factory()->create(['is_admin' => User::ADMIN_LEVEL_SUPER_ADMIN]);
    $user = User::factory()->create([
        'affiliation_template_id' => null,
        'affiliation_type' => null,
        'affiliation_name' => null,
        'affiliation_status' => 'pending',
    ]);

    $this->actingAs($superAdmin)
        ->from(route('endmin.verifications.index'))
        ->patch(route('endmin.verifications.update', $user), [
            'email_verified' => true,
            'affiliation_status' => 'verified',
        ])
        ->assertRedirect(route('endmin.verifications.index'))
        ->assertSessionHasErrors('affiliation_status');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'affiliation_status' => 'pending',
        'affiliation_verified_at' => null,
        'affiliation_verified_by' => null,
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

test('super admin can create affiliation template manually', function () {
    $superAdmin = User::factory()->create(['is_admin' => User::ADMIN_LEVEL_SUPER_ADMIN]);

    $this->actingAs($superAdmin)
        ->post(route('endmin.affiliations.manage.store'), [
            'affiliation_type' => 'university',
            'affiliation_name' => 'Universitas Manual',
            'aliases_text' => "UM\nUniv Manual",
            'is_active' => '1',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('affiliation_templates', [
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Manual',
        'is_active' => true,
        'created_by' => $superAdmin->id,
    ]);

    $this->actingAs($superAdmin)
        ->get(route('endmin.affiliations.manage.index'))
        ->assertOk()
        ->assertSee('Universitas Manual');
});

test('affiliation template form exposes detailed type options', function () {
    $superAdmin = User::factory()->create(['is_admin' => User::ADMIN_LEVEL_SUPER_ADMIN]);
    $template = AffiliationTemplate::query()->create([
        'affiliation_type' => 'polytechnic',
        'affiliation_name' => 'Politeknik Manual',
        'aliases' => [],
        'is_active' => true,
        'created_by' => $superAdmin->id,
    ]);

    $this->actingAs($superAdmin)
        ->get(route('endmin.affiliations.manage.edit', $template))
        ->assertOk()
        ->assertSee('- Pilih tipe -')
        ->assertSee('Sekolah')
        ->assertSee('Universitas')
        ->assertSee('Institut')
        ->assertSee('Politeknik')
        ->assertSee('Akademi')
        ->assertSee('Organisasi')
        ->assertSee('Perusahaan')
        ->assertSee('Yayasan')
        ->assertSee('Lainnya')
        ->assertSee('value="polytechnic" selected', false);
});

test('editing affiliation template syncs linked runtime records', function () {
    $superAdmin = User::factory()->create(['is_admin' => User::ADMIN_LEVEL_SUPER_ADMIN]);
    $template = AffiliationTemplate::query()->create([
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Lama',
        'aliases' => [],
        'is_active' => true,
        'created_by' => $superAdmin->id,
    ]);
    $user = User::factory()->create([
        'affiliation_template_id' => $template->id,
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Lama',
        'affiliation_status' => 'verified',
    ]);
    AdminAssignment::query()->create([
        'user_id' => $user->id,
        'affiliation_template_id' => $template->id,
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Lama',
        'status' => 'active',
        'contact_email' => $user->email,
    ]);
    $broadcast = AffiliationBroadcast::query()->create([
        'created_by' => $superAdmin->id,
        'title' => 'Info',
        'body' => 'Isi',
        'target_mode' => AffiliationBroadcast::TARGET_MODE_AFFILIATION,
        'send_push' => false,
        'status' => AffiliationBroadcast::STATUS_DRAFT,
    ]);
    $broadcast->targets()->create([
        'affiliation_template_id' => $template->id,
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Lama',
    ]);

    $this->actingAs($superAdmin)
        ->put(route('endmin.affiliations.manage.update', $template), [
            'affiliation_type' => 'university',
            'affiliation_name' => 'Universitas Baru',
            'aliases_text' => '',
            'is_active' => '1',
        ])
        ->assertRedirect(route('endmin.affiliations.manage.edit', $template));

    foreach (['users', 'admin_assignments', 'affiliation_broadcast_targets'] as $table) {
        $this->assertDatabaseHas($table, [
            'affiliation_template_id' => $template->id,
            'affiliation_type' => 'university',
            'affiliation_name' => 'Universitas Baru',
        ]);
    }
});
