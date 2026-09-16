<?php

namespace Tests\Unit\Ai;

use App\Jobs\ProcessAiChatRun;
use App\Services\Ai\AiRunDispatcher;
use App\Services\Ai\Enums\ThinkingEffort;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Tests\TestCase;

class AiRunDispatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_routes_heavy_effort_to_the_configured_heavy_queue(): void
    {
        Queue::fake();
        config()->set('services.ai_queue_connection', null);
        config()->set('queue.default', 'database');

        app(AiRunDispatcher::class)->dispatch(42);

        Queue::assertPushedOn('ai-heavy', ProcessAiChatRun::class, function (ProcessAiChatRun $job): bool {
            return $job->runId === 42 && $job->connection === 'database';
        });
    }

    public function test_it_routes_low_effort_to_the_fast_queue(): void
    {
        Queue::fake();

        app(AiRunDispatcher::class)->dispatch(43, ThinkingEffort::Low);

        Queue::assertPushedOn('ai-fast', ProcessAiChatRun::class, fn (ProcessAiChatRun $job): bool => $job->runId === 43);
    }

    public function test_local_development_uses_durable_queue_when_no_ai_connection_is_configured(): void
    {
        Queue::fake();
        config()->set('services.ai_queue_connection', null);
        $previousEnvironment = app()->environment();
        app()->detectEnvironment(fn (): string => 'local');

        try {
            app(AiRunDispatcher::class)->dispatch(44, ThinkingEffort::Low);
        } finally {
            app()->detectEnvironment(fn (): string => $previousEnvironment);
        }

        Queue::assertPushedOn('ai-fast', ProcessAiChatRun::class, function (ProcessAiChatRun $job): bool {
            return $job->runId === 44 && $job->connection === 'database';
        });
    }

    public function test_it_rejects_an_unknown_queue_connection(): void
    {
        config()->set('services.ai_queue_connection', 'missing');

        $this->expectException(InvalidArgumentException::class);

        app(AiRunDispatcher::class)->dispatch(42);
    }

    public function test_local_ai_rejects_explicit_deferred_execution(): void
    {
        config()->set('services.ai_queue_connection', 'deferred');
        $previousEnvironment = app()->environment();
        app()->detectEnvironment(fn (): string => 'local');
        try {
            $this->expectException(InvalidArgumentException::class);
            app(AiRunDispatcher::class)->connection();
        } finally {
            app()->detectEnvironment(fn (): string => $previousEnvironment);
        }
    }
}
