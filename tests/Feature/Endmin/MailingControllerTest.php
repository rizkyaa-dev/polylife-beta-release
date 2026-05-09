<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;

test('super admin can view mailing page with users', function () {
    $superAdmin = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_SUPER_ADMIN,
        'role' => 'super_admin',
        'account_status' => 'active',
    ]);
    $user = User::factory()->create([
        'name' => 'Mail Target',
        'email' => 'mail-target@example.test',
    ]);

    $this->actingAs($superAdmin)
        ->get(route('endmin.mailing.index'))
        ->assertOk()
        ->assertSee('Mailing')
        ->assertSee('Mail Target')
        ->assertSee($user->email);
});

test('super admin can send verification email template from mailing page', function () {
    Notification::fake();

    $superAdmin = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_SUPER_ADMIN,
        'role' => 'super_admin',
        'account_status' => 'active',
    ]);
    $user = User::factory()->create([
        'email' => 'verify-target@example.test',
        'email_verified_at' => null,
    ]);

    $this->actingAs($superAdmin)
        ->post(route('endmin.mailing.send', $user), [
            'template' => 'verify_email',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    Notification::assertSentTo($user, VerifyEmailNotification::class);

    $this->assertDatabaseHas('endmin_audit_logs', [
        'actor_id' => $superAdmin->id,
        'target_user_id' => $user->id,
        'module' => 'mailing',
        'action' => 'send_verify_email',
    ]);
});

test('super admin can send reset password email template from mailing page', function () {
    Notification::fake();

    $superAdmin = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_SUPER_ADMIN,
        'role' => 'super_admin',
        'account_status' => 'active',
    ]);
    $user = User::factory()->create([
        'email' => 'reset-target@example.test',
    ]);

    $this->actingAs($superAdmin)
        ->post(route('endmin.mailing.send', $user), [
            'template' => 'reset_password',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    Notification::assertSentTo($user, ResetPasswordNotification::class);

    $this->assertDatabaseHas('endmin_audit_logs', [
        'actor_id' => $superAdmin->id,
        'target_user_id' => $user->id,
        'module' => 'mailing',
        'action' => 'send_reset_password',
    ]);
});
