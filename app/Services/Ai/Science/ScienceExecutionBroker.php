<?php

namespace App\Services\Ai\Science;

use App\Jobs\ExecuteScienceComputation;
use App\Models\AiChatRun;
use App\Models\AiChatRunStep;
use App\Models\AiScienceExecution;
use App\Services\Ai\AiRunDispatcher;
use App\Services\Ai\AiRunStateManager;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\Enums\ThinkingEffort;
use App\Services\Ai\Exceptions\AiRunCancelledException;
use App\Services\Ai\Science\Client\ClientComputationContract;
use App\Services\Ai\Science\Client\ScienceCapabilityTelemetry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Locks always acquire the run before its execution; callbacks never perform inference. */
final class ScienceExecutionBroker
{
    public const CLIENT_LEASE_SECONDS = 20;

    public function __construct(private readonly AiRunDispatcher $dispatcher, private readonly AiScienceAgent $agent,
        private readonly AiRunStateManager $runState, private readonly ScienceCacheScope $cacheScope,
        private readonly ClientComputationContract $clientContract,
        private readonly ScienceCapabilityTelemetry $telemetry) {}

    public function suspend(AiChatRun $run, AiChatRunStep $step, array $plan, array $checkpoint, float $deadline): AiScienceExecution
    {
        return DB::transaction(function () use ($run, $step, $plan, $checkpoint, $deadline): AiScienceExecution {
            $locked = AiChatRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($locked->status !== 'running' || $locked->attempts !== $run->attempts) {
                throw new AiRunCancelledException;
            }
            $remaining = $deadline - hrtime(true) / 1_000_000_000;
            if ($remaining <= 5) {
                throw ValidationException::withMessages(['science_execution' => 'Insufficient remaining execution budget.']);
            }
            if ($locked->science_execution_id) {
                $previous = AiScienceExecution::query()->where('run_id', $locked->id)->lockForUpdate()->find($locked->science_execution_id);
                if ($previous && $previous->status === 'resuming') {
                    $this->settle($previous);
                }
            }
            $execution = AiScienceExecution::query()->create([
                'run_id' => $run->id, 'step_id' => $step->id, 'status' => 'waiting_client',
                'lease_expires_at' => now()->addSeconds(min(($plan['execution_mode'] ?? null) === 'client_script' ? 80 : self::CLIENT_LEASE_SECONDS, (int) floor($remaining - 5))),
                'deadline_at' => now()->addMilliseconds((int) floor($remaining * 1000)),
                'private_payload' => ['plan' => $plan, 'checkpoint' => ScienceCheckpoint::encode($checkpoint)],
            ]);
            $step->update(['status' => 'running', 'duration_ms' => null,
                'label' => ($plan['execution_mode'] ?? null) === 'client_script'
                    ? (config('services.ai_science_client_auto_execute', true) ? 'Menjalankan skrip komputasi lokal' : 'Menunggu persetujuan komputasi lokal')
                    : 'Menjalankan perhitungan sains',
                'public_metadata' => ['outcome' => 'started', 'execution_phase' => 'waiting_client',
                    'execution_mode' => $plan['execution_mode'] ?? 'registered_kernel']]);
            $locked->update(['science_execution_id' => $execution->id, 'claim_token' => null]);
            $this->dispatchCompute($execution->id, $execution->lease_expires_at);

            return $execution;
        });
    }

    public function offer(AiChatRun $run): ?array
    {
        if (! $run->science_execution_id || $run->status !== 'running') {
            return null;
        }
        $execution = AiScienceExecution::query()->where('run_id', $run->id)->find($run->science_execution_id);
        if (! $execution || ! in_array($execution->status, ['waiting_client', 'running_client'], true) || $execution->lease_expires_at->lessThanOrEqualTo(now())) {
            return null;
        }

        return ['id' => $execution->id, 'kernel_version' => ScienceSolverRegistry::KERNEL_VERSION,
            'claim_url' => route('ai.science.claim', $execution->id)];
    }

    public function claim(AiScienceExecution $execution, string $clientId): array
    {
        return $this->locked($execution, function ($run, $ticket) use ($clientId): array {
            $this->requireActive($run, $ticket);
            if ($ticket->lease_expires_at->lessThanOrEqualTo(now())
                || ! ($ticket->status === 'waiting_client' || ($ticket->status === 'running_client' && $ticket->client_id === $clientId))) {
                throw new ConflictHttpException('Scientific execution is already claimed or expired.');
            }
            $payload = $ticket->private_payload;
            if (! config('services.ai_science_kernel_enabled', true)
                && ($payload['plan']['execution_mode'] ?? null) !== 'client_script') {
                throw new ConflictHttpException('Registered kernel disabled; restart this request for browser-only computation.');
            }
            if ($ticket->status === 'waiting_client') {
                $payload['client_token'] = Str::random(48);
                $ticket->update(['status' => 'running_client', 'client_id' => $clientId,
                    'attempt' => $ticket->attempt + 1, 'private_payload' => $payload]);
            }
            $plan = $payload['plan'];

            if (($plan['execution_mode'] ?? null) === 'client_script') {
                return ['id' => $ticket->id, 'attempt' => $ticket->attempt, 'token' => $payload['client_token'],
                    'execution_mode' => 'client_script', 'program' => $plan['program'],
                    'auto_execute' => (bool) config('services.ai_science_client_auto_execute', true),
                    'model' => $plan['model'], 'units' => $plan['units'],
                    'lease_expires_at' => $ticket->lease_expires_at->toIso8601String(),
                    'submit_url' => route('ai.science.submit', $ticket->id)];
            }

            return ['id' => $ticket->id, 'attempt' => $ticket->attempt, 'token' => $payload['client_token'],
                'kernel_version' => ScienceSolverRegistry::KERNEL_VERSION,
                'cache_scope' => $this->cacheScope->forUser((int) $run->session()->value('user_id')),
                'solver' => $plan['solver'], 'inputs' => $plan['solver_inputs'],
                'lease_expires_at' => $ticket->lease_expires_at->toIso8601String(),
                'submit_url' => route('ai.science.submit', $ticket->id)];
        });
    }

    public function submit(AiScienceExecution $execution, array $submission): void
    {
        $this->locked($execution, function ($run, $ticket) use ($submission): void {
            $payload = $ticket->private_payload;
            $tokenHash = $payload['client_token_hash'] ?? (isset($payload['client_token']) ? hash('sha256', $payload['client_token']) : '');
            if ($tokenHash === '' || ! hash_equals($tokenHash, hash('sha256', $submission['token']))) {
                throw new ConflictHttpException('Scientific execution token is invalid.');
            }
            $hash = hash('sha256', json_encode($submission, JSON_THROW_ON_ERROR));
            if (($payload['submission']['hash'] ?? null) === $hash) {
                return; // Lost acceptance responses replay without another compute job.
            }
            $this->requireActive($run, $ticket);
            if ($ticket->status !== 'running_client' || $ticket->attempt !== $submission['attempt'] || $ticket->lease_expires_at->lessThanOrEqualTo(now())) {
                throw new ConflictHttpException('Scientific execution attempt is no longer active.');
            }
            $payload['submission'] = ['hash' => $hash, 'candidate' => $submission['result'] ?? null,
                'failure' => $submission['failure'] ?? null];
            $ticket->update(['status' => 'queued_server', 'private_payload' => $payload, 'lease_expires_at' => now()->addSeconds(45)]);
            $this->dispatchCompute($ticket->id);
        });
    }

    /** Called by a queue job. Client values never become authoritative without replay. */
    public function execute(int $executionId, ?callable $onClaim = null, ?string $claimToken = null): void
    {
        $execution = AiScienceExecution::query()->find($executionId);
        if (! $execution) {
            return;
        }
        $claimed = $this->locked($execution, function ($run, $ticket) use ($claimToken): ?AiScienceExecution {
            if ($run->status !== 'running' || $run->science_execution_id !== $ticket->id) {
                return null;
            }
            if ($ticket->deadline_at->lessThanOrEqualTo(now())) {
                $this->runState->fail($run, 'science_execution_expired', true);
                $ticket->update(['status' => 'failed']);

                return null;
            }
            if (in_array($ticket->status, ['waiting_client', 'running_client'], true)) {
                if ($ticket->lease_expires_at->isFuture()) {
                    return null;
                }
                $ticket->status = 'queued_server';
            }
            if ($ticket->status !== 'queued_server') {
                return null;
            }
            if ($ticket->server_attempts >= 2) {
                $ticket->update(['status' => 'failed', 'private_payload' => null]);
                $this->runState->fail($run, 'science_worker_failed', true);

                return null;
            }
            $ticket->update(['status' => 'running_server', 'attempt' => $ticket->attempt + 1,
                'claim_token' => $claimToken,
                'server_attempts' => $ticket->server_attempts + 1,
                'lease_expires_at' => now()->addSeconds(45)]);

            return $ticket;
        });
        if (! $claimed) {
            return;
        }
        if ($onClaim !== null) {
            $onClaim($claimed->attempt);
        }
        $payload = $claimed->private_payload;
        try {
            $guard = function () use ($claimed): void {
                $run = AiChatRun::query()->find($claimed->run_id);
                $current = AiScienceExecution::query()->find($claimed->id);
                if (! $run || $run->status !== 'running' || $run->science_execution_id !== $claimed->id
                    || ! $current || $current->status !== 'running_server' || $current->attempt !== $claimed->attempt) {
                    throw new AiRunCancelledException;
                }
                if ($current->deadline_at->lessThanOrEqualTo(now())) {
                    throw ValidationException::withMessages(['science_execution' => 'Scientific execution expired.']);
                }
            };
            if (($payload['plan']['execution_mode'] ?? null) === 'client_script') {
                $guard();
                $result = $this->clientContract->receipt(
                    $payload['plan'], $payload['submission']['candidate'] ?? null, $payload['submission']['failure'] ?? null);
                $guard();
            } elseif (! config('services.ai_science_kernel_enabled', true)) {
                // Existing registered tickets must not replay on the server after a policy switch.
                $guard();
                $result = ['status' => 'unsupported', 'result' => null, 'model_verification' => 'unverified',
                    'model' => 'Registered kernel disabled by deployment policy. Restart this request for local browser computation.',
                    'execution' => ['requested_runner' => 'browser', 'authoritative_runner' => null,
                        'verification_method' => 'none', 'client_failure' => 'kernel_disabled']];
            } else {
                $result = $this->agent->complete($payload['plan'], new LlmRequestOptions(ensureActive: $guard));
                $result['execution'] = ['requested_runner' => 'browser', 'authoritative_runner' => 'server',
                    'verification_method' => 'server_replay', 'client_failure' => $payload['submission']['failure'] ?? null];
            }
            $result['science_step_id'] = $claimed->step_id;
        } catch (AiRunCancelledException) {
            return;
        } catch (ValidationException $exception) {
            $result = ['status' => 'invalid_arguments', 'message' => 'Scientific execution failed validation; no verified result is available.',
                'errors' => $exception->errors()];
        }
        $this->locked($claimed, function ($run, $ticket) use ($claimed, $result): void {
            if ($run->status !== 'running' || $run->science_execution_id !== $ticket->id
                || $ticket->status !== 'running_server' || $ticket->attempt !== $claimed->attempt) {
                return;
            }
            if ($ticket->deadline_at->lessThanOrEqualTo(now())) {
                $this->runState->fail($run, 'science_execution_expired', true);
                $ticket->update(['status' => 'failed']);

                return;
            }
            $payload = $ticket->private_payload;
            $dynamic = ($payload['plan']['execution_mode'] ?? null) === 'client_script';
            if ($dynamic && ($payload['plan']['routing_reason'] ?? null) !== 'kernel_disabled') {
                $this->telemetry->record(($result['status'] ?? null) === 'client_computed' ? 'client_computed' : 'failure', $payload['plan']['kernel_fallback']['capability_gaps'] ?? []);
            }
            $payload['result'] = $result;
            $ticket->update(['status' => 'ready', 'private_payload' => $payload, 'lease_expires_at' => now()->addSeconds(45)]);
            $step = AiChatRunStep::query()->findOrFail($ticket->step_id);
            $failed = ($result['status'] ?? null) === 'invalid_arguments';
            $step->update(['status' => $failed ? 'failed' : 'completed',
                'label' => $dynamic ? (($result['status'] ?? null) === 'client_computed' ? 'Komputasi lokal selesai · belum terverifikasi' : 'Komputasi lokal tidak menghasilkan jawaban') : $step->label,
                'duration_ms' => max(0, (int) $step->created_at->diffInMilliseconds(now())),
                'public_metadata' => ['outcome' => $failed ? 'failed' : 'completed',
                    'execution_mode' => $dynamic ? 'client_script' : 'registered_kernel',
                    'execution_phase' => $dynamic ? (($result['status'] ?? null) === 'client_computed' ? 'client_reported_unverified' : 'client_failed') : (($result['status'] ?? null) === 'unsupported' ? 'not_executed' : 'verified_server')],
                'private_payload' => array_merge($step->private_payload ?? [], $result,
                    $dynamic ? ['client_program' => $payload['plan']['program']] : [])]);
            $run->update(['heartbeat_at' => null, 'claim_token' => null]);
            $this->dispatchResume($run, $payload);
        });
    }

    public function fail(int $id, ?int $expectedAttempt = null, ?string $claimToken = null): void
    {
        if ($expectedAttempt === null && $claimToken === null) {
            return;
        }
        $execution = AiScienceExecution::query()->find($id);
        if ($execution) {
            $this->locked($execution, function ($run, $ticket) use ($expectedAttempt, $claimToken): void {
                if ($run->status === 'running' && $run->science_execution_id === $ticket->id
                    && $ticket->status === 'running_server'
                    && ($expectedAttempt === null || $ticket->attempt === $expectedAttempt)
                    && ($claimToken === null || $ticket->claim_token === $claimToken)) {
                    $ticket->update(['status' => 'failed', 'private_payload' => null]);
                    $this->runState->fail($run, 'science_worker_failed', true);
                }
            });
        }
    }

    /** Recover missing delayed deliveries, boundedly; never spin or hold a worker. */
    public function recover(int $limit = 100): int
    {
        $ids = AiScienceExecution::query()->whereIn('status', ['waiting_client', 'running_client', 'queued_server', 'running_server', 'ready', 'resuming'])
            ->where('lease_expires_at', '<=', now())->oldest('lease_expires_at')->limit(max(1, min(100, $limit)))->pluck('id');
        foreach ($ids as $id) {
            $execution = AiScienceExecution::query()->find($id);
            if (! $execution) {
                continue;
            }
            $this->locked($execution, function ($run, $ticket): void {
                if ($run->status !== 'running') {
                    $ticket->update(['status' => 'cancelled', 'private_payload' => null]);

                    return;
                }
                if ($run->science_execution_id !== $ticket->id) {
                    $this->settle($ticket);

                    return;
                }
                if ($ticket->deadline_at->lessThanOrEqualTo(now())) {
                    $this->runState->fail($run, 'science_execution_expired', true);
                    $ticket->update(['status' => 'failed']);

                    return;
                }
                if ($ticket->lease_expires_at->isFuture()) {
                    return;
                }
                $ticket->update(['lease_expires_at' => now()->addSeconds(45)]);
                if ($ticket->status === 'ready') {
                    $this->dispatchResume($run, $ticket->private_payload);
                } else {
                    $ticket->update(['status' => 'queued_server']);
                    $this->dispatchCompute($ticket->id);
                }
            });
        }

        return $ids->count();
    }

    private function requireActive(AiChatRun $run, AiScienceExecution $ticket): void
    {
        if ($run->status !== 'running' || $ticket->deadline_at->lessThanOrEqualTo(now()) || $run->science_execution_id !== $ticket->id) {
            throw new ConflictHttpException('Scientific run is no longer active.');
        }
    }

    /** Drop duplicated model/context and plaintext tokens once their continuation is consumed. */
    public function settle(AiScienceExecution $ticket): void
    {
        $payload = $ticket->private_payload ?? [];
        $minimal = ['client_token_hash' => $payload['client_token_hash']
            ?? (isset($payload['client_token']) ? hash('sha256', $payload['client_token']) : null),
            'submission' => ['hash' => $payload['submission']['hash'] ?? null]];
        $ticket->update(['status' => 'completed', 'private_payload' => $minimal]);
    }

    private function locked(AiScienceExecution $execution, callable $callback): mixed
    {
        return DB::transaction(function () use ($execution, $callback): mixed {
            $run = AiChatRun::query()->lockForUpdate()->findOrFail($execution->run_id);
            $ticket = AiScienceExecution::query()->where('run_id', $run->id)->lockForUpdate()->findOrFail($execution->id);

            return $callback($run, $ticket);
        });
    }

    private function dispatchResume(AiChatRun $run, array $payload): void
    {
        $this->dispatcher->dispatch($run->id, ThinkingEffort::from($payload['checkpoint']['thinking_effort']));
    }

    private function dispatchCompute(int $id, ?\DateTimeInterface $delay = null): void
    {
        $job = ExecuteScienceComputation::dispatch($id)->onConnection($this->dispatcher->connection())
            ->onQueue((string) config('services.ai_compute_queue', 'ai-compute'))->afterCommit();
        if ($delay) {
            $job->delay($delay);
        }
    }
}
