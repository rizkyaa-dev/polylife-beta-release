<?php

namespace Tests\Feature\Ai;

use App\Models\AffiliationBroadcast;
use App\Models\Catatan;
use App\Models\Ipk;
use App\Models\Jadwal;
use App\Models\Kegiatan;
use App\Models\KeuanganBudget;
use App\Models\Matkul;
use App\Models\NilaiMutu;
use App\Models\Reminder;
use App\Models\Todolist;
use App\Models\Tugas;
use App\Models\User;
use App\Services\Ai\Actions\AiWriteActionRegistry;
use App\Services\Ai\AiActionReceiptPresenter;
use App\Services\Ai\AiToolRegistry;
use App\Services\Ai\Exceptions\AiActionException;
use App\Services\Ai\ToolActivityPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiWorkspaceToolCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_read_tools_are_tenant_scoped_and_return_domain_data(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Matkul::query()->create($this->coursePayload($user, 'KPL101', 'Keamanan Perangkat Lunak'));
        Matkul::query()->create(array_merge(
            $this->coursePayload($user, 'DAS101', 'Dasar Pemrograman'),
            ['semester' => 1],
        ));
        Matkul::query()->create($this->coursePayload($other, 'RAHASIA', 'Mata Kuliah Rahasia'));
        Catatan::query()->create($this->notePayload($user, 'Zero Trust', 'Terapkan least privilege pada semua akses.'));
        Catatan::query()->create($this->notePayload($other, 'Rahasia Tenant', 'Zero trust tenant lain tidak boleh bocor.'));
        Ipk::query()->create([
            'user_id' => $user->id, 'semester' => 2, 'academic_year' => '2025/2026',
            'ips_actual' => 3.8, 'ipk_running' => 3.7, 'status' => 'final',
        ]);
        AffiliationBroadcast::query()->create([
            'created_by' => $other->id, 'title' => 'Pengumuman Global', 'body' => 'Isi publik.',
            'target_mode' => AffiliationBroadcast::TARGET_MODE_GLOBAL, 'send_push' => false,
            'status' => AffiliationBroadcast::STATUS_PUBLISHED, 'published_at' => now()->subMinute(),
        ]);

        $registry = app(AiToolRegistry::class);
        $courses = $registry->find('get_courses')->execute($user, []);
        $notes = $registry->find('search_catatan')->execute($user, ['query' => 'zero trust']);
        $academic = $registry->find('get_academic_summary')->execute($user, []);
        $announcements = $registry->find('get_announcements')->execute($user, []);

        $this->assertSame(1, $courses['count']);
        $this->assertSame('KPL101', $courses['courses'][0]['kode']);
        $this->assertSame(1, $notes['count']);
        $this->assertSame('Zero Trust', $notes['catatan'][0]['judul']);
        $this->assertSame(3.8, $academic['latest_ips']);
        $this->assertSame(3.7, $academic['cumulative_ipk']);
        $this->assertSame('Pengumuman Global', $announcements['announcements'][0]['title']);
    }

    public function test_task_and_reminder_actions_persist_only_after_explicit_execution(): void
    {
        $user = User::factory()->create();
        $todo = Todolist::query()->create(['user_id' => $user->id, 'nama_item' => 'Baca modul', 'status' => false]);
        $course = Matkul::query()->create($this->coursePayload($user, 'KPL101', 'Keamanan Perangkat Lunak'));
        $tools = app(AiToolRegistry::class);
        $actions = app(AiWriteActionRegistry::class);
        $tomorrow = now('Asia/Jakarta')->addDay();

        $taskProposal = $tools->find('create_tugas')->execute($user, [
            'nama_tugas' => 'Review jurnal',
            'deadline' => $tomorrow->copy()->setTime(21, 0)->toIso8601String(),
            'mata_kuliah' => 'KPL101',
        ]);
        $reminderProposal = $tools->find('create_reminder')->execute($user, [
            'target_type' => 'todolist', 'target_query' => 'Baca modul',
            'waktu_reminder' => $tomorrow->copy()->setTime(18, 30)->toIso8601String(),
        ]);

        $this->assertDatabaseCount('tugas', 0);
        $this->assertDatabaseCount('reminders', 0);

        $task = $actions->execute('create_tugas', $user, $taskProposal['payload']);
        $reminder = $actions->execute('create_reminder', $user, $reminderProposal['payload']);

        $this->assertInstanceOf(Tugas::class, $task);
        $this->assertSame($course->id, $task->matkul_id);
        $this->assertInstanceOf(Reminder::class, $reminder);
        $this->assertSame($todo->id, $reminder->todolist_id);
        $this->assertSame($user->id, $reminder->user_id);
    }

    public function test_reminder_action_revalidates_target_ownership(): void
    {
        $user = User::factory()->create();
        $otherTodo = Todolist::query()->create([
            'user_id' => User::factory()->create()->id,
            'nama_item' => 'Target tenant lain',
            'status' => false,
        ]);

        $this->expectException(AiActionException::class);

        app(AiWriteActionRegistry::class)->execute('create_reminder', $user, [
            'reminder_target' => 'todolist',
            'todolist_id' => $otherTodo->id,
            'waktu_reminder' => now()->addDay()->format('Y-m-d H:i:s'),
            'aktif' => true,
        ]);
    }

    public function test_reminder_tool_rejects_ambiguous_fuzzy_target(): void
    {
        $user = User::factory()->create();
        Todolist::query()->create(['user_id' => $user->id, 'nama_item' => 'Baca modul satu', 'status' => false]);
        Todolist::query()->create(['user_id' => $user->id, 'nama_item' => 'Baca modul dua', 'status' => false]);

        $this->expectException(AiActionException::class);

        app(AiToolRegistry::class)->find('create_reminder')->execute($user, [
            'target_type' => 'todolist',
            'target_query' => 'Baca modul',
            'waktu_reminder' => now('Asia/Jakarta')->addDay()->toIso8601String(),
        ]);
    }

    public function test_management_actions_require_execution_and_preserve_domain_invariants(): void
    {
        $user = User::factory()->create();
        $todo = Todolist::query()->create(['user_id' => $user->id, 'nama_item' => 'Baca modul', 'status' => false]);
        $task = Tugas::query()->create([
            'user_id' => $user->id, 'nama_tugas' => 'Threat model',
            'deadline' => now()->addDay(), 'status_selesai' => false,
        ]);
        $todoReminder = Reminder::query()->create([
            'user_id' => $user->id, 'todolist_id' => $todo->id,
            'waktu_reminder' => now()->addDay(), 'aktif' => true,
        ]);
        $taskReminder = Reminder::query()->create([
            'user_id' => $user->id, 'tugas_id' => $task->id,
            'waktu_reminder' => now()->addDay(), 'aktif' => true,
        ]);
        $note = Catatan::query()->create($this->notePayload($user, 'Zero Trust', 'Isi awal.'));
        $tools = app(AiToolRegistry::class);
        $actions = app(AiWriteActionRegistry::class);

        $todoProposal = $tools->find('manage_todolist')->execute($user, ['target' => 'Baca modul', 'operation' => 'complete']);
        $taskProposal = $tools->find('manage_tugas')->execute($user, ['target' => 'Threat model', 'operation' => 'complete']);
        $noteProposal = $tools->find('update_catatan')->execute($user, [
            'target' => 'Zero Trust', 'operation' => 'append', 'value' => 'Isi tambahan.',
        ]);
        $budgetProposal = $tools->find('set_finance_budget')->execute($user, [
            'kategori' => 'Makanan & Minuman', 'nominal_limit' => 500000,
        ]);

        $this->assertFalse($todo->fresh()->status);
        $this->assertFalse($task->fresh()->status_selesai);
        $this->assertSame('Isi awal.', $note->fresh()->isi);
        $this->assertDatabaseCount('keuangan_budgets', 0);

        $actions->execute('manage_todolist', $user, $todoProposal['payload']);
        $actions->execute('manage_tugas', $user, $taskProposal['payload']);
        $actions->execute('update_catatan', $user, $noteProposal['payload']);
        $budget = $actions->execute('set_finance_budget', $user, $budgetProposal['payload']);

        $this->assertTrue($todo->fresh()->status);
        $this->assertTrue($task->fresh()->status_selesai);
        $this->assertFalse($todoReminder->fresh()->aktif);
        $this->assertFalse($taskReminder->fresh()->aktif);
        $this->assertSame("Isi awal.\n\nIsi tambahan.", $note->fresh()->isi);
        $this->assertInstanceOf(KeuanganBudget::class, $budget);
        $this->assertSame($user->id, $budget->user_id);
    }

    public function test_note_update_rejects_stale_proposal(): void
    {
        $user = User::factory()->create();
        $note = Catatan::query()->create($this->notePayload($user, 'Zero Trust', 'Versi pertama.'));
        $proposal = app(AiToolRegistry::class)->find('update_catatan')->execute($user, [
            'target' => 'Zero Trust', 'operation' => 'append', 'value' => 'Tambahan AI.',
        ]);
        $this->travel(2)->seconds();
        $note->update(['isi' => 'Edit terbaru pengguna.']);

        $this->expectException(AiActionException::class);

        app(AiWriteActionRegistry::class)->execute('update_catatan', $user, $proposal['payload']);
    }

    public function test_reminder_read_tool_is_tenant_scoped(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $ownTodo = Todolist::query()->create(['user_id' => $user->id, 'nama_item' => 'Milik sendiri', 'status' => false]);
        $otherTodo = Todolist::query()->create(['user_id' => $other->id, 'nama_item' => 'Milik tenant lain', 'status' => false]);
        Reminder::query()->create([
            'user_id' => $user->id, 'todolist_id' => $ownTodo->id,
            'waktu_reminder' => now('Asia/Jakarta')->addDay()->setTime(18, 30), 'aktif' => true,
        ]);
        Reminder::query()->create([
            'user_id' => $other->id, 'todolist_id' => $otherTodo->id,
            'waktu_reminder' => now()->addDay(), 'aktif' => true,
        ]);

        $result = app(AiToolRegistry::class)->find('get_reminders')->execute($user, []);

        $this->assertSame(1, $result['count']);
        $this->assertSame('Milik sendiri', $result['reminders'][0]['target_name']);
        $this->assertStringContainsString('T18:30:00+07:00', $result['reminders'][0]['waktu_reminder']);
    }

    public function test_structured_academic_tools_use_canonical_models_and_actions(): void
    {
        $user = User::factory()->create();
        $schedule = Jadwal::query()->create([
            'user_id' => $user->id, 'jenis' => 'lainnya',
            'tanggal_mulai' => now()->addWeek()->toDateString(), 'tanggal_selesai' => now()->addWeek()->toDateString(),
            'title' => 'Proyek Skripsi', 'start_time' => '13:00', 'end_time' => '15:00',
        ]);
        Ipk::query()->create([
            'user_id' => $user->id, 'semester' => 4, 'academic_year' => '2025/2026',
            'ips_actual' => 3.5, 'ipk_running' => 3.5, 'status' => 'final', 'target_mode' => 'ips',
        ]);
        NilaiMutu::query()->create([
            'user_id' => $user->id, 'kampus' => 'Kampus Uji', 'is_active' => true,
            'grades_plus_minus' => [['letter' => 'A', 'min_score' => 85, 'max_score' => 100, 'grade_point' => 4]],
            'grades_ab' => [],
        ]);
        $tools = app(AiToolRegistry::class);
        $actions = app(AiWriteActionRegistry::class);

        $courseProposal = $tools->find('create_course')->execute($user, [
            'kode' => 'IF305', 'nama' => 'Basis Data', 'kelas' => 'B', 'dosen' => 'Dr. Data',
            'semester' => 5, 'sks' => 3, 'hari' => 'Senin', 'jam_mulai' => '10:00',
            'jam_selesai' => '12:00', 'ruangan' => 'R204',
        ]);
        $activityProposal = $tools->find('create_kegiatan')->execute($user, [
            'jadwal' => 'Proyek Skripsi', 'nama_kegiatan' => 'Wawancara pengguna',
            'tanggal' => now()->addWeek()->toDateString(), 'waktu' => '14:00', 'lokasi' => 'Lab UX',
        ]);
        $academicProposal = $tools->find('record_academic_result')->execute($user, [
            'semester' => 5, 'academic_year' => '2026/2027', 'ips_actual' => 3.9,
        ]);

        $this->assertDatabaseMissing('matkuls', ['user_id' => $user->id, 'kode' => 'IF305']);
        $course = $actions->execute('create_course', $user, $courseProposal['payload']);
        $activity = $actions->execute('create_kegiatan', $user, $activityProposal['payload']);
        $academic = $actions->execute('record_academic_result', $user, $academicProposal['payload']);
        $gradeScale = $tools->find('get_grade_scale')->execute($user, ['score' => 88]);

        $this->assertInstanceOf(Matkul::class, $course);
        $this->assertInstanceOf(Kegiatan::class, $activity);
        $this->assertSame($schedule->id, $activity->jadwal_id);
        $this->assertSame(5, $academic->semester);
        $this->assertSame(3.7, $academic->ipk_running);
        $this->assertSame('A', $gradeScale['matched_grade']['letter']);
        $this->assertSame(4, $gradeScale['matched_grade']['grade_point']);
    }

    public function test_every_supported_write_tool_has_a_workspace_receipt_destination(): void
    {
        $presenter = app(AiActionReceiptPresenter::class);
        $tools = [
            'create_catatan', 'update_catatan', 'create_todolist', 'manage_todolist',
            'create_tugas', 'manage_tugas', 'create_keuangan', 'set_finance_budget',
            'create_jadwal', 'create_reminder', 'create_course', 'create_kegiatan',
            'record_academic_result', 'update_jadwal', 'update_kegiatan',
            'update_reminder', 'update_keuangan', 'update_course', 'update_tugas',
            'mark_announcement_read', 'duplicate_schedule', 'copy_course_to_semester',
            'archive_note', 'restore_note',
            'snooze_reminder',
        ];

        foreach ($tools as $tool) {
            $receipt = $presenter->present($tool);
            $this->assertNotNull($receipt['destination_label'], $tool);
            $this->assertNotNull($receipt['destination_url'], $tool);
            $this->assertNotSame('', $receipt['acknowledgement'], $tool);
        }
    }

    public function test_every_registered_tool_has_a_specific_activity_label(): void
    {
        $registry = app(AiToolRegistry::class);
        $presenter = app(ToolActivityPresenter::class);

        foreach ($registry->getDeclarations() as $declaration) {
            $name = $declaration['name'];
            $this->assertNotSame('Menggunakan alat bantu', $presenter->label($name), $name);
        }
    }

    public function test_missing_proposal_returns_safe_client_error(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);

        $this->actingAs($user)->postJson(route('ai.action.confirm'), [
            'action_id' => 'missing-action-id',
            'signature' => str_repeat('a', 64),
        ])->assertUnprocessable()->assertJson([
            'status' => 'error',
            'message' => 'Proposal tidak ditemukan atau sudah tidak tersedia.',
        ]);
    }

    /** @return array<string, mixed> */
    private function notePayload(User $user, string $title, string $content): array
    {
        return [
            'user_id' => $user->id,
            'judul' => $title,
            'isi' => $content,
            'show_preview' => true,
            'tanggal' => '2026-09-15',
            'status_sampah' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function coursePayload(User $user, string $code, string $name): array
    {
        return [
            'user_id' => $user->id,
            'kode' => $code,
            'nama' => $name,
            'kelas' => 'A',
            'dosen' => 'Dosen Penguji',
            'semester' => 2,
            'sks' => 3,
            'hari' => 'Senin',
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:00',
            'ruangan' => 'R1',
            'warna_label' => '#2563eb',
            'catatan' => '',
        ];
    }
}
