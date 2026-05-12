<?php

use App\Actions\Broadcast\MarkPengumumanReadAction;
use App\Models\AffiliationBroadcast;
use App\Models\User;

function createVisiblePengumumanFor(User $user, array $attributes = []): AffiliationBroadcast
{
    $admin = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_ADMIN,
        'role' => 'admin',
        'affiliation_type' => $user->affiliation_type,
        'affiliation_name' => $user->affiliation_name,
        'affiliation_status' => 'verified',
    ]);

    $broadcast = AffiliationBroadcast::query()->create(array_merge([
        'created_by' => $admin->id,
        'title' => 'Pengumuman Read State',
        'body' => 'Isi pengumuman.',
        'target_mode' => AffiliationBroadcast::TARGET_MODE_AFFILIATION,
        'status' => AffiliationBroadcast::STATUS_PUBLISHED,
        'published_at' => now(),
    ], $attributes));

    $broadcast->targets()->create([
        'affiliation_type' => $user->affiliation_type,
        'affiliation_name' => $user->affiliation_name,
    ]);

    return $broadcast;
}

test('pengumuman read endpoint only marks visible broadcasts and returns unread count', function () {
    $user = User::factory()->create([
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Read',
        'affiliation_status' => 'verified',
    ]);
    $otherUser = User::factory()->create([
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Lain',
        'affiliation_status' => 'verified',
    ]);

    $visible = createVisiblePengumumanFor($user, ['title' => 'Visible One']);
    createVisiblePengumumanFor($user, ['title' => 'Visible Two']);
    $notVisible = createVisiblePengumumanFor($otherUser, ['title' => 'Other Affiliation']);

    $this->actingAs($user)
        ->postJson(route('pengumuman.read'), [
            'broadcast_ids' => [$visible->id, $notVisible->id],
        ])
        ->assertOk()
        ->assertJson([
            'read_count' => 1,
            'unread_count' => 1,
        ]);

    $this->assertDatabaseHas('affiliation_broadcast_reads', [
        'user_id' => $user->id,
        'broadcast_id' => $visible->id,
    ]);
    $this->assertDatabaseMissing('affiliation_broadcast_reads', [
        'user_id' => $user->id,
        'broadcast_id' => $notVisible->id,
    ]);
});

test('pengumuman detail marks current broadcast as read', function () {
    $user = User::factory()->create([
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Detail',
        'affiliation_status' => 'verified',
    ]);
    $broadcast = createVisiblePengumumanFor($user);

    $this->actingAs($user)
        ->get(route('pengumuman.show', $broadcast))
        ->assertOk();

    $this->assertDatabaseHas('affiliation_broadcast_reads', [
        'user_id' => $user->id,
        'broadcast_id' => $broadcast->id,
    ]);

    expect(app(MarkPengumumanReadAction::class)->unreadCountForUser($user))->toBe(0);
});

test('pengumuman sidebar badge uses persistent read state', function () {
    $user = User::factory()->create([
        'affiliation_type' => 'university',
        'affiliation_name' => 'Universitas Sidebar',
        'affiliation_status' => 'verified',
    ]);
    $first = createVisiblePengumumanFor($user, ['title' => 'First Sidebar']);
    $second = createVisiblePengumumanFor($user, ['title' => 'Second Sidebar']);

    $response = $this->actingAs($user)
        ->get(route('pengumuman.index'))
        ->assertOk();

    expect(preg_match('/<span[^>]+data-announcement-badge/s', $response->getContent()))->toBe(1);

    app(MarkPengumumanReadAction::class)($user, [$first->id, $second->id]);

    $response = $this->actingAs($user)
        ->get(route('pengumuman.index'))
        ->assertOk();

    expect(preg_match('/<span[^>]+data-announcement-badge/s', $response->getContent()))->toBe(0);
});
