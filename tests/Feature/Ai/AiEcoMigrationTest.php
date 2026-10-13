<?php

namespace Tests\Feature\Ai;

use App\Models\AiChatRun;
use App\Models\User;
use App\Services\Ai\AiAgentOrchestrator;
use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\DTOs\LlmResponse;
use App\Services\Ai\Enums\ResponseMode;
use App\Services\Ai\Exceptions\AiEcoUnavailableException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AiEcoMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rollback_keeps_default_usable_and_reapply_preserves_existing_chat_data(): void
    {
        $client = new class implements LlmClientInterface
        {
            public array $toolCounts = [];

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $this->toolCounts[] = count($tools);

                return new LlmResponse('Reply');
            }
        };
        $this->app->instance(LlmClientInterface::class, $client);
        $user = User::factory()->create();
        $migration = require database_path('migrations/2026_10_11_000001_add_eco_context_to_ai_chat.php');
        $migration->down();
        try {
            $this->assertFalse(Schema::hasColumn('ai_chat_runs', 'context_policy'));
            $this->assertFalse(Schema::hasColumn('ai_chat_branches', 'eco_context_memory'));
            $default = app(AiAgentOrchestrator::class)->handle($user, 'Hello');
            $this->assertSame('completed', $default['run']->status);
            $this->assertGreaterThan(40, $client->toolCounts[0]);
            try {
                app(AiAgentOrchestrator::class)->handle($user, 'Hi', responseMode: ResponseMode::Eco);
                $this->fail('Missing Eco schema must reject admission.');
            } catch (AiEcoUnavailableException $exception) {
                $this->assertSame('schema_unavailable', $exception->reason);
            }
            $this->assertDatabaseCount('ai_chat_runs', 1);
            $this->assertDatabaseCount('ai_chat_messages', 2);
        } finally {
            $migration->up();
        }
        $this->assertTrue(Schema::hasColumns('ai_chat_branches', ['eco_context_memory', 'eco_memory_refreshed_at']));
        $this->assertSame('default', AiChatRun::findOrFail($default['run']->id)->response_mode);
        $eco = app(AiAgentOrchestrator::class)->handle($user, 'Hi', responseMode: ResponseMode::Eco);
        $this->assertSame('completed', $eco['run']->status);
        $this->assertSame('eco', $eco['run']->response_mode);
        $this->assertSame(4, $client->toolCounts[1]);
        $this->assertDatabaseCount('ai_chat_runs', 2);
        $this->assertDatabaseCount('ai_chat_messages', 4);
    }
}
