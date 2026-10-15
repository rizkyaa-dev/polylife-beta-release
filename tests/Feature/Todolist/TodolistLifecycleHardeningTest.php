<?php

use App\Models\Reminder;
use App\Models\Todolist;
use App\Models\User;

test('todolist toggle status correctly toggles status, disables/enables reminders, and returns 10 minute metadata', function () {
    $user = User::factory()->create();

    $todolist = Todolist::query()->create([
        'user_id' => $user->id,
        'nama_item' => 'Selesaikan Laporan Akhir',
        'status' => false,
    ]);

    $reminder = Reminder::query()->create([
        'user_id' => $user->id,
        'reminder_target' => 'todolist',
        'todolist_id' => $todolist->id,
        'waktu_reminder' => now()->addDay(),
        'aktif' => true,
    ]);

    expect($reminder->aktif)->toBeTrue();

    // 1. Toggle to completed (status = true)
    $response = $this->actingAs($user)
        ->patchJson(route('todolist.toggle-status', $todolist->id), [
            'status' => true,
        ]);

    $response->assertOk()
        ->assertJson([
            'status' => true,
            'tab' => 'completed',
            'visible_for_seconds' => 600,
        ])
        ->assertJsonStructure(['badge', 'timestamp', 'meta', 'completed_at']);

    $todolist->refresh();
    $reminder->refresh();
    expect($todolist->status)->toBeTrue();
    expect($reminder->aktif)->toBeFalse(); // Linked reminder automatically deactivated!

    // 2. Toggle back to ongoing (status = false)
    $responseBack = $this->actingAs($user)
        ->patchJson(route('todolist.toggle-status', $todolist->id), [
            'status' => false,
        ]);

    $responseBack->assertOk()
        ->assertJson([
            'status' => false,
            'tab' => 'ongoing',
            'visible_for_seconds' => null,
        ]);

    $todolist->refresh();
    $reminder->refresh();
    expect($todolist->status)->toBeFalse();
    expect($reminder->aktif)->toBeTrue(); // Linked reminder automatically reactivated!
});

test('todolist strictly rejects cross tenant toggle, edit, update, and deletion', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $todoA = Todolist::query()->create([
        'user_id' => $userA->id,
        'nama_item' => 'Tugas Rahasia User A',
        'status' => false,
    ]);

    // User B tries to toggle User A's todo item
    $this->actingAs($userB)
        ->patchJson(route('todolist.toggle-status', $todoA->id), [
            'status' => true,
        ])
        ->assertStatus(403);

    // User B tries to view edit page of User A's todo item
    $this->actingAs($userB)
        ->get(route('todolist.edit', $todoA->id))
        ->assertStatus(403);

    // User B tries to update User A's todo item
    $this->actingAs($userB)
        ->put(route('todolist.update', $todoA->id), [
            'nama_item' => 'Diubah Oleh User B',
        ])
        ->assertStatus(403);

    // User B tries to delete User A's todo item
    $this->actingAs($userB)
        ->delete(route('todolist.destroy', $todoA->id))
        ->assertStatus(403);

    // Verify User A's item remains untouched
    $todoA->refresh();
    expect($todoA->nama_item)->toBe('Tugas Rahasia User A');
    expect($todoA->status)->toBeFalse();
});

test('todolist item name validation enforces length boundary', function () {
    $user = User::factory()->create();

    // Empty name should fail
    $this->actingAs($user)
        ->post(route('todolist.store'), [
            'nama_item' => '',
        ])
        ->assertSessionHasErrors(['nama_item']);

    // Name exceeding 150 characters should fail
    $tooLongName = str_repeat('A', 151);
    $this->actingAs($user)
        ->post(route('todolist.store'), [
            'nama_item' => $tooLongName,
        ])
        ->assertSessionHasErrors(['nama_item']);

    // Name with exactly 150 characters should succeed
    $exactBoundaryName = str_repeat('B', 150);
    $this->actingAs($user)
        ->post(route('todolist.store'), [
            'nama_item' => $exactBoundaryName,
        ])
        ->assertRedirect(route('todolist.index'));

    expect(Todolist::where('user_id', $user->id)->where('nama_item', $exactBoundaryName)->exists())->toBeTrue();
});
