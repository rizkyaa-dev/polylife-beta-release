<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;

final class ExportWorkspaceSummaryTool implements AiToolInterface
{
    public function __construct(private readonly GetWorkspaceOverviewTool $overview) {}

    public function name(): string
    {
        return 'export_workspace_summary';
    }

    public function description(): string
    {
        return 'Menyusun ringkasan workspace dalam Markdown yang dapat disalin pengguna. Tidak membuat file permanen dan tidak mengubah data.';
    }

    public function schema(): array
    {
        return ['name' => $this->name(), 'description' => $this->description(), 'parameters' => [
            'type' => 'object', 'properties' => [
                'range_days' => ['type' => 'integer', 'description' => 'Rentang 1-31 hari, default 7'],
            ],
        ]];
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function execute(User $user, array $arguments): array
    {
        $overview = $this->overview->execute($user, $arguments);
        $schedule = $overview['schedule'];
        $work = $overview['pending_work'];
        $finance = $overview['finance'];
        $academic = $overview['academic'];
        $lines = [
            '# Ringkasan PolyLife',
            '',
            '- Dibuat: '.$overview['generated_at'],
            '- Jadwal '.$overview['range_days'].' hari: '.$schedule['total'],
            '- Tugas tertunda: '.$work['pending_tugas_count'],
            '- To-do aktif: '.$work['active_todos_count'],
            '- Reminder mendatang: '.$overview['reminders']['count'],
            '- Pemasukan bulan ini: Rp'.number_format((float) $finance['total_pemasukan'], 0, ',', '.'),
            '- Pengeluaran bulan ini: Rp'.number_format((float) $finance['total_pengeluaran'], 0, ',', '.'),
            '- Saldo bulan ini: Rp'.number_format((float) $finance['saldo_bulan_ini'], 0, ',', '.'),
            '- IPS terbaru: '.($academic['latest_ips'] ?? 'belum ada'),
            '- IPK kumulatif: '.($academic['cumulative_ipk'] ?? 'belum ada'),
        ];

        return ['format' => 'markdown', 'filename_suggestion' => 'polylife-summary.md', 'content' => implode("\n", $lines)];
    }
}
