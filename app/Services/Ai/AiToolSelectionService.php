<?php

namespace App\Services\Ai;

use App\Services\Ai\DTOs\LlmMessage;
use Illuminate\Support\Str;

final class AiToolSelectionService
{
    /** @var array<string, list<string>> */
    private const DOMAIN_TOOLS = [
        'schedule' => [
            'get_upcoming_schedule', 'search_schedules', 'create_jadwal', 'update_jadwal', 'duplicate_schedule',
            'create_kegiatan', 'update_kegiatan', 'check_schedule_conflicts', 'find_free_time_slots', 'preview_schedule_change',
        ],
        'tasks' => [
            'get_pending_tasks', 'search_tasks', 'create_tugas', 'update_tugas', 'manage_tugas',
            'create_todolist', 'manage_todolist', 'suggest_task_breakdown', 'suggest_study_plan',
        ],
        'finance' => [
            'get_financial_summary', 'search_financial_transactions', 'create_keuangan', 'update_keuangan',
            'set_finance_budget', 'suggest_budget', 'suggest_financial_anomalies',
        ],
        'notes' => ['search_catatan', 'create_catatan', 'update_catatan', 'archive_note', 'restore_note'],
        'courses' => [
            'get_courses', 'search_courses', 'get_course_overview', 'compare_course_workload',
            'create_course', 'update_course', 'copy_course_to_semester',
        ],
        'academic' => ['get_academic_summary', 'get_grade_scale', 'record_academic_result'],
        'announcements' => ['get_announcements', 'mark_announcement_read'],
        'reminders' => ['get_reminders', 'create_reminder', 'update_reminder', 'snooze_reminder'],
        'overview' => ['get_workspace_overview', 'export_workspace_summary', 'prepare_weekly_review', 'detect_duplicate_records'],
    ];

    /** @var array<string, list<string>> */
    private const KEYWORDS = [
        'schedule' => ['jadwal', 'agenda', 'kegiatan', 'slot', 'bentrok', 'waktu kosong', 'free time', 'besok', 'lusa'],
        'tasks' => ['tugas', 'todo', 'to-do', 'deadline', 'pekerjaan', 'prioritas', 'belajar'],
        'finance' => ['keuangan', 'uang', 'transaksi', 'pengeluaran', 'pemasukan', 'anggaran', 'budget', 'rupiah', 'rp'],
        'notes' => ['catatan', 'note', 'ringkasan materi'],
        'courses' => ['mata kuliah', 'matkul', 'course', 'dosen', 'sks', 'kelas kuliah'],
        'academic' => ['ipk', 'ips', 'nilai', 'akademik', 'semester'],
        'announcements' => ['pengumuman', 'announcement', 'kampus terbaru'],
        'reminders' => ['reminder', 'pengingat', 'ingatkan', 'tunda pengingat', 'snooze'],
        'overview' => ['workspace', 'overview workspace', 'review mingguan', 'weekly review', 'ekspor', 'export', 'duplikat data'],
    ];

    public function __construct(private readonly AiToolRegistry $registry) {}

    /**
     * @param  list<LlmMessage>  $history
     * @return list<array<string, mixed>>
     */
    public function declarations(string $prompt, array $history): array
    {
        $recentContext = collect($history)->reverse()->take(4)->pluck('content')->filter()->reverse()->implode(' ');
        $text = Str::lower(Str::squish($recentContext.' '.$prompt));
        $domains = collect(self::KEYWORDS)
            ->filter(fn (array $keywords): bool => collect($keywords)->contains(fn (string $keyword): bool => str_contains($text, $keyword)))
            ->keys();
        if (preg_match('/\b[a-z]{2,}[- ]?\d{2,}\b/u', $text) === 1) {
            $domains->push('courses');
        }
        $domains = $domains->unique()->values();

        if ($domains->isEmpty()) {
            return $this->registry->getDeclarations();
        }

        $names = $domains->flatMap(fn (string $domain): array => self::DOMAIN_TOOLS[$domain])->unique()->values()->all();

        return $this->registry->getDeclarations($names);
    }
}
