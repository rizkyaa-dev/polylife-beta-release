<?php

namespace App\Services\Ai\Tools;

use App\Models\Ipk;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;

final class GetAcademicSummaryTool implements AiToolInterface
{
    public function name(): string
    {
        return 'get_academic_summary';
    }

    public function description(): string
    {
        return 'Mengambil IPS terbaru, IPK kumulatif, target, dan riwayat performa akademik pengguna.';
    }

    public function schema(): array
    {
        return ['name' => $this->name(), 'description' => $this->description(), 'parameters' => ['type' => 'object']];
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function execute(User $user, array $arguments): array
    {
        $entries = Ipk::query()->forUser((int) $user->id)->orderBy('semester')->get([
            'semester', 'academic_year', 'ips_actual', 'ips_target', 'ipk_running', 'ipk_target', 'status',
        ]);
        $actual = $entries->whereNotNull('ips_actual');
        $latest = $actual->last();

        return [
            'latest_ips' => $latest?->ips_actual,
            'latest_semester' => $latest?->semester,
            'cumulative_ipk' => $latest?->ipk_running ?? ($actual->isNotEmpty() ? round((float) $actual->avg('ips_actual'), 2) : null),
            'history' => $entries->values()->toArray(),
        ];
    }
}
