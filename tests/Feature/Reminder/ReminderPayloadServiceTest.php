<?php

use App\Models\Jadwal;
use App\Models\Kegiatan;
use App\Models\Reminder;
use App\Models\Todolist;
use App\Models\Tugas;
use App\Models\User;
use App\Services\Reminder\ReminderPayloadService;
use Symfony\Component\HttpKernel\Exception\HttpException;

function expectReminderAbort(callable $callback, int $status, ?string $message = null): void
{
    try {
        $callback();
        test()->fail('Expected HTTP exception was not thrown.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe($status);

        if ($message !== null) {
            expect($exception->getMessage())->toBe($message);
        }
    }
}

test('reminder payload service builds create payload for selected target and normalizes fields', function () {
    $user = User::factory()->create();
    $todo = Todolist::query()->create([
        'user_id' => $user->id,
        'nama_item' => 'Bayar UKT',
        'status' => false,
    ]);

    $payload = app(ReminderPayloadService::class)->build(
        $user->id,
        [
            'reminder_target' => 'todolist',
            'waktu_reminder' => '2026-04-10 09:30:00',
        ],
        [
            'todolist_id' => $todo->id,
            'tugas_id' => 999,
            'aktif' => '1',
            'waktu_reminder' => '2026-04-10 09:30:00',
        ]
    );

    expect($payload['user_id'])->toBe($user->id);
    expect($payload['todolist_id'])->toBe($todo->id);
    expect($payload['tugas_id'])->toBeNull();
    expect($payload['jadwal_id'])->toBeNull();
    expect($payload['kegiatan_id'])->toBeNull();
    expect($payload['aktif'])->toBeTrue();
    expect($payload['waktu_reminder'])->toBe('2026-04-10 09:30:00');
    expect($payload)->not->toHaveKey('reminder_target');
});

test('reminder payload service rejects missing selected target id', function () {
    $service = app(ReminderPayloadService::class);

    expectReminderAbort(
        fn () => $service->build(
            1,
            [
                'reminder_target' => 'tugas',
                'waktu_reminder' => '2026-04-10 09:30:00',
            ],
            [
                'waktu_reminder' => '2026-04-10 09:30:00',
            ]
        ),
        422,
        'Target reminder harus dipilih.'
    );
});

test('reminder payload service rejects selected targets owned by another user', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $tugas = Tugas::query()->create([
        'user_id' => $otherUser->id,
        'nama_tugas' => 'Laporan',
        'deadline' => '2026-04-11 08:00:00',
        'status_selesai' => false,
    ]);

    $service = app(ReminderPayloadService::class);

    expectReminderAbort(
        fn () => $service->build(
            $owner->id,
            [
                'reminder_target' => 'tugas',
                'waktu_reminder' => '2026-04-10 09:30:00',
            ],
            [
                'tugas_id' => $tugas->id,
                'waktu_reminder' => '2026-04-10 09:30:00',
            ]
        ),
        403,
        'Akses target tidak diizinkan.'
    );
});

test('reminder payload service resolves kegiatan ownership through jadwal owner', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $jadwal = Jadwal::query()->create([
        'user_id' => $otherUser->id,
        'jenis' => 'kuliah',
        'tanggal_mulai' => '2026-04-10',
        'tanggal_selesai' => '2026-04-10',
    ]);
    $kegiatan = Kegiatan::query()->create([
        'jadwal_id' => $jadwal->id,
        'nama_kegiatan' => 'Presentasi',
        'waktu' => '09:00:00',
        'tanggal_deadline' => '2026-04-10',
    ]);

    $service = app(ReminderPayloadService::class);

    expectReminderAbort(
        fn () => $service->build(
            $owner->id,
            [
                'reminder_target' => 'kegiatan',
                'waktu_reminder' => '2026-04-10 08:00:00',
            ],
            [
                'kegiatan_id' => $kegiatan->id,
                'waktu_reminder' => '2026-04-10 08:00:00',
            ]
        ),
        403,
        'Akses target tidak diizinkan.'
    );
});

test('reminder payload service aborts when selected target record does not exist', function () {
    $user = User::factory()->create();
    $service = app(ReminderPayloadService::class);

    expectReminderAbort(
        fn () => $service->build(
            $user->id,
            [
                'reminder_target' => 'jadwal',
                'waktu_reminder' => '2026-04-10 09:30:00',
            ],
            [
                'jadwal_id' => 999999,
                'waktu_reminder' => '2026-04-10 09:30:00',
            ]
        ),
        404
    );
});

test('reminder payload service resolves reminder target and enforces reminder ownership', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $service = app(ReminderPayloadService::class);

    expect($service->resolveReminderTarget(new Reminder(['tugas_id' => 10])))->toBe('tugas');
    expect($service->resolveReminderTarget(new Reminder(['jadwal_id' => 10])))->toBe('jadwal');
    expect($service->resolveReminderTarget(new Reminder(['kegiatan_id' => 10])))->toBe('kegiatan');
    expect($service->resolveReminderTarget(new Reminder(['todolist_id' => 10])))->toBe('todolist');

    $reminder = Reminder::query()->create([
        'user_id' => $owner->id,
        'waktu_reminder' => '2026-04-10 09:30:00',
        'aktif' => true,
    ]);

    expectReminderAbort(
        fn () => $service->assertOwnership($reminder, $otherUser->id),
        403,
        'Akses ditolak'
    );
});
