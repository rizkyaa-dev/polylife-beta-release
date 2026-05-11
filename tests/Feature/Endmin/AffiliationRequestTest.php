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
