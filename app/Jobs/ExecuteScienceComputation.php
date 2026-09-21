<?php

namespace App\Jobs;

use App\Services\Ai\Science\ScienceExecutionBroker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

final class ExecuteScienceComputation implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public bool $failOnTimeout = true;

    public readonly string $claimToken;

    public function __construct(public readonly int $executionId)
    {
        $this->claimToken = (string) Str::uuid();
    }

    public function handle(ScienceExecutionBroker $broker): void
    {
        $broker->execute($this->executionId, claimToken: $this->claimToken);
    }

    public function failed(?Throwable $exception): void
    {
        app(ScienceExecutionBroker::class)->fail($this->executionId, claimToken: $this->claimToken ?? null);
    }
}
