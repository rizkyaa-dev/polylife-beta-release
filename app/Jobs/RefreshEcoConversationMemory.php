<?php

namespace App\Jobs;

use App\Services\Ai\Eco\EcoConversationMemory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class RefreshEcoConversationMemory implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public readonly int $runId) {}

    public function backoff(): array
    {
        return [5, 30];
    }

    public function handle(EcoConversationMemory $memory): void
    {
        $memory->refresh($this->runId);
    }
}
