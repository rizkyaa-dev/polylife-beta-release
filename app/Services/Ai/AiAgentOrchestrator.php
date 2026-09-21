<?php

namespace App\Services\Ai;

use App\Models\AiActionProposal;
use App\Models\AiChatBranch;
use App\Models\AiChatMessage;
use App\Models\AiChatRun;
use App\Models\AiChatRunStep;
use App\Models\AiChatSession;
use App\Models\AiScienceExecution;
use App\Models\User;
use App\Models\UserAiAssistant;
use App\Services\Ai\Actions\AiWriteActionRegistry;
use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\Design\AiDesignArtifactEvaluator;
use App\Services\Ai\DTOs\LlmMessage;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\Enums\ThinkingEffort;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\Exceptions\AiRunCancelledException;
use App\Services\Ai\Science\AiScienceAgent;
use App\Services\Ai\Science\AiScienceDelegation;
use App\Services\Ai\Science\Client\ClientComputationAgent;
use App\Services\Ai\Science\Client\ScienceCapabilityTelemetry;
use App\Services\Ai\Science\ScienceCheckpoint;
use App\Services\Ai\Science\ScienceContractStore;
use App\Services\Ai\Science\ScienceExecutionBroker;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AiAgentOrchestrator
{
    private const MAX_TOOL_ROUNDS = 4;

    public function __construct(
        private readonly LlmClientInterface $llmClient,
        private readonly AiToolRegistry $toolRegistry,
        private readonly AiWriteActionRegistry $writeActions,
        private readonly AiSystemInstructionBuilder $instructionBuilder,
        private readonly AiConversationBranchService $branchService,
        private readonly ConversationContextAssembler $contextAssembler,
        private readonly ToolActivityPresenter $toolActivityPresenter,
        private readonly AiToolSelectionService $toolSelection,
        private readonly AiInferencePolicy $inferencePolicy,
        private readonly AiRunStateManager $runStateManager,
        private readonly AiRunDispatcher $runDispatcher,
        private readonly AiCodingInstructionRouter $codingInstructionRouter,
        private readonly AiCodingDelegation $codingDelegation,
        private readonly AiCodeGenerationAgent $codeGenerationAgent,
        private readonly AiDesignArtifactEvaluator $designEvaluator,
        private readonly AiScienceAgent $scienceAgent,
        private readonly AiScienceDelegation $scienceDelegation,
        private readonly ScienceContractStore $scienceContracts,
        private readonly ScienceExecutionBroker $scienceBroker,
        private readonly ClientComputationAgent $clientComputation,
        private readonly ScienceCapabilityTelemetry $scienceTelemetry
    ) {}

    /**
     * @return array{
     *     session: AiChatSession,
     *     reply: string,
     *     proposals: list<array<string, mixed>>
     * }
     */
    public function handle(User $user, string $userPrompt, ?int $sessionId = null): array
    {
        $turn = $this->branchService->beginTurn($user, $userPrompt, $sessionId);

        return $this->runTurn($user, $userPrompt, $turn);
    }

    /** @return array{session: AiChatSession, branch: AiChatBranch, user_message: AiChatMessage, run: AiChatRun} */
    public function enqueue(User $user, string $userPrompt, ?int $sessionId = null, ?string $requestId = null, bool $scienceClient = false): array
    {
        $turn = $this->branchService->beginTurn($user, $userPrompt, $sessionId, requestId: $requestId, scienceClient: $scienceClient);
        if ($turn['is_new'] ?? true) {
            $this->dispatchTurn($turn['run'], $user);
        }

        return $turn;
    }

    /**
     * @return array<string, mixed>
     */
    public function edit(User $user, int $messageId, string $userPrompt): array
    {
        $message = AiChatMessage::query()
            ->whereKey($messageId)
            ->whereHas('session', fn ($query) => $query->where('user_id', $user->id))
            ->firstOrFail();
        $turn = $this->branchService->beginTurn($user, $userPrompt, $message->session_id, $message->id);

        return $this->runTurn($user, $userPrompt, $turn);
    }

    /** @return array{session: AiChatSession, branch: AiChatBranch, user_message: AiChatMessage, run: AiChatRun} */
    public function enqueueEdit(User $user, int $messageId, string $userPrompt, ?string $requestId = null, bool $scienceClient = false): array
    {
        $message = AiChatMessage::query()
            ->whereKey($messageId)
            ->whereHas('session', fn ($query) => $query->where('user_id', $user->id))
            ->firstOrFail();
        $turn = $this->branchService->beginTurn($user, $userPrompt, $message->session_id, $message->id, $requestId, $scienceClient);
        if ($turn['is_new'] ?? true) {
            $this->dispatchTurn($turn['run'], $user);
        }

        return $turn;
    }

    /** @return array<string, mixed>|null */
    public function processRun(int $runId, ?callable $onClaim = null, ?string $claimToken = null): ?array
    {
        $resume = null;
        $run = DB::transaction(function () use ($runId, &$resume, $claimToken): ?AiChatRun {
            $run = AiChatRun::query()->lockForUpdate()->find($runId);
            if (! $run || $run->status !== 'running') {
                return null;
            }
            if ($run->science_execution_id) {
                $execution = AiScienceExecution::query()->where('run_id', $run->id)->lockForUpdate()->find($run->science_execution_id);
                if (! $execution || $execution->status !== 'ready') {
                    return null;
                }
                if ($execution->deadline_at->lessThanOrEqualTo(now())) {
                    $this->runStateManager->fail($run, 'science_execution_expired', true);

                    return null;
                }
                $resume = $execution;
                $execution->update(['status' => 'resuming', 'lease_expires_at' => $execution->deadline_at->copy()->addSeconds(30)]);
            } elseif ($run->heartbeat_at !== null) {
                return null;
            }
            $run->update([
                'attempts' => $run->attempts + 1,
                'claim_token' => $claimToken,
                'heartbeat_at' => now(),
                'lease_expires_at' => now()->addMinutes(6),
                'error_code' => null,
                'retryable' => false,
            ]);

            return $run;
        });
        if (! $run) {
            return null;
        }
        if ($onClaim !== null) {
            $onClaim($run->attempts);
        }

        $run->load(['session.user', 'branch', 'userMessage']);
        if (! $run->session || ! $run->session->user || ! $run->branch || ! $run->userMessage) {
            throw new \RuntimeException('Run AI tidak memiliki relasi percakapan yang lengkap.');
        }

        try {
            return $this->runTurn($run->session->user, $run->userMessage->content, [
                'session' => $run->session,
                'branch' => $run->branch,
                'user_message' => $run->userMessage,
                'run' => $run,
                'parent_id' => $run->userMessage->parent_message_id,
            ], $resume);
        } catch (AiRunCancelledException) {
            return null;
        }
    }

    /**
     * @param  array{session: AiChatSession, branch: AiChatBranch, user_message: AiChatMessage, run: AiChatRun, parent_id: ?int}  $turn
     * @return array<string, mixed>
     */
    private function runTurn(User $user, string $userPrompt, array $turn, ?AiScienceExecution $resume = null): array
    {
        $session = $turn['session'];
        $branch = $turn['branch'];
        $userMessage = $turn['user_message'];
        $run = $turn['run'];
        $expectedAttempt = (int) $run->attempts;
        $startedAt = microtime(true);
        $run->update(['heartbeat_at' => now(), 'lease_expires_at' => now()->addMinutes(6)]);

        $assistant = UserAiAssistant::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'assistant_name' => 'PolyBot',
                'personality_tone' => 'friendly_peer',
            ]
        );

        $systemInstruction = $this->instructionBuilder->build($user, $assistant).$this->codingDelegation->instruction().$this->scienceDelegation->instruction();
        $minimumOutputTokens = 0;
        $thinkingEffort = $assistant->thinking_effort ?? ThinkingEffort::High;
        $runStartedAt = hrtime(true) / 1_000_000_000;
        $deadlineAt = $runStartedAt + $this->inferencePolicy->runTimeoutSeconds($thinkingEffort);
        $run->update(['lease_expires_at' => now()->addSeconds($this->inferencePolicy->runTimeoutSeconds($thinkingEffort) + 30)]);

        $lineage = $this->branchService->recentLineage($session, $turn['parent_id']);
        $availableScience = $this->scienceContracts->ancestors($lineage);
        if ($availableScience->isNotEmpty()) {
            $systemInstruction .= "\nScientific contract step IDs available on this branch: ".implode(', ', $availableScience->keys()->all()).'. Use science_step_id when implementing a calculator from one of these results.';
        }
        $sourceArtifacts = $lineage->filter(fn (AiChatMessage $message) => $message->role === 'assistant'
            && $message->status === 'completed' && str_contains($message->content, '```'))->keyBy('id');
        if ($sourceArtifacts->isNotEmpty()) {
            $systemInstruction .= "\nArtefak pada cabang ini (ID pesan asisten, urutan lama ke baru): ".implode(', ', $sourceArtifacts->keys()->all());
        }
        $messageHistory = $this->contextAssembler->assemble($lineage, $branch);
        $messageHistory[] = new LlmMessage(role: 'user', content: $userPrompt);
        $tools = [...$this->toolSelection->declarations($userPrompt, $messageHistory), $this->codingDelegation->declaration(), $this->scienceDelegation->declaration()];
        $proposals = [];
        $proposalRows = [];
        $toolResultsByInvocation = [];

        $iterations = 0;
        $finalReply = '';
        $finalReasoningContent = null;
        $stepSequence = 0;
        $totalToolCalls = 0;
        $delegationBoundaryEnforced = false;

        if ($resume !== null) {
            $saved = ScienceCheckpoint::decode($resume->private_payload['checkpoint']);
            $messageHistory = $saved['history'];
            $systemInstruction = $saved['system_instruction'];
            $tools = $saved['tools'];
            $thinkingEffort = ThinkingEffort::from($saved['thinking_effort']);
            $iterations = $saved['iterations'];
            $stepSequence = $saved['step_sequence'];
            $totalToolCalls = $saved['total_tool_calls'];
            $toolResultsByInvocation = $saved['invocation_results'];
            $proposals = $saved['proposals'];
            $proposalRows = $saved['proposal_rows'];
            $delegationBoundaryEnforced = $saved['boundary_enforced'];
            $remaining = (float) now()->diffInMilliseconds($resume->deadline_at, false) / 1000;
            $deadlineAt = hrtime(true) / 1_000_000_000 + $remaining;
            $runStartedAt = $deadlineAt - $saved['run_budget_seconds'];
            $startedAt = (float) $saved['processing_started_at'];
            $result = $resume->private_payload['result'];
            $toolResultsByInvocation[$saved['pending_invocation_key']] = $result;
            $messageHistory[] = new LlmMessage('tool', toolResult: [
                'call_id' => $saved['pending_call_id'], 'tool_name' => AiScienceDelegation::TOOL_NAME,
                'result' => $this->scienceDelegation->toolResult($result)]);
            $availableScience->put($resume->step_id, AiChatRunStep::query()->findOrFail($resume->step_id));
            $this->heartbeat($run, $deadlineAt);
        }

        try {
            while ($iterations < self::MAX_TOOL_ROUNDS) {
                $iterations++;
                $reasoningStep = $this->startStep(
                    $run->id,
                    ++$stepSequence,
                    'reasoning_summary',
                    $iterations === 1 ? 'Memahami permintaan dan konteks' : 'Menilai hasil alat bantu'
                );
                $response = $this->performModelCall(
                    $reasoningStep,
                    fn () => $this->llmClient->chat(
                        $messageHistory,
                        $tools,
                        $systemInstruction,
                        $this->requestOptions($thinkingEffort, $deadlineAt, $minimumOutputTokens)->withGuard(fn () => $this->ensureRunIsActive($run, $expectedAttempt))
                    )
                );
                $this->ensureRunIsActive($run, $expectedAttempt);
                if ($response->isTruncated()) {
                    throw AiProviderException::truncated();
                }

                if (! $response->hasToolCalls()) {
                    if ($this->codingDelegation->containsImplementation($response->content)) {
                        if ($delegationBoundaryEnforced || $iterations >= self::MAX_TOOL_ROUNDS) {
                            throw AiProviderException::invalidCodingResponse();
                        }
                        // Discard bypassed source code; never feed it back as authoritative context.
                        $delegationBoundaryEnforced = true;
                        $tools = [$this->codingDelegation->declaration()];
                        $systemInstruction .= "\nImplementasi Anda tidak diterima: gunakan delegate_code_generation dengan brief lengkap untuk permintaan ini. Jangan keluarkan source code sendiri.";

                        continue;
                    }
                    $finalReply = (string) ($response->content ?? 'Maaf, saya tidak memiliki jawaban saat ini.');
                    $finalReasoningContent = $response->reasoningContent;
                    break;
                }

                $roundToolCalls = count($response->toolCalls);
                $maxPerRound = max(1, (int) config('services.ai_max_tool_calls_per_response', 8));
                $maxPerRun = max($maxPerRound, (int) config('services.ai_max_tool_calls_per_run', 16));
                if ($roundToolCalls > $maxPerRound || $totalToolCalls + $roundToolCalls > $maxPerRun) {
                    throw AiProviderException::toolCallLimitExceeded();
                }
                $totalToolCalls += $roundToolCalls;

                $messageHistory[] = new LlmMessage(
                    role: 'assistant',
                    content: $response->content,
                    toolCalls: $response->toolCalls,
                    reasoningContent: $response->reasoningContent
                );

                foreach ($response->toolCalls as $call) {
                    $this->ensureRunIsActive($run, $expectedAttempt);
                    $toolStep = $this->startStep(
                        $run->id,
                        ++$stepSequence,
                        'tool_call',
                        $this->toolActivityPresenter->label($call->name),
                        $call->name
                    );
                    $toolStartedAt = microtime(true);
                    $invocationKey = $this->toolInvocationKey($call->name, $call->arguments);

                    if ($call->argumentError !== null) {
                        $result = [
                            'status' => 'invalid_arguments',
                            'message' => $call->argumentError,
                        ];
                    } elseif ($call->name === AiCodingDelegation::TOOL_NAME) {
                        try {
                            if ($roundToolCalls !== 1) {
                                throw ValidationException::withMessages(['delegation' => 'Selesaikan tool workspace terlebih dahulu; delegasi harus dipanggil sendiri.']);
                            }
                            $brief = $this->codingDelegation->brief($call->arguments, $userPrompt, $this->codingInstructionRouter);
                            $scienceId = $call->arguments['science_step_id'] ?? null;
                            $scienceContract = $scienceId === null ? null : $this->scienceContracts->resolve((int) $scienceId, $availableScience);
                            $sourceId = $call->arguments['source_message_id'] ?? null;
                            $source = $sourceId === null ? null : $sourceArtifacts->get($sourceId);
                            if ($sourceId !== null && $source === null) {
                                throw ValidationException::withMessages(['source_message_id' => 'Artefak tidak tersedia pada cabang ini.']);
                            }
                            if ($source !== null && mb_strlen($source->content) > 120000) {
                                throw ValidationException::withMessages(['source_message_id' => 'Artefak terlalu besar untuk revisi dalam satu turn. Minta pengguna membatasi bagian yang direvisi.']);
                            }
                            // Promote only validated coding work; anchor to run start so
                            // repeated delegations cannot renew the deadline indefinitely.
                            $deadlineAt = $runStartedAt + $this->inferencePolicy->runTimeoutSeconds($thinkingEffort, true);
                            $this->heartbeat($run, $deadlineAt);
                            // Retain the validated brief privately even when generation fails.
                            $coderOptions = $this->requestOptions($thinkingEffort, $deadlineAt, max(0, (int) config('services.ai_coding_output_tokens', 16384)), true)
                                ->withGuard(fn () => $this->ensureRunIsActive($run, $expectedAttempt));
                            $toolStep->update([
                                'public_metadata' => ['outcome' => 'started', 'language' => $brief->language, 'runtime' => $brief->runtime, 'prompt_version' => AiCodingPromptBuilder::VERSION],
                                'private_payload' => [
                                    'coding_brief' => $brief->toArray(), 'source_message_id' => $sourceId, 'science_contract' => $scienceContract,
                                    'execution_policy' => ['thinking_effort' => $thinkingEffort->value, 'request_timeout_seconds' => $coderOptions->timeoutSeconds, 'run_timeout_seconds' => $this->inferencePolicy->runTimeoutSeconds($thinkingEffort, true)],
                                ],
                            ]);
                            $generated = $this->performModelCall($toolStep, function () use ($userPrompt, $brief, $source, $coderOptions, $scienceContract) {
                                $generated = $this->codeGenerationAgent->generate(
                                    $userPrompt, $brief, $this->codingInstructionRouter->forLanguage($brief->language),
                                    $coderOptions,
                                    $source?->content, $scienceContract
                                );
                                if ($generated->hasToolCalls() || blank($generated->content) || ! str_contains($generated->content, '```')) {
                                    throw AiProviderException::invalidCodingResponse();
                                }

                                return $generated;
                            });
                            $this->ensureRunIsActive($run, $expectedAttempt);
                            $toolStep->update([
                                'public_metadata' => ['outcome' => 'completed', 'language' => $brief->language, 'runtime' => $brief->runtime, 'prompt_version' => AiCodingPromptBuilder::VERSION],
                                'private_payload' => array_merge($toolStep->private_payload, [
                                    'design_review' => $this->designEvaluator->evaluate((string) $generated->content),
                                ]),
                            ]);
                            $finalReply = (string) $generated->content;
                            $finalReasoningContent = $generated->reasoningContent;
                            break 2;
                        } catch (ValidationException $exception) {
                            $result = ['status' => 'invalid_arguments', 'message' => 'Perbaiki brief delegasi sebelum mencoba kembali.', 'errors' => $exception->errors()];
                        }
                    } elseif ($call->name === AiScienceDelegation::TOOL_NAME) {
                        if (array_key_exists($invocationKey, $toolResultsByInvocation)) {
                            $result = $toolResultsByInvocation[$invocationKey];
                        } else {
                            try {
                                $scienceRequest = $this->scienceDelegation->request($call->arguments, $userPrompt);
                                if ($roundToolCalls !== 1) {
                                    throw ValidationException::withMessages(['delegation' => 'Scientific delegation must be called alone after collecting required facts.']);
                                }
                                $deadlineAt = max($deadlineAt, $runStartedAt + $this->inferencePolicy->scienceRunTimeoutSeconds($thinkingEffort));
                                $this->heartbeat($run, $deadlineAt);
                                $toolStep->update(['private_payload' => ['science_request' => $scienceRequest]]);
                                $scienceOptions = $this->requestOptions($thinkingEffort, $deadlineAt, 8192, true)
                                    ->withGuard(fn () => $this->ensureRunIsActive($run, $expectedAttempt));
                                $kernelEnabled = (bool) config('services.ai_science_kernel_enabled', true);
                                if (! $kernelEnabled) {
                                    $result = $run->science_client && config('services.ai_science_dynamic_enabled', false)
                                        ? $this->performModelCall($toolStep, fn () => $this->clientComputation->planLocal($scienceRequest, $scienceOptions))
                                        : ['status' => 'unsupported', 'result' => null, 'model_verification' => 'unverified',
                                            'model' => 'Server computation is disabled. An available browser client and enabled local computation are required; no server fallback is permitted.'];
                                } else {
                                    $result = $this->performModelCall($toolStep, fn () => $run->science_client
                                    ? $this->scienceAgent->plan($scienceRequest, $scienceOptions)
                                    : $this->scienceAgent->solve($scienceRequest, $scienceOptions));
                                }
                                $this->ensureRunIsActive($run, $expectedAttempt);
                                if ($kernelEnabled && $result['status'] === 'unsupported') {
                                    $this->scienceTelemetry->record('unsupported', $result['capability_gaps'] ?? []);
                                    if ($run->science_client && config('services.ai_science_dynamic_enabled', false)) {
                                        $result = $this->performModelCall($toolStep,
                                            fn () => $this->clientComputation->plan($scienceRequest, $result, $scienceOptions));
                                        $this->ensureRunIsActive($run, $expectedAttempt);
                                        $this->scienceTelemetry->record($result['status'] === 'ready' ? 'prepared' : 'failure', $result['kernel_fallback']['capability_gaps'] ?? []);
                                    }
                                }
                                if ($run->science_client && $result['status'] === 'ready') {
                                    $this->scienceBroker->suspend($run, $toolStep, $result, [
                                        'history' => $messageHistory, 'system_instruction' => $systemInstruction, 'tools' => $tools,
                                        'thinking_effort' => $thinkingEffort->value, 'iterations' => $iterations,
                                        'step_sequence' => $stepSequence, 'total_tool_calls' => $totalToolCalls,
                                        'invocation_results' => $toolResultsByInvocation, 'proposals' => $proposals,
                                        'proposal_rows' => $proposalRows, 'boundary_enforced' => $delegationBoundaryEnforced,
                                        'run_budget_seconds' => $deadlineAt - $runStartedAt, 'processing_started_at' => $startedAt,
                                        'pending_invocation_key' => $invocationKey, 'pending_call_id' => $call->id,
                                    ], $deadlineAt);

                                    return $turn + ['phase' => 'awaiting_science'];
                                }
                                $result['science_step_id'] = $toolStep->id;
                                $toolStep->update(['private_payload' => array_merge($toolStep->private_payload ?? [], $result)]);
                                $availableScience->put($toolStep->id, $toolStep);
                                $toolResultsByInvocation[$invocationKey] = $result;
                            } catch (ValidationException $exception) {
                                $result = ['status' => 'invalid_arguments', 'errors' => $exception->errors(),
                                    'message' => 'Scientific model or inputs were invalid; no verified result is available.'];
                            }
                        }
                    } elseif (array_key_exists($invocationKey, $toolResultsByInvocation)) {
                        $result = [
                            'status' => 'duplicate_tool_call',
                            'message' => 'Tool dengan argumen identik sudah dijalankan pada respons ini. Gunakan hasil sebelumnya dan lanjutkan ke jawaban.',
                            'previous_result' => $toolResultsByInvocation[$invocationKey],
                        ];
                    } else {
                        $tool = $this->toolRegistry->find($call->name);
                        if (! $tool) {
                            $result = ['error' => "Tool '{$call->name}' tidak dikenali."];
                        } elseif ($tool->isMutating()) {
                            try {
                                $proposalData = $tool->execute($user, $call->arguments);
                                $proposalData['payload'] = $this->writeActions->validatePayload(
                                    $call->name,
                                    (array) ($proposalData['payload'] ?? [])
                                );
                                $prepared = $this->prepareSignedProposal($user, $session, $proposalData);
                                $proposals[] = $prepared['response'];
                                $proposalRows[] = $prepared['row'];

                                $result = [
                                    'status' => 'proposal_created',
                                    'message' => "Proposal aksi {$call->name} berhasil dibuat dan membutuhkan konfirmasi pengguna.",
                                    'summary' => $prepared['response']['summary'],
                                ];
                            } catch (ValidationException|InvalidFormatException $exception) {
                                $result = [
                                    'status' => 'invalid_arguments',
                                    'message' => 'Argumen tool tidak valid. Perbaiki data sebelum mencoba kembali.',
                                    'errors' => $exception instanceof ValidationException ? $exception->errors() : [],
                                ];
                            } catch (Throwable $exception) {
                                report($exception);
                                $result = $this->toolExecutionFailure($call->name);
                            }
                        } else {
                            try {
                                $result = $tool->execute($user, $call->arguments);
                            } catch (ValidationException|InvalidFormatException $exception) {
                                $result = [
                                    'status' => 'invalid_arguments',
                                    'message' => 'Argumen tool tidak valid. Perbaiki data sebelum mencoba kembali.',
                                    'errors' => $exception instanceof ValidationException ? $exception->errors() : [],
                                ];
                            } catch (Throwable $exception) {
                                report($exception);
                                $result = $this->toolExecutionFailure($call->name);
                            }
                        }

                        $toolResultsByInvocation[$invocationKey] = $result;
                    }

                    $toolFailed = array_key_exists('error', $result)
                        || in_array($result['status'] ?? null, ['invalid_arguments', 'tool_execution_failed'], true);
                    $toolStep->update([
                        'status' => $toolFailed ? 'failed' : 'completed',
                        'duration_ms' => $this->elapsedMilliseconds($toolStartedAt),
                        'public_metadata' => ['outcome' => $toolFailed ? 'failed' : 'completed'],
                        'private_payload' => $call->name === AiScienceDelegation::TOOL_NAME
                            ? array_merge($toolStep->private_payload ?? [], $result) : $result,
                    ]);
                    $this->heartbeat($run, $deadlineAt);

                    $messageHistory[] = new LlmMessage(
                        role: 'tool',
                        content: null,
                        toolCalls: [],
                        toolResult: [
                            'call_id' => $call->id,
                            'tool_name' => $call->name,
                            'result' => $call->name === AiScienceDelegation::TOOL_NAME
                                ? $this->scienceDelegation->toolResult($result) : $result,
                        ]
                    );
                }
            }
            if ($finalReply === '') {
                $reasoningStep = $this->startStep(
                    $run->id,
                    ++$stepSequence,
                    'reasoning_summary',
                    'Menyusun jawaban dari hasil yang tersedia'
                );
                $finalResponse = $this->performModelCall(
                    $reasoningStep,
                    fn () => $this->llmClient->chat(
                        $messageHistory,
                        [],
                        $this->instructionBuilder->forFinalAnswer($systemInstruction),
                        $this->requestOptions($thinkingEffort, $deadlineAt, $minimumOutputTokens)->withGuard(fn () => $this->ensureRunIsActive($run, $expectedAttempt))
                    )
                );
                $this->ensureRunIsActive($run, $expectedAttempt);
                if ($finalResponse->isTruncated()) {
                    throw AiProviderException::truncated();
                }

                if (! $finalResponse->hasToolCalls() && filled($finalResponse->content)) {
                    if ($this->codingDelegation->containsImplementation($finalResponse->content)) {
                        throw AiProviderException::invalidCodingResponse();
                    }
                    $finalReply = (string) $finalResponse->content;
                    $finalReasoningContent = $finalResponse->reasoningContent;
                } else {
                    $finalReply = 'Saya belum dapat menyelesaikan permintaan ini dari hasil yang tersedia. Silakan lengkapi detail yang diperlukan dan coba kembali.';
                }
            }

            $this->ensureRunIsActive($run, $expectedAttempt);
            $assistantMessage = DB::transaction(function () use ($userMessage, $session, $branch, $run, $proposalRows, $proposals, $finalReply, $finalReasoningContent, $startedAt, $expectedAttempt): AiChatMessage {
                $lockedRun = AiChatRun::query()->lockForUpdate()->findOrFail($run->id);
                if ($lockedRun->status !== 'running' || $lockedRun->attempts !== $expectedAttempt) {
                    throw new AiRunCancelledException;
                }
                $userMessage->update(['status' => 'completed', 'error_code' => null]);

                foreach ($proposalRows as $proposalRow) {
                    AiActionProposal::query()->create($proposalRow);
                }

                $assistantMessage = AiChatMessage::query()->create([
                    'session_id' => $session->id,
                    'parent_message_id' => $userMessage->id,
                    'branch_id' => $branch->id,
                    'revision_group_id' => (string) Str::uuid(),
                    'role' => 'assistant',
                    'content' => $finalReply,
                    'reasoning_content' => $finalReasoningContent,
                    'status' => 'completed',
                    'tool_calls_json' => ! empty($proposals) ? $proposals : null,
                ]);

                $branch->update(['head_message_id' => $assistantMessage->id]);
                $lockedRun->update([
                    'assistant_message_id' => $assistantMessage->id,
                    'status' => 'completed',
                    'duration_ms' => $this->elapsedMilliseconds($startedAt),
                    'completed_at' => now(),
                    'heartbeat_at' => now(),
                    'lease_expires_at' => null,
                ]);
                if ($lockedRun->science_execution_id) {
                    $execution = AiScienceExecution::query()->where('run_id', $run->id)->lockForUpdate()->find($lockedRun->science_execution_id);
                    if ($execution) {
                        $this->scienceBroker->settle($execution);
                    }
                }
                $session->update(['active_branch_id' => $branch->id]);
                $session->touch();

                return $assistantMessage;
            });
        } catch (Throwable $exception) {
            $errorCode = $exception instanceof AiProviderException
                ? $exception->errorCode
                : 'run_failed';
            $retryable = $exception instanceof AiProviderException && $exception->retryable;
            $this->runStateManager->fail(
                $run,
                $errorCode,
                $retryable,
                $this->elapsedMilliseconds($startedAt),
                $expectedAttempt
            );

            throw $exception;
        }

        $run->refresh()->load('steps');

        return [
            'session' => $session,
            'branch' => $branch,
            'user_message' => $userMessage->fresh(),
            'assistant_message' => $assistantMessage,
            'run' => $run,
            'reply' => $finalReply,
            'proposals' => $proposals,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function toolInvocationKey(string $toolName, array $arguments): string
    {
        $canonicalArguments = ActionProposalExecutor::canonicalizePayload($arguments);

        return hash('sha256', $toolName."\0".json_encode($canonicalArguments, JSON_THROW_ON_ERROR));
    }

    /** @return array{status: string, message: string, tool_name: string} */
    private function toolExecutionFailure(string $toolName): array
    {
        return [
            'status' => 'tool_execution_failed',
            'message' => 'Data workspace belum dapat dimuat. Jangan mengarang hasil; lanjutkan dengan informasi yang tersedia.',
            'tool_name' => $toolName,
        ];
    }

    /**
     * @return array{response: array<string, mixed>, row: array<string, mixed>}
     */
    private function prepareSignedProposal(User $user, AiChatSession $session, array $data): array
    {
        $actionId = 'act_'.Str::lower(Str::random(16));
        $expiresAt = now()->addMinutes(15);
        $payloadJson = $data['payload'] ?? [];
        $toolName = $data['tool_name'] ?? 'unknown';

        $canonicalPayload = ActionProposalExecutor::canonicalizePayload($payloadJson);

        $signature = hash_hmac(
            'sha256',
            $actionId.'|'.$user->id.'|'.$toolName.'|'.json_encode($canonicalPayload),
            (string) config('app.key')
        );

        $row = [
            'action_id' => $actionId,
            'session_id' => $session->id,
            'user_id' => $user->id,
            'tool_name' => $toolName,
            'summary' => $data['summary'] ?? 'Usulan perubahan data',
            'payload_json' => $payloadJson,
            'signature' => $signature,
            'status' => 'pending',
            'expires_at' => $expiresAt,
        ];

        return [
            'response' => [
                'action_id' => $actionId,
                'tool_name' => $toolName,
                'summary' => $row['summary'],
                'payload' => $payloadJson,
                'signature' => $signature,
                'expires_at' => $expiresAt->toIso8601String(),
            ],
            'row' => $row,
        ];
    }

    private function startStep(
        int $runId,
        int $sequence,
        string $kind,
        string $label,
        ?string $toolName = null
    ): AiChatRunStep {
        return AiChatRunStep::query()->create([
            'run_id' => $runId,
            'sequence' => $sequence,
            'kind' => $kind,
            'status' => 'running',
            'label' => $label,
            'tool_name' => $toolName,
        ]);
    }

    private function performModelCall(AiChatRunStep $step, callable $callback): mixed
    {
        $startedAt = microtime(true);
        try {
            $response = $callback();
            $step->update([
                'status' => 'completed',
                'duration_ms' => $this->elapsedMilliseconds($startedAt),
            ]);

            return $response;
        } catch (Throwable $exception) {
            $step->update([
                'status' => 'failed',
                'duration_ms' => $this->elapsedMilliseconds($startedAt),
            ]);

            throw $exception;
        }
    }

    private function requestOptions(
        ThinkingEffort $effort,
        float $deadlineAt,
        int $minimumOutputTokens = 0,
        bool $coding = false
    ): LlmRequestOptions {
        $requestDeadline = $deadlineAt - 5;
        $remaining = (int) floor($requestDeadline - hrtime(true) / 1_000_000_000);
        if ($remaining < 1) {
            throw AiProviderException::timeout();
        }

        $options = $this->inferencePolicy->requestOptions($effort, $remaining, $minimumOutputTokens, $coding);

        return $options->forAttempt($options->timeoutSeconds, $requestDeadline);
    }

    private function heartbeat(AiChatRun $run, float $deadlineAt): void
    {
        $remaining = max(5, (int) ceil($deadlineAt - hrtime(true) / 1_000_000_000));
        $run->update([
            'heartbeat_at' => now(),
            'lease_expires_at' => now()->addSeconds($remaining + 30),
        ]);
    }

    private function ensureRunIsActive(AiChatRun $run, ?int $expectedAttempt = null): void
    {
        $run->refresh();
        if ($run->status !== 'running' || ($expectedAttempt !== null && $run->attempts !== $expectedAttempt)) {
            throw new AiRunCancelledException;
        }
    }

    private function elapsedMilliseconds(float $startedAt): int
    {
        return max(0, (int) round((microtime(true) - $startedAt) * 1000));
    }

    private function dispatchTurn(AiChatRun $run, User $user): void
    {
        try {
            $effort = $user->aiAssistant()->first()?->thinking_effort;
            $this->runDispatcher->dispatch(
                $run->id,
                $effort instanceof ThinkingEffort
                    ? $effort
                    : ThinkingEffort::tryFrom((string) $effort) ?? ThinkingEffort::High
            );
        } catch (Throwable $exception) {
            $this->runStateManager->fail($run, 'queue_dispatch_failed', true);

            throw $exception;
        }
    }
}
