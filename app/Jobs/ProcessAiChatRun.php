<?php

namespace App\Jobs;

use App\Services\Ai\AiAgentOrchestrator;
use App\Services\Ai\AiRunStateManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessAiChatRun implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 330;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $runId) {}

    public function handle(AiAgentOrchestrator $orchestrator): void
    {
        $orchestrator->processRun($this->runId);
    }

    public function failed(?Throwable $exception): void
    {
        app(AiRunStateManager::class)->fail($this->runId, 'worker_failed', true);
    }
}
