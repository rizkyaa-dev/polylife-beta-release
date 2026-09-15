<?php

namespace App\Services\Ai;

use App\Jobs\ProcessAiChatRun;
use InvalidArgumentException;

final class AiRunDispatcher
{
    public function dispatch(int $runId): void
    {
        $connection = trim((string) (config('services.ai_queue_connection') ?: config('queue.default')));
        $connections = (array) config('queue.connections', []);

        if ($connection === '' || ! array_key_exists($connection, $connections)) {
            throw new InvalidArgumentException("Koneksi queue AI '{$connection}' tidak valid.");
        }

        ProcessAiChatRun::dispatch($runId)
            ->onConnection($connection)
            ->onQueue('ai')
            ->afterCommit();
    }
}
