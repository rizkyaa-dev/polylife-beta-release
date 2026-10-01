<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\DTOs\LlmResponse;
use App\Services\Ai\Enums\ThinkingEffort;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\LlmInference;
use App\Services\Ai\Science\Client\ClientComputationAgent;
use App\Services\Ai\Science\Client\ClientComputationContract;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ClientComputationAgentTest extends TestCase
{
    private function client(bool $repair): LlmClientInterface
    {
        return new class($repair) implements LlmClientInterface
        {
            public array $envelopes = [];

            public function __construct(private bool $repair) {}

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $this->envelopes[] = json_decode($messages[0]->content, true);
                $program = ['source' => 'function compute(i){return {values:{value:i.x+1},checks:[{name:"inverse",passed:true}]}}', 'inputs' => ['x' => 2]];
                if ($this->repair && count($this->envelopes) === 2) {
                    $program['checks'] = ['inverse relation'];
                }

                return new LlmResponse(json_encode(['status' => 'ready', 'model' => 'Addition', 'assumptions' => [], 'units' => [], 'program' => $program]));
            }
        };
    }

    public function test_one_structural_repair_preserves_original_request_without_kernel(): void
    {
        $client = $this->client(true);
        $agent = new ClientComputationAgent(new LlmInference($client), app(ClientComputationContract::class));
        $request = ['problem' => 'Add one', 'original_problem' => 'Add 1 to 2; output value'];
        $plan = $agent->planLocal($request, new LlmRequestOptions(ThinkingEffort::Low, 30));
        $this->assertSame('ready', $plan['status']);
        $this->assertSame('kernel_disabled', $plan['routing_reason']);
        $this->assertSame(2, $plan['planning_attempts']);
        $this->assertCount(2, $client->envelopes);
        $this->assertSame($request, $client->envelopes[1]['request']);
        $this->assertArrayHasKey('program.checks', $client->envelopes[1]['validation_feedback']);
    }

    public function test_repeated_invalid_proposal_is_terminal_after_one_repair(): void
    {
        $client = $this->client(false);
        $agent = new ClientComputationAgent(new LlmInference($client), app(ClientComputationContract::class));
        try {
            $agent->planLocal(['problem' => 'Add one'], new LlmRequestOptions(ThinkingEffort::Low, 30));
            $this->fail('Repeated invalid proposal must be rejected.');
        } catch (ValidationException) {
            $this->assertCount(2, $client->envelopes);
        }
    }

    public function test_expired_budget_does_not_call_provider(): void
    {
        $client = $this->client(true);
        $agent = new ClientComputationAgent(new LlmInference($client), app(ClientComputationContract::class));
        try {
            $agent->planLocal(['problem' => 'Add one'], new LlmRequestOptions(ThinkingEffort::Low, 30, 8192, hrtime(true) / 1e9 - 1));
            $this->fail('Expired budget must fail.');
        } catch (AiProviderException) {
            $this->assertCount(0, $client->envelopes);
        }
    }

    public function test_markdown_code_fences_are_parsed_successfully(): void
    {
        $client = new class implements LlmClientInterface
        {
            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $json = json_encode([
                    'status' => 'ready',
                    'model' => 'Arithmetic',
                    'assumptions' => [],
                    'units' => [],
                    'program' => [
                        'source' => 'function compute(i){return {values:{value:i.a*i.b/i.c},checks:[{name:"c",passed:true}]}}',
                        'inputs' => ['a' => 78, 'b' => 985, 'c' => 1.857],
                        'checks' => ['check arithmetic'],
                    ],
                ]);

                return new LlmResponse("```json\n{$json}\n```");
            }
        };

        $agent = new ClientComputationAgent(new LlmInference($client), app(ClientComputationContract::class));
        $plan = $agent->planLocal(['problem' => 'Calculate arithmetic'], new LlmRequestOptions(ThinkingEffort::Low, 30));

        $this->assertSame('ready', $plan['status']);
        $this->assertSame(1, $plan['planning_attempts']);
    }

    public function test_malformed_json_on_first_attempt_is_repaired_on_second_attempt(): void
    {
        $client = new class implements LlmClientInterface
        {
            public array $envelopes = [];

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $this->envelopes[] = json_decode($messages[0]->content, true);

                if (count($this->envelopes) === 1) {
                    return new LlmResponse('Invalid raw { json without closing brace');
                }

                $json = json_encode([
                    'status' => 'ready',
                    'model' => 'Arithmetic',
                    'assumptions' => [],
                    'units' => [],
                    'program' => [
                        'source' => 'function compute(i){return {values:{value:42},checks:[{name:"c",passed:true}]}}',
                        'inputs' => [],
                        'checks' => ['check math'],
                    ],
                ]);

                return new LlmResponse($json);
            }
        };

        $agent = new ClientComputationAgent(new LlmInference($client), app(ClientComputationContract::class));
        $plan = $agent->planLocal(['problem' => 'Calculate arithmetic'], new LlmRequestOptions(ThinkingEffort::Low, 30));

        $this->assertSame('ready', $plan['status']);
        $this->assertSame(2, $plan['planning_attempts']);
        $this->assertCount(2, $client->envelopes);
        $this->assertArrayHasKey('program', $client->envelopes[1]['validation_feedback']);
        $this->assertSame(['Invalid client-computation JSON.'], $client->envelopes[1]['validation_feedback']['program']);
    }
}
