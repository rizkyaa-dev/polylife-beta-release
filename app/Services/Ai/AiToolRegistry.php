<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\Tools\CreateCatatanTool;
use App\Services\Ai\Tools\CreateJadwalTool;
use App\Services\Ai\Tools\CreateKeuanganTool;
use App\Services\Ai\Tools\CreateTodolistTool;
use App\Services\Ai\Tools\GetFinancialSummaryTool;
use App\Services\Ai\Tools\GetPendingTasksTool;
use App\Services\Ai\Tools\GetUpcomingScheduleTool;

class AiToolRegistry
{
    /**
     * @var array<string, AiToolInterface>
     */
    private array $tools = [];

    public function __construct(
        GetUpcomingScheduleTool $getUpcomingSchedule,
        GetPendingTasksTool $getPendingTasks,
        GetFinancialSummaryTool $getFinancialSummary,
        CreateJadwalTool $createJadwal,
        CreateKeuanganTool $createKeuangan,
        CreateTodolistTool $createTodolist,
        CreateCatatanTool $createCatatan
    ) {
        foreach ([
            $getUpcomingSchedule,
            $getPendingTasks,
            $getFinancialSummary,
            $createJadwal,
            $createKeuangan,
            $createTodolist,
            $createCatatan,
        ] as $tool) {
            $this->register($tool);
        }
    }

    public function register(AiToolInterface $tool): self
    {
        $this->tools[$tool->name()] = $tool;

        return $this;
    }

    public function find(string $name): ?AiToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getDeclarations(): array
    {
        return array_values(array_map(
            fn (AiToolInterface $tool) => $tool->schema(),
            $this->tools
        ));
    }
}
