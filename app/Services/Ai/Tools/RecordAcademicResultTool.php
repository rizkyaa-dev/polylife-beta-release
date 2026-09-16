<?php

namespace App\Services\Ai\Tools;

use App\Models\Ipk;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\Exceptions\AiActionException;

final class RecordAcademicResultTool implements AiToolInterface
{
    public function name(): string
    {
        return 'record_academic_result';
    }

    public function description(): string
    {
        return 'Mengusulkan pencatatan IPS aktual untuk semester berikutnya. Jangan menyimpan hasil akademik sebagai catatan biasa.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(), 'description' => $this->description(),
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'semester' => ['type' => 'integer'],
                    'academic_year' => ['type' => 'string', 'description' => 'YYYY/YYYY'],
                    'ips_actual' => ['type' => 'number', 'description' => 'IPS 0 sampai 4'],
                    'remarks' => ['type' => 'string'],
                ],
                'required' => ['semester', 'academic_year', 'ips_actual'],
            ],
        ];
    }

    public function isMutating(): bool
    {
        return true;
    }

    public function execute(User $user, array $arguments): array
    {
        $semester = (int) ($arguments['semester'] ?? 0);
        $ips = (float) ($arguments['ips_actual'] ?? -1);
        $latestSemester = Ipk::query()->forUser((int) $user->id)->whereNotNull('semester')->max('semester');
        $expectedSemester = $latestSemester ? $latestSemester + 1 : 1;
        if ($semester !== $expectedSemester) {
            throw new AiActionException("Semester harus berurutan. Semester berikutnya: {$expectedSemester}.");
        }

        return [
            'status' => 'proposal_created', 'tool_name' => $this->name(),
            'summary' => "IPS Semester {$semester}: {$ips}",
            'payload' => [
                'semester' => $semester,
                'academic_year' => trim((string) ($arguments['academic_year'] ?? '')),
                'ips_actual' => $ips,
                'remarks' => trim((string) ($arguments['remarks'] ?? '')) ?: null,
                'status' => 'final',
                'target_mode' => 'ips',
            ],
        ];
    }
}
