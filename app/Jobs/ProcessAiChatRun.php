<?php

namespace App\Jobs;

use App\Services\Ai\AiAgentOrchestrator;
use App\Services\Ai\AiRunStateManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class ProcessAiChatRun implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 510;

    public bool $failOnTimeout = true;

    public readonly string $claimToken;

    public function __construct(public readonly int $runId)
    {
        $this->claimToken = (string) Str::uuid();
    }

    public function handle(AiAgentOrchestrator $orchestrator): void
    {
        $orchestrator->processRun($this->runId, claimToken: $this->claimToken ?? null);
    }

    public function failed(?Throwable $exception): void
    {
        // Legacy jobs have no persistent claim token; lease recovery handles them.
        if (isset($this->claimToken)) {
            app(AiRunStateManager::class)->fail($this->runId, 'worker_failed', true, expectedClaimToken: $this->claimToken);
        }
    }
}
