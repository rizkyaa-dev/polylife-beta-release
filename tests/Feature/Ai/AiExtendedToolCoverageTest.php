<?php

namespace Tests\Feature\Ai;

use App\Models\AffiliationBroadcast;
use App\Models\Catatan;
use App\Models\Jadwal;
use App\Models\Kegiatan;
use App\Models\Keuangan;
use App\Models\Matkul;
use App\Models\Reminder;
use App\Models\Todolist;
use App\Models\Tugas;
use App\Models\User;
use App\Services\Ai\Actions\AiWriteActionRegistry;
use App\Services\Ai\AiToolRegistry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiExtendedToolCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-16 10:00:00', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_registry_exposes_every_allowed_extended_tool_with_the_correct_mutation_boundary(): void
    {
        $registry = app(AiToolRegistry::class);
        $readTools = [
            'search_financial_transactions', 'search_schedules', 'search_tasks',
            'get_workspace_overview', 'export_workspace_summary', 'suggest_budget', 'suggest_study_plan',
        ];
        $writeTools = [
            'update_jadwal', 'update_kegiatan', 'update_reminder', 'update_keuangan', 'update_course', 'update_tugas',
            'mark_announcement_read', 'duplicate_schedule', 'copy_course_to_semester', 'archive_note', 'restore_note',
        ];

        foreach ($readTools as $name) {
            $this->assertNotNull($registry->find($name), $name);
            $this->assertFalse($registry->find($name)->isMutating(), $name);
        }
        foreach ($writeTools as $name) {
            $this->assertNotNull($registry->find($name), $name);
            $this->assertTrue($registry->find($name)->isMutating(), $name);
        }
    }

    public function test_update_tools_use_owned_records_and_canonical_actions(): void
    {
        [$user, $fixtures] = $this->workspaceFixtures();
        $tools = app(AiToolRegistry::class);
        $actions = app(AiWriteActionRegistry::class);

        $cases = [
            ['update_jadwal', ['target' => 'Proyek Lama', 'location' => 'Ruang Baru'], fn () => $fixtures['schedule']->fresh()->location === 'Ruang Baru'],
            ['update_kegiatan', ['target' => 'Riset Awal', 'lokasi' => 'Lab Baru'], fn () => $fixtures['activity']->fresh()->lokasi === 'Lab Baru'],
            ['update_reminder', ['target_type' => 'todolist', 'target_query' => 'Baca Modul', 'new_time' => '2026-09-18 09:00'], fn () => $fixtures['reminder']->fresh()->getRawOriginal('waktu_reminder') === '2026-09-18 09:00:00'],
            ['update_keuangan', ['transaction_id' => $fixtures['finance']->id, 'nominal' => 42000], fn () => (float) $fixtures['finance']->fresh()->nominal === 42000.0],
            ['update_course', ['target' => 'IF101', 'dosen' => 'Dr. Baru'], fn () => $fixtures['course']->fresh()->dosen === 'Dr. Baru'],
            ['update_tugas', ['target' => 'Tugas Lama', 'deadline' => '2026-09-20 21:00'], fn () => $fixtures['task']->fresh()->deadline->format('Y-m-d H:i') === '2026-09-20 21:00'],
        ];

        foreach ($cases as [$toolName, $arguments, $assertion]) {
            $proposal = $tools->find($toolName)->execute($user, $arguments);
            $this->assertSame('proposal_created', $proposal['status'], $toolName);
            $this->assertFalse($assertion(), "{$toolName} mutated before confirmation");
            $actions->execute($toolName, $user, $proposal['payload']);
            $this->assertTrue($assertion(), $toolName);
        }
    }

    public function test_duplicate_copy_archive_restore_and_mark_read_actions_preserve_invariants(): void
    {
        [$user, $fixtures] = $this->workspaceFixtures();
        $tools = app(AiToolRegistry::class);
        $actions = app(AiWriteActionRegistry::class);

        $duplicate = $tools->find('duplicate_schedule')->execute($user, [
            'target' => 'Proyek Lama', 'tanggal_mulai' => '2026-10-01', 'tanggal_selesai' => '2026-10-03',
        ]);
        /** @var Jadwal $copiedSchedule */
        $copiedSchedule = $actions->execute('duplicate_schedule', $user, $duplicate['payload']);
        $this->assertSame('2026-10-01', $copiedSchedule->tanggal_mulai->toDateString());
        $this->assertCount(1, $copiedSchedule->kegiatans);
        $this->assertDatabaseMissing('reminders', ['user_id' => $user->id, 'jadwal_id' => $copiedSchedule->id]);

        $copyCourse = $tools->find('copy_course_to_semester')->execute($user, [
            'target' => 'IF101', 'semester' => 6,
        ]);
        /** @var Matkul $copiedCourse */
        $copiedCourse = $actions->execute('copy_course_to_semester', $user, $copyCourse['payload']);
        $this->assertSame('IF101-S6', $copiedCourse->kode);
        $this->assertSame(6, $copiedCourse->semester);

        $archive = $tools->find('archive_note')->execute($user, ['target' => 'Catatan Aktif']);
        $actions->execute('archive_note', $user, $archive['payload']);
        $this->assertTrue($fixtures['note']->fresh()->status_sampah);
        $restore = $tools->find('restore_note')->execute($user, ['target' => 'Catatan Aktif']);
        $actions->execute('restore_note', $user, $restore['payload']);
        $this->assertFalse($fixtures['note']->fresh()->status_sampah);

        $markRead = $tools->find('mark_announcement_read')->execute($user, ['target' => 'Info Akademik']);
        $actions->execute('mark_announcement_read', $user, $markRead['payload']);
        $this->assertDatabaseHas('affiliation_broadcast_reads', [
            'user_id' => $user->id, 'broadcast_id' => $fixtures['announcement']->id,
        ]);
    }

    public function test_search_overview_export_and_suggestions_are_tenant_scoped_and_read_only(): void
    {
        [$user, $fixtures] = $this->workspaceFixtures();
        $other = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        Keuangan::create(['user_id' => $other->id, 'jenis' => 'pengeluaran', 'kategori' => 'SECRET', 'deskripsi' => 'TENANT-SECRET', 'nominal' => 999999, 'tanggal' => '2026-09-10']);
        Jadwal::create(['user_id' => $other->id, 'jenis' => 'lainnya', 'title' => 'TENANT-SECRET', 'tanggal_mulai' => '2026-09-16', 'tanggal_selesai' => '2026-09-16']);
        Tugas::create(['user_id' => $other->id, 'nama_tugas' => 'TENANT-SECRET', 'deadline' => '2026-09-20 10:00', 'status_selesai' => false]);

        $tools = app(AiToolRegistry::class);
        $beforeCounts = [Keuangan::count(), Jadwal::count(), Tugas::count(), Reminder::count()];
        $transactions = $tools->find('search_financial_transactions')->execute($user, ['query' => 'Makan']);
        $schedules = $tools->find('search_schedules')->execute($user, ['query' => 'Proyek']);
        $tasks = $tools->find('search_tasks')->execute($user, ['query' => 'Lama']);
        $overview = $tools->find('get_workspace_overview')->execute($user, ['range_days' => 7]);
        $export = $tools->find('export_workspace_summary')->execute($user, ['range_days' => 7]);
        $budget = $tools->find('suggest_budget')->execute($user, ['lookback_months' => 3]);
        $study = $tools->find('suggest_study_plan')->execute($user, ['range_days' => 7]);

        $this->assertSame($fixtures['finance']->id, $transactions['transactions'][0]['id']);
        $this->assertSame($fixtures['schedule']->id, $schedules['schedules'][0]['id']);
        $this->assertSame($fixtures['task']->id, $tasks['tasks'][0]['id']);
        $this->assertArrayHasKey('finance', $overview);
        $this->assertSame('markdown', $export['format']);
        $this->assertStringContainsString('# Ringkasan PolyLife', $export['content']);
        $this->assertTrue($budget['has_data']);
        $this->assertNotEmpty($study['prioritized_tasks']);
        $serialized = json_encode([$transactions, $schedules, $tasks, $overview, $export, $budget, $study]);
        $this->assertStringNotContainsString('TENANT-SECRET', $serialized);
        $this->assertSame($beforeCounts, [Keuangan::count(), Jadwal::count(), Tugas::count(), Reminder::count()]);
    }

    /** @return array{User, array<string, mixed>} */
    private function workspaceFixtures(): array
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        $course = Matkul::create([
            'user_id' => $user->id, 'kode' => 'IF101', 'nama' => 'Algoritma', 'kelas' => 'A;', 'dosen' => 'Dr. Lama',
            'semester' => 5, 'sks' => 3, 'hari' => 'senin;', 'jam_mulai' => '10:00;', 'jam_selesai' => '12:00;',
            'ruangan' => 'R101;', 'warna_label' => '#2563eb', 'catatan' => '',
        ]);
        $schedule = Jadwal::create([
            'user_id' => $user->id, 'jenis' => 'lainnya', 'title' => 'Proyek Lama',
            'tanggal_mulai' => '2026-09-16', 'tanggal_selesai' => '2026-09-18',
            'start_time' => '13:00:00', 'end_time' => '15:00:00', 'location' => 'Lab Lama',
            'matkul_id_list' => (string) $course->id, 'is_completed' => false,
        ]);
        $activity = Kegiatan::create([
            'jadwal_id' => $schedule->id, 'nama_kegiatan' => 'Riset Awal', 'lokasi' => 'Lab Lama',
            'tanggal_deadline' => '2026-09-17', 'waktu' => '14:00:00', 'status' => 'belum_dimulai',
        ]);
        $todo = Todolist::create(['user_id' => $user->id, 'nama_item' => 'Baca Modul', 'status' => false]);
        $reminder = Reminder::create(['user_id' => $user->id, 'todolist_id' => $todo->id, 'waktu_reminder' => '2026-09-17 18:30:00', 'aktif' => true]);
        $task = Tugas::create(['user_id' => $user->id, 'matkul_id' => $course->id, 'nama_tugas' => 'Tugas Lama', 'deskripsi' => 'Draft', 'deadline' => '2026-09-19 20:00', 'status_selesai' => false]);
        $finance = Keuangan::create(['user_id' => $user->id, 'jenis' => 'pengeluaran', 'kategori' => 'Makanan', 'deskripsi' => 'Makan siang', 'nominal' => 30000, 'tanggal' => '2026-09-15']);
        Keuangan::create(['user_id' => $user->id, 'jenis' => 'pengeluaran', 'kategori' => 'Makanan', 'deskripsi' => 'Historis', 'nominal' => 90000, 'tanggal' => '2026-08-15']);
        $note = Catatan::create(['user_id' => $user->id, 'judul' => 'Catatan Aktif', 'isi' => 'Isi', 'tanggal' => '2026-09-16', 'show_preview' => true, 'status_sampah' => false]);
        $announcement = AffiliationBroadcast::create([
            'created_by' => $user->id, 'title' => 'Info Akademik', 'body' => 'Isi pengumuman',
            'target_mode' => AffiliationBroadcast::TARGET_MODE_GLOBAL, 'send_push' => false,
            'status' => AffiliationBroadcast::STATUS_PUBLISHED, 'published_at' => now()->subHour(),
        ]);

        return [$user, compact('course', 'schedule', 'activity', 'todo', 'reminder', 'task', 'finance', 'note', 'announcement')];
    }
}
