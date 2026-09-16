<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\Exceptions\AiActionException;
use App\Services\Ai\UserTimeContext;

final class SetFinanceBudgetTool implements AiToolInterface
{
    public function __construct(private readonly UserTimeContext $timeContext) {}

    public function name(): string
    {
        return 'set_finance_budget';
    }

    public function description(): string
    {
        return 'Mengusulkan batas anggaran bulanan untuk satu kategori pengeluaran.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(), 'description' => $this->description(),
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'kategori' => ['type' => 'string', 'description' => 'Kategori anggaran'],
                    'nominal_limit' => ['type' => 'number', 'description' => 'Batas nominal rupiah, minimal 1000'],
                    'month' => ['type' => 'string', 'description' => 'Bulan YYYY-MM, default bulan saat ini'],
                ],
                'required' => ['kategori', 'nominal_limit'],
            ],
        ];
    }

    public function isMutating(): bool
    {
        return true;
    }

    public function execute(User $user, array $arguments): array
    {
        $category = trim((string) ($arguments['kategori'] ?? ''));
        $nominal = (float) ($arguments['nominal_limit'] ?? 0);
        if ($category === '' || $nominal < 1000) {
            throw new AiActionException('Kategori dan batas anggaran minimal Rp1.000 wajib diisi.');
        }
        $month = isset($arguments['month'])
            ? $this->timeContext->parse($user, $arguments['month'].'-01')
            : $this->timeContext->now($user);

        return [
            'status' => 'proposal_created', 'tool_name' => $this->name(),
            'summary' => sprintf('Anggaran %s %s: Rp%s', $category, $month->format('Y-m'), number_format($nominal, 0, ',', '.')),
            'payload' => [
                'kategori' => $category,
                'nominal_limit' => $nominal,
                'bulan' => (int) $month->month,
                'tahun' => (int) $month->year,
            ],
        ];
    }
}
