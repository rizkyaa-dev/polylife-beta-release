<?php

use App\Services\Reminder\GuestReminderFeedService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->guestWorkspacePath = storage_path('app/guest/workspace.json');
    $this->originalGuestWorkspace = File::exists($this->guestWorkspacePath)
        ? File::get($this->guestWorkspacePath)
        : null;

    File::ensureDirectoryExists(dirname($this->guestWorkspacePath));
});

afterEach(function () {
    if ($this->originalGuestWorkspace === null) {
        File::delete($this->guestWorkspacePath);

        return;
    }

    File::put($this->guestWorkspacePath, $this->originalGuestWorkspace);
});

test('guest reminder feed service filters sorts and limits upcoming reminders', function () {
    File::put($this->guestWorkspacePath, json_encode([
        'todolist' => [
            [
                'id' => 1,
                'nama_item' => 'Sudah lewat',
                'reminders' => [
                    ['id' => 11, 'waktu_reminder' => '2026-04-09 09:00:00', 'aktif' => true],
                ],
            ],
            [
                'id' => 2,
                'nama_item' => 'Nonaktif',
                'reminders' => [
                    ['id' => 12, 'waktu_reminder' => '2026-04-09 11:00:00', 'aktif' => false],
                ],
            ],
            [
                'id' => 3,
                'nama_item' => 'Jatuh tempo sekarang',
                'reminders' => [
                    ['id' => 13, 'waktu_reminder' => '2026-04-09 10:00:00', 'aktif' => true],
                ],
            ],
            [
                'id' => 4,
                'nama_item' => 'Lima belas menit lagi',
                'reminders' => [
                    ['id' => 14, 'waktu_reminder' => '2026-04-09 10:15:00', 'aktif' => true],
                ],
            ],
            [
                'id' => 5,
                'nama_item' => 'Dua jam lagi',
                'reminders' => [
                    ['id' => 15, 'waktu_reminder' => '2026-04-09 12:00:00', 'aktif' => true],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR));

    $payload = app(GuestReminderFeedService::class)->build(
        Carbon::create(2026, 4, 9, 10, 0, 0, 'Asia/Jakarta'),
        2
    );

    expect($payload)->toHaveCount(2);
    expect($payload[0]['title'])->toBe('Jatuh tempo sekarang');
    expect($payload[0]['seconds_left'])->toBe(0);
    expect($payload[0]['time_left_text'])->toBe('Segera jatuh tempo');
    expect($payload[0]['edit_url'])->toBe('#');
    expect($payload[1]['title'])->toBe('Lima belas menit lagi');
    expect($payload[1]['seconds_left'])->toBe(900);
    expect($payload[1]['time_left_text'])->toBe('Sisa 15 menit');
});
