<?php

use App\Models\AffiliationBroadcast;
use App\Models\User;

test('pengumuman feed renders creator profile avatar when visible to user', function () {
    $admin = User::factory()->create([
        'name' => 'Admin Avatar',
        'is_admin' => User::ADMIN_LEVEL_ADMIN,
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Avatar',
        'affiliation_status' => 'verified',
    ]);
    $admin->profileAvatar()->create([
        'image' => 'avatar-binary',
        'mime_type' => 'image/webp',
        'width' => 64,
        'height' => 64,
        'size' => 13,
    ]);

    $user = User::factory()->create([
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Avatar',
        'affiliation_status' => 'verified',
    ]);

    $broadcast = AffiliationBroadcast::query()->create([
        'created_by' => $admin->id,
        'title' => 'Pengumuman Avatar',
        'body' => 'Isi pengumuman.',
        'target_mode' => AffiliationBroadcast::TARGET_MODE_AFFILIATION,
        'status' => AffiliationBroadcast::STATUS_PUBLISHED,
        'published_at' => now(),
    ]);
    $broadcast->targets()->create([
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Avatar',
    ]);

    $this->actingAs($user)
        ->get(route('pengumuman.index'))
        ->assertOk()
        ->assertSee(route('pengumuman.creator-avatar', $admin, false), false)
        ->assertSee('Foto profil Admin Avatar', false);
});

test('pengumuman creator avatar can only be viewed by users who can see creator broadcast', function () {
    $admin = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_ADMIN,
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Avatar',
        'affiliation_status' => 'verified',
    ]);
    $admin->profileAvatar()->create([
        'image' => 'avatar-binary',
        'mime_type' => 'image/webp',
        'width' => 64,
        'height' => 64,
        'size' => 13,
    ]);

    $visibleUser = User::factory()->create([
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Avatar',
        'affiliation_status' => 'verified',
    ]);
    $otherUser = User::factory()->create([
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Lain',
        'affiliation_status' => 'verified',
    ]);

    $broadcast = AffiliationBroadcast::query()->create([
        'created_by' => $admin->id,
        'title' => 'Pengumuman Avatar',
        'body' => 'Isi pengumuman.',
        'target_mode' => AffiliationBroadcast::TARGET_MODE_AFFILIATION,
        'status' => AffiliationBroadcast::STATUS_PUBLISHED,
        'published_at' => now(),
    ]);
    $broadcast->targets()->create([
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Avatar',
    ]);

    $this->actingAs($visibleUser)
        ->get(route('pengumuman.creator-avatar', $admin))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp')
        ->assertSee('avatar-binary', false);

    $this->actingAs($otherUser)
        ->get(route('pengumuman.creator-avatar', $admin))
        ->assertNotFound();
});
