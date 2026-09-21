<?php

namespace Tests\Feature\Ai;

use App\Jobs\ExecuteScienceComputation;
use App\Jobs\ProcessAiChatRun;
use App\Models\AiChatRun;
use App\Models\AiScienceExecution;
use App\Models\User;
use App\Models\UserAiAssistant;
use App\Services\Ai\AiAgentOrchestrator;
use App\Services\Ai\AiRunStateManager;
use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\DTOs\LlmResponse;
use App\Services\Ai\DTOs\LlmToolCall;
use App\Services\Ai\Science\ScienceExecutionBroker;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\CallQueuedHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class AiScienceExecutionTest extends TestCase
{
    use RefreshDatabase;

    private function pending(bool $useQueuedJob = false, string $effort = 'low', bool $withOutputs = false): array
    {
        Queue::fake();
        config(['services.ai_science_browser_enabled' => true, 'services.ai_queue_connection' => 'database']);
        $user = User::factory()->create(['account_status' => 'active', 'role' => 'user', 'email_verified_at' => now()]);
        UserAiAssistant::create(['user_id' => $user->id, 'assistant_name' => 'Test', 'personality_tone' => 'friendly_peer', 'thinking_effort' => $effort]);
        $client = new class($withOutputs) implements LlmClientInterface
        {
            public function __construct(private readonly bool $withOutputs) {}

            public array $requests = [];

            public array $claims = [];

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $this->requests[] = compact('messages', 'tools', 'systemInstruction', 'options');
                $this->claims[] = AiChatRun::firstOrFail()->claim_token;

                return match (count($this->requests)) {
                    1 => new LlmResponse(null, [new LlmToolCall('science1', 'delegate_science_problem', ['problem' => 'PRIVATE_MODEL: Solve x+2y=5 and 3x+4y=11.'])]),
                    2 => new LlmResponse(json_encode(['status' => 'ready', 'model' => 'Unknown order x,y.', 'assumptions' => [], 'units' => [],
                        'solver' => 'linear_system', 'inputs' => array_merge(['matrix' => [[1, 2], [3, 4]], 'rhs' => [5, 11]],
                            $this->withOutputs ? ['dimensions' => ['variables' => array_fill(0, 2, array_fill(0, 7, 0)),
                                'rhs' => array_fill(0, 2, array_fill(0, 7, 0)), 'matrix' => array_fill(0, 2, array_fill(0, 2, array_fill(0, 7, 0)))],
                                'outputs' => [['name' => 'total', 'dimension' => array_fill(0, 7, 0),
                                    'expression' => ['op' => 'add', 'args' => [['op' => 'var', 'name' => 'r0'], ['op' => 'var', 'name' => 'r1']]]]]]
                                : [])])),
                    3 => new LlmResponse('x=1, y=2. Physical formulation unverified.'),
                    default => throw new \LogicException('Planning or final inference was duplicated.'),
                };
            }
        };
        $this->app->instance(LlmClientInterface::class, $client);
        $orchestrator = app(AiAgentOrchestrator::class);
        $turn = $orchestrator->enqueue($user, 'Solve equations.', requestId: (string) Str::uuid(), scienceClient: true);
        $job = new ProcessAiChatRun($turn['run']->id);
        if ($useQueuedJob) {
            $job->handle($orchestrator);
        } else {
            $result = $orchestrator->processRun($turn['run']->id);
            $this->assertSame('awaiting_science', $result['phase']);
        }
        $execution = AiScienceExecution::firstOrFail();

        return [$user, $client, $turn['run']->fresh(), $execution, $job];
    }

    public function test_suspend_client_submit_server_replay_resume_does_not_repeat_planning(): void
    {
        [$user, $client, $run, $ticket] = $this->pending();
        $broker = app(ScienceExecutionBroker::class);
        $this->assertCount(2, $client->requests);
        $this->assertSame('running', $ticket->step->status);
        $this->assertStringNotContainsString('PRIVATE_MODEL', $ticket->getRawOriginal('private_payload'));
        $claim = $broker->claim($ticket, (string) Str::uuid());
        $submission = ['attempt' => $claim['attempt'], 'token' => $claim['token'], 'result' => ['status' => 'computed', 'solution' => [999, 999]]];
        $broker->submit($ticket, $submission);
        $broker->submit($ticket, $submission);
        Queue::assertPushed(ExecuteScienceComputation::class, 2); // Delayed fallback plus one verification.
        $broker->execute($ticket->id);
        $this->assertSame('ready', $ticket->fresh()->status);
        $this->assertSame([1, 2], $ticket->fresh()->private_payload['result']['result']['solution']);
        $orchestrator = app(AiAgentOrchestrator::class);
        $completed = $orchestrator->processRun($run->id);
        $this->assertSame('completed', $completed['run']->status);
        $this->assertCount(3, $client->requests);
        $tool = $client->requests[2]['messages'][2];
        $this->assertSame('science1', $tool->toolResult['call_id']);
        $this->assertSame([1, 2], $tool->toolResult['result']['result']['solution']);
        $this->assertNull($orchestrator->processRun($run->id));
        $broker->execute($ticket->id); // Late delayed delivery cannot compute again.
        $this->assertSame(1, $ticket->fresh()->server_attempts);
    }

    public function test_expired_client_lease_falls_back_without_a_callback(): void
    {
        [, $client, $run, $ticket] = $this->pending();
        $broker = app(ScienceExecutionBroker::class);
        $broker->execute($ticket->id);
        $this->assertSame('waiting_client', $ticket->fresh()->status);
        $this->travel(21)->seconds();
        $broker->execute($ticket->id);
        $this->assertSame('ready', $ticket->fresh()->status);
        app(AiAgentOrchestrator::class)->processRun($run->id);
        $this->assertCount(3, $client->requests);
    }

    public function test_disabling_kernel_prevents_replay_of_previously_queued_registered_ticket(): void
    {
        [, , , $ticket] = $this->pending();
        config(['services.ai_science_kernel_enabled' => false]);
        $this->travel(21)->seconds();
        app(ScienceExecutionBroker::class)->execute($ticket->id);
        $result = $ticket->fresh()->private_payload['result'];
        $this->assertSame('unsupported', $result['status']);
        $this->assertNull($result['result']);
        $this->assertNull($result['execution']['authoritative_runner']);
        $this->assertSame('none', $result['execution']['verification_method']);
        $this->assertSame('not_executed', $ticket->step->fresh()->public_metadata['execution_phase']);
    }

    public function test_disabling_kernel_also_prevents_issuing_legacy_browser_kernel_program(): void
    {
        [, , , $ticket] = $this->pending();
        config(['services.ai_science_kernel_enabled' => false]);
        $this->expectException(ConflictHttpException::class);
        app(ScienceExecutionBroker::class)->claim($ticket, (string) Str::uuid());
    }

    public function test_projected_output_is_replayed_not_trusted_from_the_browser(): void
    {
        [, $client, $run, $ticket] = $this->pending(withOutputs: true);
        $broker = app(ScienceExecutionBroker::class);
        $claim = $broker->claim($ticket, (string) Str::uuid());
        $this->assertSame('total', $claim['inputs']['outputs'][0]['name']);
        $broker->submit($ticket, ['attempt' => $claim['attempt'], 'token' => $claim['token'],
            'result' => ['status' => 'computed', 'outputs' => [['name' => 'total', 'value' => 999]]]]);
        $broker->execute($ticket->id);
        $this->assertSame(3, $ticket->fresh()->private_payload['result']['result']['outputs'][0]['value']);
        app(AiAgentOrchestrator::class)->processRun($run->id);
        $this->assertSame(3, $client->requests[2]['messages'][2]->toolResult['result']['result']['outputs'][0]['value']);
        $this->assertCount(3, $client->requests);
        $this->assertSame('completed', $run->fresh()->status);
    }

    public function test_only_one_browser_can_claim_and_late_result_is_rejected(): void
    {
        [, , , $ticket] = $this->pending();
        $broker = app(ScienceExecutionBroker::class);
        $id = (string) Str::uuid();
        $claim = $broker->claim($ticket, $id);
        $this->assertSame($claim, $broker->claim($ticket, $id));
        try {
            $broker->claim($ticket, (string) Str::uuid());
            $this->fail('A second browser cannot own the active lease.');
        } catch (ConflictHttpException) {
            $this->assertSame(1, $ticket->fresh()->attempt);
        }
        $this->travel(21)->seconds();
        $broker->execute($ticket->id);
        $this->expectException(ConflictHttpException::class);
        $broker->submit($ticket, ['attempt' => $claim['attempt'], 'token' => $claim['token'], 'failure' => 'timeout']);
    }

    public function test_overall_deadline_is_not_renewed_on_resume(): void
    {
        [, $client, $run, $ticket] = $this->pending();
        $this->travel(21)->seconds();
        app(ScienceExecutionBroker::class)->execute($ticket->id);
        app(AiAgentOrchestrator::class)->processRun($run->id);
        $before = $client->requests[1]['options']->deadlineAt;
        $after = $client->requests[2]['options']->deadlineAt;
        $this->assertLessThan($before - 19, $after);
        $this->assertEqualsWithDelta(21, $before - $after, 1);
    }

    public function test_computation_at_the_exact_deadline_is_rejected(): void
    {
        [, $client, $run, $ticket] = $this->pending();
        $this->travelTo($ticket->deadline_at);
        app(ScienceExecutionBroker::class)->execute($ticket->id);
        $this->assertSame('failed', $run->fresh()->status);
        $this->assertSame('science_execution_expired', $run->fresh()->error_code);
        $this->assertSame(0, $ticket->fresh()->server_attempts);
        $this->assertCount(2, $client->requests);
    }

    public function test_recovery_at_the_exact_deadline_cannot_renew_the_ticket(): void
    {
        [, , $run, $ticket] = $this->pending();
        $this->travelTo($ticket->deadline_at);
        Queue::fake();
        app(ScienceExecutionBroker::class)->recover();
        $this->assertSame('failed', $run->fresh()->status);
        $this->assertNull($ticket->fresh()->private_payload);
        Queue::assertNothingPushed();
    }

    public function test_client_cannot_claim_at_the_exact_lease_expiration(): void
    {
        [, , $run, $ticket] = $this->pending();
        $this->travelTo($ticket->lease_expires_at);
        $broker = app(ScienceExecutionBroker::class);
        $this->assertNull($broker->offer($run));
        $this->expectException(ConflictHttpException::class);
        $broker->claim($ticket, (string) Str::uuid());
    }

    public function test_client_cannot_submit_at_the_exact_lease_expiration(): void
    {
        [, , , $ticket] = $this->pending();
        $broker = app(ScienceExecutionBroker::class);
        $claim = $broker->claim($ticket, (string) Str::uuid());
        $this->travelTo($ticket->lease_expires_at);
        $this->expectException(ConflictHttpException::class);
        $broker->submit($ticket, ['attempt' => $claim['attempt'], 'token' => $claim['token'], 'failure' => 'timeout']);
    }

    public function test_effort_changes_while_awaiting_compute_do_not_mutate_the_suspended_turn(): void
    {
        [$user, $client, $run, $ticket] = $this->pending();
        UserAiAssistant::where('user_id', $user->id)->update(['thinking_effort' => 'max']);
        $this->travel(21)->seconds();
        app(ScienceExecutionBroker::class)->execute($ticket->id);
        app(AiAgentOrchestrator::class)->processRun($run->id);
        $this->assertSame('low', $client->requests[2]['options']->thinkingEffort->value);
        $this->assertSame('completed', $run->fresh()->status);
    }

    public function test_heavy_resume_preserves_queue_class_even_after_user_selects_off(): void
    {
        [$user, , $run, $ticket] = $this->pending(effort: 'high');
        UserAiAssistant::where('user_id', $user->id)->update(['thinking_effort' => 'off']);
        $this->travel(21)->seconds();
        app(ScienceExecutionBroker::class)->execute($ticket->id);
        $jobs = Queue::pushed(ProcessAiChatRun::class);
        $this->assertCount(2, $jobs);
        foreach ($jobs as $delivery) {
            $this->assertSame(config('services.ai_heavy_queue', 'ai-heavy'), $delivery->queue);
        }
        $this->assertSame(2, $run->fresh()->dispatch_attempts);
    }

    public function test_cancelled_run_never_accepts_client_data_or_resumes(): void
    {
        [, $client, $run, $ticket] = $this->pending();
        $broker = app(ScienceExecutionBroker::class);
        $claim = $broker->claim($ticket, (string) Str::uuid());
        app(AiRunStateManager::class)->fail($run, 'user_cancelled', false);
        $broker->execute($ticket->id);
        $this->assertNull(app(AiAgentOrchestrator::class)->processRun($run->id));
        $this->assertCount(2, $client->requests);
        $this->expectException(ConflictHttpException::class);
        $broker->submit($ticket, ['attempt' => $claim['attempt'], 'token' => $claim['token'], 'failure' => 'cancelled']);
    }

    public function test_http_callback_and_claim_are_owner_scoped_and_bounded(): void
    {
        [$owner, , , $ticket] = $this->pending();
        $intruder = User::factory()->create(['account_status' => 'active', 'role' => 'user', 'email_verified_at' => now()]);
        $this->actingAs($intruder)->postJson(route('ai.science.claim', $ticket->id), ['client_id' => (string) Str::uuid()])->assertNotFound();
        $this->postJson(route('ai.science.submit', $ticket->id), [])->assertNotFound();
        $claim = $this->actingAs($owner)->postJson(route('ai.science.claim', $ticket->id), ['client_id' => (string) Str::uuid()])->assertOk();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $claim->json('cache_scope'));
        $this->assertSame(hash_hmac('sha256', 'science-browser-cache:user:'.$owner->id, (string) config('app.key')), $claim->json('cache_scope'));
        $this->postJson(route('ai.science.submit', $ticket->id), ['attempt' => 1, 'token' => $claim->json('token'),
            'result' => ['huge' => str_repeat('x', 40000)]])->assertStatus(413);
        $this->postJson(route('ai.science.submit', $ticket->id), ['attempt' => 1, 'token' => $claim->json('token'), 'failure' => 'unavailable'])
            ->assertAccepted();
    }

    public function test_recovery_of_missing_delayed_delivery_is_bounded_and_expiry_is_terminal(): void
    {
        [, , $run, $ticket] = $this->pending();
        $broker = app(ScienceExecutionBroker::class);
        $this->travel(21)->seconds();
        $this->assertSame(1, $broker->recover());
        $this->assertSame('queued_server', $ticket->fresh()->status);
        $this->assertSame(0, $broker->recover());
        $this->travel(250)->seconds();
        $broker->execute($ticket->id);
        $this->assertSame('failed', $run->fresh()->status);
        $this->assertSame('science_execution_expired', $run->fresh()->error_code);
    }

    public function test_reconstructed_compute_failure_uses_persistent_claim_not_runtime_fields(): void
    {
        [, , $run, $ticket] = $this->pending();
        $this->travel(21)->seconds();
        $job = new ExecuteScienceComputation($ticket->id);
        $serialized = serialize($job);
        $broker = app(ScienceExecutionBroker::class);
        try {
            $broker->execute($ticket->id, onClaim: function (): void {
                throw new \RuntimeException('Simulated worker crash immediately after claim.');
            }, claimToken: $job->claimToken);
            $this->fail('The simulated crash must interrupt execution.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated worker crash immediately after claim.', $exception->getMessage());
        }
        $this->assertSame($job->claimToken, $ticket->fresh()->claim_token);
        $this->assertArrayNotHasKey('claim_token', $ticket->fresh()->toArray());
        $stale = new ExecuteScienceComputation($ticket->id);
        app(CallQueuedHandler::class)->failed(['command' => serialize($stale)], new \RuntimeException('Stale delivery'), (string) Str::uuid());
        $this->assertSame('running', $run->fresh()->status);
        app(CallQueuedHandler::class)->failed(['command' => $serialized], new \RuntimeException('Current delivery'), (string) Str::uuid());
        $this->assertSame('failed', $run->fresh()->status);
        $this->assertSame('science_worker_failed', $run->fresh()->error_code);
        $this->assertNull($ticket->fresh()->private_payload);
    }

    public function test_reconstructed_main_failure_cannot_fail_another_claim(): void
    {
        [, , $run] = $this->pending();
        $job = new ProcessAiChatRun($run->id);
        $serialized = serialize($job);
        $run->update(['claim_token' => $job->claimToken, 'attempts' => 2]);
        $stale = new ProcessAiChatRun($run->id);
        app(CallQueuedHandler::class)->failed(['command' => serialize($stale)], new \RuntimeException('Stale delivery'), (string) Str::uuid());
        $this->assertSame('running', $run->fresh()->status);
        app(CallQueuedHandler::class)->failed(['command' => $serialized], new \RuntimeException('Current delivery'), (string) Str::uuid());
        $this->assertSame('failed', $run->fresh()->status);
        $this->assertSame('worker_failed', $run->fresh()->error_code);
    }

    public function test_suspension_releases_the_original_main_job_claim(): void
    {
        [, $client, $run, $ticket, $original] = $this->pending(true);
        $broker = app(ScienceExecutionBroker::class);
        $this->assertSame([$original->claimToken, $original->claimToken], $client->claims);
        $this->assertNull($run->claim_token);
        app(CallQueuedHandler::class)->failed(['command' => serialize($original)], new \RuntimeException('Late failure after suspension'), (string) Str::uuid());
        $this->assertSame('running', $run->fresh()->status);
        $this->travel(21)->seconds();
        $broker->execute($ticket->id);
        $this->assertNull($run->fresh()->claim_token);
        $this->assertTrue($run->fresh()->last_dispatched_at->isAfter($run->last_dispatched_at));
        $resume = new ProcessAiChatRun($run->id);
        $resume->handle(app(AiAgentOrchestrator::class));
        $this->assertSame($resume->claimToken, $client->claims[2]);
        $this->assertNotSame($original->claimToken, $resume->claimToken);
        $this->assertSame('completed', $run->fresh()->status);
    }

    public function test_failure_from_recovered_compute_claim_cannot_fail_its_successor(): void
    {
        [, , $run, $ticket] = $this->pending();
        $broker = app(ScienceExecutionBroker::class);
        $this->travel(21)->seconds();
        $first = new ExecuteScienceComputation($ticket->id);
        $second = new ExecuteScienceComputation($ticket->id);
        foreach ([$first, $second] as $index => $job) {
            try {
                $broker->execute($ticket->id, onClaim: function (): void {
                    throw new \RuntimeException('Crash');
                }, claimToken: $job->claimToken);
                $this->fail('Crash must interrupt execution.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('Crash', $exception->getMessage());
            }
            $this->assertSame($index + 1, $ticket->fresh()->server_attempts);
            if ($index === 0) {
                $this->travel(46)->seconds();
                $broker->recover();
            }
        }
        app(CallQueuedHandler::class)->failed(['command' => serialize($first)], new \RuntimeException('Old worker eventually exits'), (string) Str::uuid());
        $this->assertSame('running', $run->fresh()->status);
        $this->assertSame($second->claimToken, $ticket->fresh()->claim_token);
        $this->travel(46)->seconds();
        $broker->recover();
        $broker->execute($ticket->id);
        $this->assertSame(2, $ticket->fresh()->server_attempts);
        $this->assertSame('failed', $run->fresh()->status);
        $this->assertNull($ticket->fresh()->private_payload);
    }

    public function test_failure_callback_for_a_replaced_ticket_cannot_fail_the_current_run(): void
    {
        [, , $run, $ticket] = $this->pending();
        $broker = app(ScienceExecutionBroker::class);
        $this->travel(21)->seconds();
        $job = new ExecuteScienceComputation($ticket->id);
        try {
            $broker->execute($ticket->id, onClaim: function (): void {
                throw new \RuntimeException('Interrupt claimed execution before computation.');
            }, claimToken: $job->claimToken);
            $this->fail('Claim interruption must be observed.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Interrupt claimed execution before computation.', $exception->getMessage());
        }
        // Fault injection: a replacement becomes authoritative before the old
        // process reports failure. Its token still matches only the old ticket.
        $step = $ticket->step->replicate();
        $step->sequence = $run->steps()->max('sequence') + 1;
        $step->save();
        $replacement = $ticket->fresh()->replicate();
        $replacement->fill(['step_id' => $step->id, 'status' => 'waiting_client', 'claim_token' => null,
            'attempt' => 0, 'server_attempts' => 0]);
        $replacement->save();
        $run->update(['science_execution_id' => $replacement->id]);
        app(CallQueuedHandler::class)->failed(['command' => serialize($job)], new \RuntimeException('Old ticket failed'), (string) Str::uuid());
        $this->assertSame('running', $run->fresh()->status);
        $this->assertSame($replacement->id, $run->fresh()->science_execution_id);
        $this->assertSame('waiting_client', $replacement->fresh()->status);
        $this->assertNotNull($replacement->fresh()->private_payload);
    }

    public function test_ticket_replacement_between_final_guard_and_publication_is_fenced(): void
    {
        [, , $run, $ticket] = $this->pending();
        $step = $ticket->step->replicate();
        $step->sequence = $run->steps()->max('sequence') + 1;
        $step->save();
        $replacement = $ticket->replicate();
        $replacement->fill(['step_id' => $step->id, 'status' => 'waiting_client']);
        $replacement->save();
        $newClaim = (string) Str::uuid();
        $observations = 0;
        $this->travel(21)->seconds();
        Queue::fake();
        app(ScienceExecutionBroker::class)->execute($ticket->id, onClaim: function () use ($run, $replacement, $newClaim, &$observations): void {
            // Fault injection after the final guard's run snapshot was read,
            // before publication acquires its transaction lock.
            DB::listen(function (QueryExecuted $query) use ($run, $replacement, $newClaim, &$observations): void {
                if (preg_match('/^select \* from ["`]ai_science_executions["`]/', $query->sql)
                    && ! str_contains($query->sql, 'run_id') && ++$observations === 2) {
                    $run->update(['science_execution_id' => $replacement->id, 'claim_token' => $newClaim]);
                }
            });
        });
        $this->assertGreaterThanOrEqual(2, $observations);
        $this->assertSame($replacement->id, $run->fresh()->science_execution_id);
        $this->assertSame($newClaim, $run->fresh()->claim_token);
        $this->assertSame('running_server', $ticket->fresh()->status);
        $this->assertSame('waiting_client', $replacement->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_resume_cannot_claim_at_the_exact_science_deadline(): void
    {
        [, $client, $run, $ticket] = $this->pending();
        $this->travel(21)->seconds();
        app(ScienceExecutionBroker::class)->execute($ticket->id);
        $this->assertSame('ready', $ticket->fresh()->status);
        $attempts = $run->fresh()->attempts;
        $this->travelTo($ticket->deadline_at);
        $this->assertNull(app(AiAgentOrchestrator::class)->processRun($run->id));
        $this->assertSame($attempts, $run->fresh()->attempts);
        $this->assertSame('science_execution_expired', $run->fresh()->error_code);
        $this->assertCount(2, $client->requests);
    }
}
