<?php

namespace Tests\Feature\Ai;

use App\Models\AiChatRunStep;
use App\Models\User;
use App\Models\UserAiAssistant;
use App\Services\Ai\AiAgentOrchestrator;
use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\DTOs\LlmResponse;
use App\Services\Ai\DTOs\LlmToolCall;
use App\Services\Ai\Exceptions\AiProviderException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiCodingDelegationTest extends TestCase
{
    use RefreshDatabase;

    private function client(array $responses): object
    {
        $client = new class($responses) implements LlmClientInterface
        {
            public array $requests = [];

            public function __construct(private array $responses) {}

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $this->requests[] = compact('messages', 'tools', 'systemInstruction', 'options');

                $response = array_shift($this->responses) ?? throw new \LogicException('Unexpected extra inference');
                if ($response instanceof \Throwable) {
                    throw $response;
                }

                return $response;
            }
        };
        $this->app->instance(LlmClientInterface::class, $client);

        return $client;
    }

    private function delegation(array $overrides = []): LlmResponse
    {
        return new LlmResponse(null, toolCalls: [new LlmToolCall('code', 'delegate_code_generation', array_replace([
            'language' => 'html', 'runtime' => 'browser', 'files' => ['index.html'],
            'requirements' => ['Portfolio standalone dengan tema gelap'],
            'acceptance_criteria' => ['Responsif dan dibuka langsung di browser'],
        ], $overrides))]);
    }

    public function test_informal_follow_up_keeps_main_context_but_coder_only_receives_brief(): void
    {
        $client = $this->client([
            new LlmResponse('Bisa, portofolio statis. Mau standalone?'),
            $this->delegation(), new LlmResponse("```html\n<html><body>Portfolio</body></html>\n```"),
        ]);
        $user = User::factory()->create();
        $first = app(AiAgentOrchestrator::class)->handle($user, 'pengen bikin web statis buat porto sih');
        $result = app(AiAgentOrchestrator::class)->handle($user, 'stand alone aja gasi', $first['session']->id);

        $mainText = implode(' ', array_map(fn ($message) => $message->content, $client->requests[1]['messages']));
        $this->assertStringContainsString('web statis buat porto', $mainText);
        $this->assertStringContainsString('stand alone aja gasi', $mainText);
        $this->assertCount(1, $client->requests[2]['messages']);
        $payload = $client->requests[2]['messages'][0]->content;
        $this->assertStringContainsString('Portfolio standalone', $payload);
        $this->assertStringNotContainsString('Mau standalone?', $payload);
        $this->assertSame([], $client->requests[2]['tools']);
        $this->assertCount(3, $client->requests);
        $step = $result['run']->steps->firstWhere('tool_name', 'delegate_code_generation');
        $this->assertSame('html', $step->private_payload['coding_brief']['language']);
    }

    public function test_revision_receives_only_selected_ancestor_artifact(): void
    {
        $code = "```html\n<html><body>Original portfolio</body></html>\n```";
        $client = $this->client([$this->delegation(), new LlmResponse($code)]);
        $user = User::factory()->create();
        $first = app(AiAgentOrchestrator::class)->handle($user, 'buat porto');
        $client = $this->client([
            $this->delegation(['source_message_id' => $first['assistant_message']->id]),
            new LlmResponse("```html\n<html><body>Updated</body></html>\n```"),
        ]);
        app(AiAgentOrchestrator::class)->handle($user, 'ganti tema terang', $first['session']->id);

        $this->assertStringContainsString('Original portfolio', $client->requests[1]['messages'][0]->content);
        $this->assertCount(1, $client->requests[1]['messages']);
    }

    public function test_intent_plan_reaches_isolated_coder_and_static_review_is_private_without_extra_inference(): void
    {
        $client = $this->client([
            $this->delegation(['design_intent' => ['goal' => 'showcase', 'expression' => 'restrained',
                'audience' => 'UNTRUSTED_AUDIENCE_MARKER', 'accent_hex' => '#ffff00', 'assumptions' => ['Audiens belum diketahui']]]),
            new LlmResponse("```html\n<!doctype html><html><body><a href='#missing'>Project</a></body></html>\n```"),
        ]);
        $result = app(AiAgentOrchestrator::class)->handle(User::factory()->create(), 'buat portfolio saya');
        $this->assertCount(2, $client->requests);
        $this->assertSame([], $client->requests[1]['tools']);
        $this->assertStringContainsString('design_plan', $client->requests[1]['messages'][0]->content);
        $this->assertStringContainsString('bukti karya', $client->requests[1]['messages'][0]->content);
        $this->assertStringNotContainsString('on-accent/accent', $client->requests[1]['messages'][0]->content);
        $this->assertStringNotContainsString('UNTRUSTED_AUDIENCE_MARKER', $client->requests[1]['systemInstruction']);
        $this->assertStringNotContainsString('UNTRUSTED_AUDIENCE_MARKER', $result['reply']);
        $step = $result['run']->steps->firstWhere('tool_name', 'delegate_code_generation');
        $this->assertSame('showcase', $step->private_payload['coding_brief']['design_intent']['goal']);
        $this->assertSame('unverified', $step->private_payload['design_review']['render']);
        $this->assertSame('fail', $step->private_payload['design_review']['checks']['broken_fragment_links']['status']);
        $this->assertArrayNotHasKey('design_review', $step->public_metadata);
        $this->assertSame('completed', $result['run']->status);
        $next = $this->client([new LlmResponse('Bisa, bagian mana yang mau disesuaikan?')]);
        app(AiAgentOrchestrator::class)->handle($result['session']->user, 'mau diskusi layout dulu', $result['session']->id);
        $history = implode(' ', array_map(fn ($message) => (string) $message->content, $next->requests[0]['messages']));
        $this->assertStringContainsString('design_intent', $history);
        $this->assertStringNotContainsString('design_review', $history);
        $this->assertStringNotContainsString('broken_fragment_links', $history);
    }

    public function test_source_from_another_user_is_rejected_before_coder_inference(): void
    {
        $this->client([$this->delegation(), new LlmResponse("```html\n<html>Private other user artifact</html>\n```")]);
        $foreign = app(AiAgentOrchestrator::class)->handle(User::factory()->create(), 'buat web');
        $client = $this->client([
            $this->delegation(['source_message_id' => $foreign['assistant_message']->id]),
            new LlmResponse('Pilih kode sumber pada percakapan ini dahulu.'),
        ]);
        $result = app(AiAgentOrchestrator::class)->handle(User::factory()->create(), 'revisi web');

        $this->assertCount(2, $client->requests);
        $this->assertSame('failed', $result['run']->steps->firstWhere('tool_name', 'delegate_code_generation')->status);
        $this->assertStringNotContainsString('Private other user artifact', json_encode($client->requests));
    }

    public function test_main_implementation_bypass_is_discarded_and_delegated_once(): void
    {
        $client = $this->client([
            new LlmResponse("```html\n<!doctype html><html>Bypassed source</html>\n```"),
            $this->delegation(), new LlmResponse("```html\n<html>Coder source</html>\n```"),
        ]);
        $result = app(AiAgentOrchestrator::class)->handle(User::factory()->create(), 'bikinin web porto dong');

        $this->assertSame("```html\n<html>Coder source</html>\n```", $result['reply']);
        $this->assertStringNotContainsString('Bypassed source', json_encode($client->requests[1]['messages']));
        $this->assertSame(['delegate_code_generation'], array_column($client->requests[1]['tools'], 'name'));
        $this->assertCount(3, $client->requests);
    }

    public function test_explicit_topic_shift_is_not_forced_into_coding(): void
    {
        $client = $this->client([
            $this->delegation(), new LlmResponse("```html\n<html>Portfolio</html>\n```"),
            new LlmResponse('Autentikasi memverifikasi identitas, otorisasi menentukan izin.'),
        ]);
        $user = User::factory()->create();
        $first = app(AiAgentOrchestrator::class)->handle($user, 'buat web porto');
        $result = app(AiAgentOrchestrator::class)->handle($user, 'ganti topik, jelaskan autentikasi dan otorisasi tanpa kode', $first['session']->id);

        $this->assertCount(3, $client->requests);
        $this->assertFalse($result['run']->steps->contains('tool_name', 'delegate_code_generation'));
    }

    public function test_edit_cannot_select_an_artifact_from_the_discarded_sibling_branch(): void
    {
        $this->client([$this->delegation(), new LlmResponse("```html\n<html>Old branch source</html>\n```")]);
        $user = User::factory()->create();
        $first = app(AiAgentOrchestrator::class)->handle($user, 'buat web porto');
        $client = $this->client([
            $this->delegation(['source_message_id' => $first['assistant_message']->id]),
            new LlmResponse('Kode tersebut bukan sumber pada cabang ini.'),
        ]);
        $result = app(AiAgentOrchestrator::class)->edit($user, $first['user_message']->id, 'buat web coffee shop');

        $this->assertCount(2, $client->requests);
        $this->assertSame('failed', $result['run']->steps->firstWhere('tool_name', 'delegate_code_generation')->status);
        $this->assertStringNotContainsString('Old branch source', json_encode($client->requests));
    }

    public function test_repeated_boundary_violation_fails_without_committing_bypassed_code(): void
    {
        $bypass = new LlmResponse("```html\n<html>Untrusted bypass</html>\n```");
        $client = $this->client([$bypass, $bypass]);
        $user = User::factory()->create();
        try {
            app(AiAgentOrchestrator::class)->handle($user, 'buat web portfolio');
            $this->fail('Repeated boundary violation must fail.');
        } catch (AiProviderException $exception) {
            $this->assertSame('coding_agent_invalid_response', $exception->errorCode);
        }

        $this->assertCount(2, $client->requests);
        $this->assertDatabaseMissing('ai_chat_messages', ['role' => 'assistant', 'content' => $bypass->content]);
        $this->assertDatabaseHas('ai_chat_runs', ['status' => 'failed', 'retryable' => true]);
    }

    public function test_failed_coder_retains_private_brief_and_promotes_bounded_run_deadline(): void
    {
        $user = User::factory()->create();
        UserAiAssistant::query()->create([
            'user_id' => $user->id, 'assistant_name' => 'PolyBot',
            'personality_tone' => 'friendly_peer', 'thinking_effort' => 'low',
        ]);
        $client = $this->client([$this->delegation(), AiProviderException::timeout()]);
        try {
            app(AiAgentOrchestrator::class)->handle($user, 'buat HTML standalone portfolio');
            $this->fail('Expected provider timeout');
        } catch (AiProviderException $exception) {
            $this->assertSame('provider_timeout', $exception->errorCode);
        }
        $this->assertSame(45, $client->requests[0]['options']->timeoutSeconds);
        $this->assertSame(180, $client->requests[1]['options']->timeoutSeconds);
        $this->assertEqualsWithDelta(150, $client->requests[1]['options']->deadlineAt - $client->requests[0]['options']->deadlineAt, 0.001);
        $this->assertNotNull($client->requests[1]['options']->ensureActive);
        $step = AiChatRunStep::query()->where('tool_name', 'delegate_code_generation')->firstOrFail();
        $this->assertSame('failed', $step->status);
        $this->assertSame('html', $step->private_payload['coding_brief']['language']);
        $this->assertDatabaseMissing('ai_chat_messages', ['role' => 'assistant']);
        $this->assertSame('low', $step->private_payload['execution_policy']['thinking_effort']);
        $this->assertSame(180, $step->private_payload['execution_policy']['request_timeout_seconds']);
        $this->assertSame(240, $step->private_payload['execution_policy']['run_timeout_seconds']);
    }
}
