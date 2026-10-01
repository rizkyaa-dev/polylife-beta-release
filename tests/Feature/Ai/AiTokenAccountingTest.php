<?php

namespace Tests\Feature\Ai;

use App\Models\AiChatRun;
use App\Models\User;
use App\Services\Ai\AiAgentOrchestrator;
use App\Services\Ai\AiChatResponseFactory;
use App\Services\Ai\AiTokenUsageSummary;
use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\DTOs\LlmResponse;
use App\Services\Ai\DTOs\LlmTokenUsage;
use App\Services\Ai\DTOs\LlmToolCall;
use App\Services\Ai\Exceptions\AiProviderException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiTokenAccountingTest extends TestCase
{
    use RefreshDatabase;

    private function responses(array $responses): void
    {
        $this->app->instance(LlmClientInterface::class, new class($responses) implements LlmClientInterface
        {
            public function __construct(private array $responses) {}

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $response = array_shift($this->responses);
                if ($response instanceof \Throwable) {
                    throw $response;
                }

                return $response;
            }
        });
    }

    public function test_coding_repair_accounts_for_both_artifacts_and_the_routing_call(): void
    {
        $usage = new LlmTokenUsage(100, 10, 110);
        $this->responses([
            new LlmResponse(null, [new LlmToolCall('coder', 'delegate_code_generation', [
                'language' => 'html', 'runtime' => 'browser', 'files' => ['landing.html'],
                'requirements' => ['Page'], 'acceptance_criteria' => ['Valid links'],
            ])], usage: $usage),
            new LlmResponse("```html\n<html><a href=\"#missing\">Broken</a></html>\n```", usage: $usage),
            new LlmResponse("```html\n<html><a href=\"#page\">Page</a><div id=\"page\"></div></html>\n```", usage: $usage),
        ]);
        $run = app(AiAgentOrchestrator::class)->handle(User::factory()->create(), 'Create page')['run'];
        $this->assertSame(330, $run->total_tokens);
        $this->assertSame(3, $run->model_calls);
        $this->assertSame('complete', $run->tokenUsageStatus());
        $this->assertSame(220, $run->steps()->where('tool_name', 'delegate_code_generation')->firstOrFail()->public_metadata['tokens']['total']);
        $reply = app(AiChatResponseFactory::class)->completed($run);
        $this->assertStringContainsString('data-code-filename="landing.html"', $reply['reply_html']);
        $this->actingAs($run->session->user)->get(route('ai.workspace', ['session' => $run->session_id]))
            ->assertOk()->assertSee('data-code-filename="landing.html"', false);
    }

    public function test_failed_run_keeps_previous_billed_tokens_and_marks_missing_measurements(): void
    {
        $this->responses([
            new LlmResponse(null, [new LlmToolCall('lookup', 'lookup_info', [])], usage: new LlmTokenUsage(100, 10, 110)),
            AiProviderException::timeout(),
        ]);
        try {
            app(AiAgentOrchestrator::class)->handle(User::factory()->create(), 'Lookup data');
            $this->fail('Expected timeout.');
        } catch (AiProviderException) {
            $run = AiChatRun::firstOrFail();
            $this->assertSame('failed', $run->status);
            $this->assertSame(110, $run->total_tokens);
            $this->assertSame('partial', $run->tokenUsageStatus());
            $summary = AiTokenUsageSummary::session($run->session_id);
            $this->assertSame(110, $summary['total']);
            $this->assertSame('partial', $summary['status']);
            $this->actingAs($run->session->user)->getJson(route('ai.runs.show', ['run' => $run->id]))
                ->assertOk()->assertJsonPath('run.tokens.total', 110)->assertJsonPath('session_tokens.status', 'partial');
        }
    }

    public function test_historical_missing_usage_is_unknown_and_polling_has_an_authoritative_session_total(): void
    {
        $this->responses([new LlmResponse('Old response'), new LlmResponse('Measured', usage: new LlmTokenUsage(100, 10, 110))]);
        $user = User::factory()->create();
        $orchestrator = app(AiAgentOrchestrator::class);
        $old = $orchestrator->handle($user, 'First')['run'];
        $this->assertSame('unknown', $old->tokenUsageStatus());
        $run = $orchestrator->handle($user, 'Second', $old->session_id)['run'];
        $factory = app(AiChatResponseFactory::class);
        $first = $factory->completed($run);
        $second = $factory->completed($run);
        $this->assertSame($first['session_tokens'], $second['session_tokens']);
        $this->assertSame(110, $first['session_tokens']['total']);
        $this->assertSame('partial', $first['session_tokens']['status']);
    }

    public function test_workspace_view_renders_token_counter_pill_and_popover(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);

        $response = $this->actingAs($user)->get(route('ai.workspace', ['new' => 1]));

        $response->assertOk();
        $response->assertSee('data-ai-token-counter', false);
        $response->assertSee('data-ai-token-trigger', false);
        $response->assertSee('data-ai-token-in', false);
        $response->assertSee('data-ai-token-out', false);
        $response->assertSee('data-ai-token-popover', false);
    }

    public function test_workspace_pill_shows_session_totals_after_reload_and_keeps_last_turn_details(): void
    {
        $this->responses([
            new LlmResponse('First', usage: new LlmTokenUsage(100, 10)),
            new LlmResponse('Second', usage: new LlmTokenUsage(200, 20)),
        ]);
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        $orchestrator = app(AiAgentOrchestrator::class);
        $first = $orchestrator->handle($user, 'First')['run'];
        $orchestrator->handle($user, 'Second', $first->session_id);

        $response = $this->actingAs($user)->get(route('ai.workspace', ['session' => $first->session_id]));
        $response->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        foreach ([
            'in' => '300 in', 'out' => '30 out', 'detail-in' => '200',
            'detail-out' => '20', 'detail-total' => '220', 'session-total' => '330 tok',
        ] as $key => $expected) {
            $this->assertSame($expected, trim($xpath->query('//*[@data-ai-token-'.$key.']')->item(0)->textContent));
        }
        $response->assertSee('data-session-status="complete"', false);
        $response->assertSee('Pengiriman Terakhir');
    }

    public function test_orchestrator_accumulates_and_persists_token_usage_in_run_and_steps(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);

        $mockClient = new class implements LlmClientInterface
        {
            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                return new LlmResponse(
                    content: 'Jawaban pengujian token.',
                    usage: new LlmTokenUsage(
                        promptTokens: 120,
                        completionTokens: 35,
                        totalTokens: 155
                    )
                );
            }
        };

        $this->app->instance(LlmClientInterface::class, $mockClient);

        $result = app(AiAgentOrchestrator::class)->handle($user, 'Berapa token yang terpakai?');

        /** @var AiChatRun $run */
        $run = $result['run'];

        $this->assertSame('completed', $run->status);
        $this->assertSame(120, $run->prompt_tokens);
        $this->assertSame(35, $run->completion_tokens);
        $this->assertSame(155, $run->total_tokens);

        // Verify tokenUsage helper
        $usage = $run->tokenUsage();
        $this->assertSame(120, $usage->promptTokens);
        $this->assertSame(35, $usage->completionTokens);
        $this->assertSame(155, $usage->totalTokens);

        // Verify step-level metadata has tokens
        $step = $run->steps()->where('kind', 'reasoning_summary')->first();
        $this->assertNotNull($step);
        $this->assertIsArray($step->public_metadata['tokens'] ?? null);
        $this->assertSame(120, $step->public_metadata['tokens']['prompt']);
        $this->assertSame(35, $step->public_metadata['tokens']['completion']);
        $this->assertSame(155, $step->public_metadata['tokens']['total']);

        // Verify AiChatResponseFactory output
        $responseFactory = app(AiChatResponseFactory::class);
        $completedResponse = $responseFactory->completed($run);

        $this->assertSame('success', $completedResponse['status']);
        $this->assertArrayHasKey('tokens', $completedResponse['run']);
        $this->assertSame([
            'prompt' => 120,
            'completion' => 35,
            'total' => 155,
            'status' => 'complete',
        ], $completedResponse['run']['tokens']);
    }

    public function test_run_status_endpoint_returns_tokens_when_completed(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);

        $mockClient = new class implements LlmClientInterface
        {
            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                return new LlmResponse(
                    content: 'Respons dengan token.',
                    usage: new LlmTokenUsage(
                        promptTokens: 250,
                        completionTokens: 80,
                        totalTokens: 330
                    )
                );
            }
        };

        $this->app->instance(LlmClientInterface::class, $mockClient);

        $result = app(AiAgentOrchestrator::class)->handle($user, 'Hitung token saya');
        $run = $result['run'];

        $response = $this->actingAs($user)->getJson(route('ai.runs.show', ['run' => $run->id]));

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('run.tokens.prompt', 250);
        $response->assertJsonPath('run.tokens.completion', 80);
        $response->assertJsonPath('run.tokens.total', 330);
    }

    public function test_orchestrator_accumulates_token_usage_for_subagent_delegation(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);

        $responses = [
            // Round 1: Model decides to call coding tool
            new LlmResponse(
                content: null,
                toolCalls: [
                    new LlmToolCall('call_1', 'delegate_code_generation', [
                        'language' => 'html',
                        'runtime' => 'browser',
                        'files' => ['index.html'],
                        'requirements' => ['Portfolio standalone'],
                        'acceptance_criteria' => ['Sederhana'],
                    ]),
                ],
                usage: new LlmTokenUsage(promptTokens: 100, completionTokens: 30, totalTokens: 130)
            ),
            // Sub-agent call: Coding agent generates code directly
            new LlmResponse(
                content: "```html\n<!DOCTYPE html><html><body>Hi</body></html>\n```",
                usage: new LlmTokenUsage(promptTokens: 200, completionTokens: 80, totalTokens: 280)
            ),
        ];

        $mockClient = new class($responses) implements LlmClientInterface
        {
            public function __construct(private array $responses) {}

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $resp = array_shift($this->responses);
                if (! $resp) {
                    throw new \RuntimeException('No response in queue');
                }

                return $resp;
            }
        };

        $this->app->instance(LlmClientInterface::class, $mockClient);

        $result = app(AiAgentOrchestrator::class)->handle($user, 'Buatkan portofolio sederhana');
        $run = $result['run'];

        $this->assertSame('completed', $run->status);
        // Prompt tokens: 100 (round 1 decision) + 200 (sub-agent coding) = 300
        $this->assertSame(300, $run->prompt_tokens);
        // Completion tokens: 30 + 80 = 110
        $this->assertSame(110, $run->completion_tokens);
        // Total tokens: 130 + 280 = 410
        $this->assertSame(410, $run->total_tokens);

        // Step-level token check
        $toolStep = $run->steps()->where('kind', 'tool_call')->first();
        $this->assertNotNull($toolStep);
        $this->assertIsArray($toolStep->public_metadata['tokens'] ?? null);
        $this->assertSame(200, $toolStep->public_metadata['tokens']['prompt']);
        $this->assertSame(80, $toolStep->public_metadata['tokens']['completion']);
        $this->assertSame(280, $toolStep->public_metadata['tokens']['total']);
    }

    public function test_orchestrator_accumulates_token_usage_across_multi_turn_tool_rounds(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);

        $responses = [
            // Round 1: Model issues tool call
            new LlmResponse(
                content: null,
                toolCalls: [
                    new LlmToolCall('call_lookup', 'lookup_info', []),
                ],
                usage: new LlmTokenUsage(promptTokens: 120, completionTokens: 40, totalTokens: 160)
            ),
            // Round 2: Model evaluates tool result and outputs final answer
            new LlmResponse(
                content: 'Ini adalah hasil setelah memproses data alat.',
                usage: new LlmTokenUsage(promptTokens: 280, completionTokens: 60, totalTokens: 340)
            ),
        ];

        $mockClient = new class($responses) implements LlmClientInterface
        {
            public function __construct(private array $responses) {}

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $resp = array_shift($this->responses);
                if (! $resp) {
                    throw new \RuntimeException('No response in queue');
                }

                return $resp;
            }
        };

        $this->app->instance(LlmClientInterface::class, $mockClient);

        $result = app(AiAgentOrchestrator::class)->handle($user, 'Tolong cek data dengan alat');
        $run = $result['run'];

        $this->assertSame('completed', $run->status);
        // Accumulated prompt tokens: 120 (round 1 decision) + 280 (round 2 final response) = 400
        $this->assertSame(400, $run->prompt_tokens);
        // Accumulated completion tokens: 40 (round 1 tool call) + 60 (round 2 final response) = 100
        $this->assertSame(100, $run->completion_tokens);
        // Accumulated total tokens: 160 + 340 = 500
        $this->assertSame(500, $run->total_tokens);
    }
}
