<?php

namespace Tests\Feature\Ai;

use App\Models\Reminder;
use App\Models\Todolist;
use App\Models\User;
use App\Services\Ai\Exceptions\AiActionException;
use App\Services\Ai\ReminderRecordResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderRecordResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_targets_with_reminders_are_ambiguous(): void
    {
        $user = User::factory()->create();
        $this->reminder($user, '2026-10-02 10:00:00');
        $this->reminder($user, '2026-10-02 11:00:00');
        $this->expectException(AiActionException::class);
        app(ReminderRecordResolver::class)->resolve($user, 'todolist', 'Beli buku');
    }

    public function test_explicit_time_disambiguates_duplicate_targets_without_selecting_the_first(): void
    {
        $user = User::factory()->create();
        $this->reminder($user, '2026-10-02 10:00:00');
        $second = $this->reminder($user, '2026-10-02 11:00:00');
        $resolved = app(ReminderRecordResolver::class)->resolve($user, 'todolist', 'Beli buku', '2026-10-02T11:00:00+07:00');
        $this->assertSame($second->id, $resolved->id);
    }

    public function test_same_name_without_an_eligible_owned_reminder_is_excluded(): void
    {
        $user = User::factory()->create();
        Todolist::create(['user_id' => $user->id, 'nama_item' => 'Beli buku', 'status' => false]);
        $expected = $this->reminder($user, '2026-10-02 10:00:00');
        $this->reminder(User::factory()->create(), '2026-10-02 10:00:00');
        $this->assertSame($expected->id, app(ReminderRecordResolver::class)->resolve($user, 'todolist', 'Beli buku')->id);
    }

    public function test_multiple_reminders_on_one_target_still_require_a_time(): void
    {
        $user = User::factory()->create();
        $first = $this->reminder($user, '2026-10-02 10:00:00');
        Reminder::create(['user_id' => $user->id, 'todolist_id' => $first->todolist_id, 'waktu_reminder' => '2026-10-02 11:00:00', 'aktif' => true]);
        $this->expectException(AiActionException::class);
        app(ReminderRecordResolver::class)->resolve($user, 'todolist', 'Beli buku');
    }

    private function reminder(User $user, string $time): Reminder
    {
        $todo = Todolist::create(['user_id' => $user->id, 'nama_item' => 'Beli buku', 'status' => false]);

        return Reminder::create(['user_id' => $user->id, 'todolist_id' => $todo->id, 'waktu_reminder' => $time, 'aktif' => true]);
    }
}
