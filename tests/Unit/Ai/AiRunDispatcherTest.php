<?php

namespace Tests\Unit\Ai;

use App\Jobs\ProcessAiChatRun;
use App\Services\Ai\AiRunDispatcher;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Tests\TestCase;

class AiRunDispatcherTest extends TestCase
{
    public function test_it_uses_the_configured_default_connection_and_ai_queue(): void
    {
        Queue::fake();
        config()->set('services.ai_queue_connection', null);
        config()->set('queue.default', 'database');

        app(AiRunDispatcher::class)->dispatch(42);

        Queue::assertPushedOn('ai', ProcessAiChatRun::class, function (ProcessAiChatRun $job): bool {
            return $job->runId === 42 && $job->connection === 'database';
        });
    }

    public function test_it_rejects_an_unknown_queue_connection(): void
    {
        config()->set('services.ai_queue_connection', 'missing');

        $this->expectException(InvalidArgumentException::class);

        app(AiRunDispatcher::class)->dispatch(42);
    }
}
