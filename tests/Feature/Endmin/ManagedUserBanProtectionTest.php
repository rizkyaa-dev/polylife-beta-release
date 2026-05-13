<?php

use App\Models\AdminAssignment;
use App\Models\AffiliationTemplate;
use App\Models\User;

test('super admin cannot ban the currently authenticated account', function () {
    $superAdmin = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_SUPER_ADMIN,
        'role' => 'super_admin',
        'account_status' => 'active',
    ]);

    $this->actingAs($superAdmin)
        ->from(route('endmin.users.edit', $superAdmin))
        ->put(route('endmin.users.update', $superAdmin), [
            'email' => $superAdmin->email,
            'account_status' => 'banned',
            'ban_reason_code' => 'self_ban',
            'ban_reason_text' => 'Tidak sengaja.',
        ])
        ->assertRedirect(route('endmin.users.edit', $superAdmin))
        ->assertSessionHasErrors('account_status');

    expect($superAdmin->refresh()->account_status)->toBe('active');
});

test('super admin cannot ban another super admin account', function () {
    $actor = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_SUPER_ADMIN,
        'role' => 'super_admin',
        'account_status' => 'active',
    ]);
    $target = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_SUPER_ADMIN,
        'role' => 'super_admin',
        'account_status' => 'active',
    ]);

    $this->actingAs($actor)
        ->from(route('endmin.users.edit', $target))
        ->put(route('endmin.users.update', $target), [
            'email' => $target->email,
            'account_status' => 'banned',
            'ban_reason_code' => 'peer_ban',
            'ban_reason_text' => 'Tidak diizinkan.',
        ])
        ->assertRedirect(route('endmin.users.edit', $target))
        ->assertSessionHasErrors('account_status');

    expect($target->refresh()->account_status)->toBe('active');
});

test('super admin can bind managed user to affiliation template from edit page', function () {
    $actor = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_SUPER_ADMIN,
        'role' => 'super_admin',
        'account_status' => 'active',
    ]);
    $template = AffiliationTemplate::query()->create([
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Managed',
        'aliases' => [],
        'is_active' => true,
        'created_by' => $actor->id,
    ]);
    $target = User::factory()->create([
        'name' => 'User Managed',
        'account_status' => 'active',
        'affiliation_status' => 'pending',
    ]);

    $this->actingAs($actor)
        ->put(route('endmin.users.update', $target), [
            'name' => 'User Managed Updated',
            'email' => $target->email,
            'email_verified' => '1',
            'account_status' => 'active',
            'affiliation_template_id' => $template->id,
            'affiliation_status' => 'verified',
            'student_id_type' => 'nim',
            'student_id_number' => '240001',
        ])
        ->assertRedirect(route('endmin.users.index'));

    $this->assertDatabaseHas('users', [
        'id' => $target->id,
        'name' => 'User Managed Updated',
        'affiliation_template_id' => $template->id,
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Managed',
        'affiliation_status' => 'verified',
        'affiliation_verified_by' => $actor->id,
        'student_id_type' => 'nim',
        'student_id_number' => '240001',
    ]);
});

test('managed admin affiliation update refreshes active assignment', function () {
    $actor = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_SUPER_ADMIN,
        'role' => 'super_admin',
        'account_status' => 'active',
    ]);
    $oldTemplate = AffiliationTemplate::query()->create([
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Lama Admin',
        'aliases' => [],
        'is_active' => true,
        'created_by' => $actor->id,
    ]);
    $newTemplate = AffiliationTemplate::query()->create([
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Baru Admin',
        'aliases' => [],
        'is_active' => true,
        'created_by' => $actor->id,
    ]);
    $admin = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_ADMIN,
        'role' => 'admin',
        'account_status' => 'active',
        'affiliation_template_id' => $oldTemplate->id,
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Lama Admin',
        'affiliation_status' => 'verified',
        'affiliation_verified_at' => now(),
    ]);
    AdminAssignment::query()->create([
        'user_id' => $admin->id,
        'affiliation_template_id' => $oldTemplate->id,
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Lama Admin',
        'status' => 'active',
        'contact_email' => $admin->email,
        'assigned_by' => $actor->id,
        'assigned_at' => now(),
    ]);

    $this->actingAs($actor)
        ->put(route('endmin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'email_verified' => '1',
            'account_status' => 'active',
            'affiliation_template_id' => $newTemplate->id,
            'affiliation_status' => 'verified',
            'student_id_type' => 'nidn',
            'student_id_number' => 'ADM001',
        ])
        ->assertRedirect(route('endmin.users.index'));

    $this->assertDatabaseHas('admin_assignments', [
        'user_id' => $admin->id,
        'affiliation_template_id' => $oldTemplate->id,
        'status' => 'revoked',
    ]);
    $this->assertDatabaseHas('admin_assignments', [
        'user_id' => $admin->id,
        'affiliation_template_id' => $newTemplate->id,
        'affiliation_name' => 'Universitas Baru Admin',
        'status' => 'active',
    ]);
});
