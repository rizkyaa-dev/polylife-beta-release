<?php

namespace Tests\Feature\Ai;

use App\Models\Catatan;
use App\Models\Jadwal;
use App\Models\Keuangan;
use App\Models\Matkul;
use App\Models\Reminder;
use App\Models\Todolist;
use App\Models\Tugas;
use App\Models\User;
use App\Services\Ai\Actions\AiWriteActionRegistry;
use App\Services\Ai\AiToolRegistry;
use App\Services\Ai\AiToolSelectionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiRecommendedToolCoverageTest extends TestCase
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

    public function test_registry_and_domain_selector_expose_the_new_tools_without_sending_every_schema(): void
    {
        $registry = app(AiToolRegistry::class);
        $names = collect($registry->getDeclarations())->pluck('name');
        $this->assertCount(51, $names);
        $this->assertContains('check_schedule_conflicts', $names);
        $this->assertContains('snooze_reminder', $names);
        $this->assertContains('get_course_overview', $names);

        $financeNames = collect(app(AiToolSelectionService::class)->declarations('Periksa anomali pengeluaran saya', []))->pluck('name');
        $this->assertContains('suggest_financial_anomalies', $financeNames);
        $this->assertContains('update_keuangan', $financeNames);
        $this->assertNotContains('create_catatan', $financeNames);
        $this->assertLessThan(51, $financeNames->count());

        $courseNames = collect(app(AiToolSelectionService::class)->declarations(
            'Buka overview KPL401 lalu bandingkan bebannya dan cari slot belajar.',
            []
        ))->pluck('name');
        $this->assertContains('get_course_overview', $courseNames);
        $this->assertContains('compare_course_workload', $courseNames);
        $this->assertNotContains('get_workspace_overview', $courseNames);

        $this->assertCount(51, app(AiToolSelectionService::class)->declarations('Tolong bantu saya', []));
    }

    public function test_schedule_analysis_tools_detect_conflicts_find_free_slots_and_preview_safely(): void
    {
        [$user, $fixtures] = $this->fixtures();
        $tools = app(AiToolRegistry::class);

        $conflicts = $tools->find('check_schedule_conflicts')->execute($user, [
            'start_at' => '2026-09-17 10:30', 'end_at' => '2026-09-17 11:30',
        ]);
        $this->assertTrue($conflicts['has_conflict']);
        $this->assertSame($fixtures['schedule']->id, $conflicts['conflicts'][0]['source_schedule_id']);

        $slots = $tools->find('find_free_time_slots')->execute($user, [
            'start_date' => '2026-09-17', 'end_date' => '2026-09-17',
            'minimum_minutes' => 30, 'day_start' => '09:00', 'day_end' => '13:00',
        ]);
        $this->assertSame(2, $slots['returned']);
        $this->assertSame(60, $slots['slots'][0]['minutes']);

        $preview = $tools->find('preview_schedule_change')->execute($user, [
            'target' => 'Belajar Mandiri', 'start_date' => '2026-09-17', 'end_date' => '2026-09-17',
            'start_time' => '12:30', 'end_time' => '13:30',
        ]);
        $this->assertTrue($preview['has_conflict']);
        $this->assertSame('10:00', $fixtures['schedule']->fresh()->start_time);
    }

    public function test_advisory_tools_return_bounded_tenant_scoped_evidence_without_mutation(): void
    {
        [$user, $fixtures] = $this->fixtures();
        $tools = app(AiToolRegistry::class);

        $breakdown = $tools->find('suggest_task_breakdown')->execute($user, ['target' => 'Proyek Akhir', 'desired_steps' => 6]);
        $this->assertSame($fixtures['task']->id, $breakdown['task']['id']);
        $this->assertSame(6, $breakdown['desired_steps']);

        $review = $tools->find('prepare_weekly_review')->execute($user, []);
        $this->assertArrayHasKey('pending_work', $review);
        $this->assertArrayHasKey('next_seven_days', $review);

        $duplicates = $tools->find('detect_duplicate_records')->execute($user, ['domain' => 'todos']);
        $this->assertSame(1, $duplicates['candidate_group_count']);
        $this->assertFalse($duplicates['destructive_action_taken']);

        $anomalies = $tools->find('suggest_financial_anomalies')->execute($user, ['lookback_days' => 90]);
        $this->assertSame(1, $anomalies['candidate_count']);
        $this->assertSame($fixtures['anomaly']->id, $anomalies['candidates'][0]['transaction_id']);
    }

    public function test_course_tools_search_summarize_and_compare_owned_courses(): void
    {
        [$user, $fixtures] = $this->fixtures();
        $other = User::factory()->create();
        $this->createCourse($other, 'SECRET', 'Tenant Rahasia');
        $tools = app(AiToolRegistry::class);

        $search = $tools->find('search_courses')->execute($user, ['query' => 'Keamanan']);
        $this->assertSame(1, $search['total']);
        $this->assertSame($fixtures['course']->id, $search['courses'][0]['id']);

        $overview = $tools->find('get_course_overview')->execute($user, ['target' => 'KPL401']);
        $this->assertSame('KPL401', $overview['course']['code']);
        $this->assertSame(1, $overview['task_summary']['pending']);
        $this->assertSame(1, $overview['schedules'][0]['activity_count']);

        $comparison = $tools->find('compare_course_workload')->execute($user, ['semester' => 5]);
        $this->assertSame(1, $comparison['courses_compared']);
        $this->assertSame(120, $comparison['workloads'][0]['weekly_class_minutes']);
    }

    public function test_snooze_reminder_only_mutates_after_confirmation(): void
    {
        [$user, $fixtures] = $this->fixtures();
        $tool = app(AiToolRegistry::class)->find('snooze_reminder');
        $proposal = $tool->execute($user, [
            'target_type' => 'todolist', 'target_query' => 'Baca Modul', 'minutes' => 30,
        ]);

        $this->assertSame('2026-09-17 18:30:00', $fixtures['reminder']->fresh()->getRawOriginal('waktu_reminder'));
        app(AiWriteActionRegistry::class)->execute('snooze_reminder', $user, $proposal['payload']);
        $this->assertSame('2026-09-16 10:30:00', $fixtures['reminder']->fresh()->getRawOriginal('waktu_reminder'));
    }

    /** @return array{User, array<string, mixed>} */
    private function fixtures(): array
    {
        $user = User::factory()->create(['account_status' => 'active']);
        $course = $this->createCourse($user, 'KPL401', 'Keamanan Perangkat Lunak');
        $schedule = Jadwal::create([
            'user_id' => $user->id, 'matkul_id_list' => (string) $course->id, 'jenis' => 'lainnya',
            'title' => 'Belajar Mandiri', 'tanggal_mulai' => '2026-09-17', 'tanggal_selesai' => '2026-09-17',
            'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'Perpustakaan', 'is_completed' => false,
        ]);
        $schedule->kegiatans()->create(['nama_kegiatan' => 'Baca referensi', 'tanggal_deadline' => '2026-09-17', 'waktu' => '10:15', 'status' => 'belum_dimulai']);
        Jadwal::create([
            'user_id' => $user->id, 'jenis' => 'lainnya', 'title' => 'Rapat Tim',
            'tanggal_mulai' => '2026-09-17', 'tanggal_selesai' => '2026-09-17',
            'start_time' => '12:00', 'end_time' => '13:00', 'is_completed' => false,
        ]);
        $task = Tugas::create([
            'user_id' => $user->id, 'matkul_id' => $course->id, 'nama_tugas' => 'Proyek Akhir',
            'deskripsi' => 'Membuat threat model', 'deadline' => '2026-09-20 20:00', 'status_selesai' => false,
        ]);
        $todo = Todolist::create(['user_id' => $user->id, 'nama_item' => 'Baca Modul', 'status' => false]);
        Todolist::create(['user_id' => $user->id, 'nama_item' => 'Baca Modul', 'status' => false]);
        $reminder = Reminder::create([
            'user_id' => $user->id, 'todolist_id' => $todo->id, 'waktu_reminder' => '2026-09-17 18:30:00', 'aktif' => true,
        ]);
        foreach ([10000, 10000, 10000] as $amount) {
            Keuangan::create(['user_id' => $user->id, 'jenis' => 'pengeluaran', 'kategori' => 'Makanan', 'nominal' => $amount, 'deskripsi' => 'Makan', 'tanggal' => '2026-09-10']);
        }
        $anomaly = Keuangan::create(['user_id' => $user->id, 'jenis' => 'pengeluaran', 'kategori' => 'Makanan', 'nominal' => 100000, 'deskripsi' => 'Jamuan', 'tanggal' => '2026-09-15']);
        Catatan::create(['user_id' => $user->id, 'judul' => 'Catatan', 'isi' => 'Isi', 'tanggal' => '2026-09-16', 'show_preview' => true, 'status_sampah' => false]);

        return [$user, compact('course', 'schedule', 'task', 'reminder', 'anomaly')];
    }

    private function createCourse(User $user, string $code, string $name): Matkul
    {
        return Matkul::create([
            'user_id' => $user->id, 'kode' => $code, 'nama' => $name, 'kelas' => 'A;', 'dosen' => 'Dr. Aman',
            'semester' => 5, 'sks' => 3, 'hari' => 'Kamis;', 'jam_mulai' => '08:00;', 'jam_selesai' => '10:00;',
            'ruangan' => 'R101;', 'warna_label' => '#2563eb', 'catatan' => '',
        ]);
    }
}
