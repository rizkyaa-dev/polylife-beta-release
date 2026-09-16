<?php

namespace App\Services\Ai;

use App\Models\AiActionProposal;
use App\Models\AiChatBranch;
use App\Models\AiChatMessage;
use App\Models\AiChatRun;
use App\Models\AiChatRunStep;
use App\Models\AiChatSession;
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
        private readonly AiDesignArtifactEvaluator $designEvaluator
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
    public function enqueue(User $user, string $userPrompt, ?int $sessionId = null, ?string $requestId = null): array
    {
        $turn = $this->branchService->beginTurn($user, $userPrompt, $sessionId, requestId: $requestId);
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
    public function enqueueEdit(User $user, int $messageId, string $userPrompt, ?string $requestId = null): array
    {
        $message = AiChatMessage::query()
            ->whereKey($messageId)
            ->whereHas('session', fn ($query) => $query->where('user_id', $user->id))
            ->firstOrFail();
        $turn = $this->branchService->beginTurn($user, $userPrompt, $message->session_id, $message->id, $requestId);
        if ($turn['is_new'] ?? true) {
            $this->dispatchTurn($turn['run'], $user);
        }

        return $turn;
    }

    /** @return array<string, mixed>|null */
    public function processRun(int $runId): ?array
    {
        $run = DB::transaction(function () use ($runId): ?AiChatRun {
            $run = AiChatRun::query()->lockForUpdate()->find($runId);
            if (! $run || $run->status !== 'running' || $run->heartbeat_at !== null) {
                return null;
            }
            $run->update([
                'attempts' => $run->attempts + 1,
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
            ]);
        } catch (AiRunCancelledException) {
            return null;
        }
    }

    /**
     * @param  array{session: AiChatSession, branch: AiChatBranch, user_message: AiChatMessage, run: AiChatRun, parent_id: ?int}  $turn
     * @return array<string, mixed>
     */
    private function runTurn(User $user, string $userPrompt, array $turn): array
    {
        $session = $turn['session'];
        $branch = $turn['branch'];
        $userMessage = $turn['user_message'];
        $run = $turn['run'];
        $startedAt = microtime(true);
        $run->update(['heartbeat_at' => now(), 'lease_expires_at' => now()->addMinutes(6)]);

        $assistant = UserAiAssistant::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'assistant_name' => 'PolyBot',
                'personality_tone' => 'friendly_peer',
            ]
        );

        $systemInstruction = $this->instructionBuilder->build($user, $assistant).$this->codingDelegation->instruction();
        $minimumOutputTokens = 0;
        $thinkingEffort = $assistant->thinking_effort ?? ThinkingEffort::High;
        $runStartedAt = hrtime(true) / 1_000_000_000;
        $deadlineAt = $runStartedAt + $this->inferencePolicy->runTimeoutSeconds($thinkingEffort);
        $run->update(['lease_expires_at' => now()->addSeconds($this->inferencePolicy->runTimeoutSeconds($thinkingEffort) + 30)]);

        $lineage = $this->branchService->recentLineage($session, $turn['parent_id']);
        $sourceArtifacts = $lineage->filter(fn (AiChatMessage $message) => $message->role === 'assistant'
            && $message->status === 'completed' && str_contains($message->content, '```'))->keyBy('id');
        if ($sourceArtifacts->isNotEmpty()) {
            $systemInstruction .= "\nArtefak pada cabang ini (ID pesan asisten, urutan lama ke baru): ".implode(', ', $sourceArtifacts->keys()->all());
        }
        $messageHistory = $this->contextAssembler->assemble($lineage, $branch);
        $messageHistory[] = new LlmMessage(role: 'user', content: $userPrompt);
        $tools = [...$this->toolSelection->declarations($userPrompt, $messageHistory), $this->codingDelegation->declaration()];
        $proposals = [];
        $proposalRows = [];
        $toolResultsByInvocation = [];

        $iterations = 0;
        $finalReply = '';
        $finalReasoningContent = null;
        $stepSequence = 0;
        $totalToolCalls = 0;
        $delegationBoundaryEnforced = false;

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
                        $this->requestOptions($thinkingEffort, $deadlineAt, $minimumOutputTokens)->withGuard(fn () => $this->ensureRunIsActive($run))
                    )
                );
                $this->ensureRunIsActive($run);
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
                    $this->ensureRunIsActive($run);
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
                                ->withGuard(fn () => $this->ensureRunIsActive($run));
                            $toolStep->update([
                                'public_metadata' => ['outcome' => 'started', 'language' => $brief->language, 'runtime' => $brief->runtime, 'prompt_version' => AiCodingPromptBuilder::VERSION],
                                'private_payload' => [
                                    'coding_brief' => $brief->toArray(), 'source_message_id' => $sourceId,
                                    'execution_policy' => ['thinking_effort' => $thinkingEffort->value, 'request_timeout_seconds' => $coderOptions->timeoutSeconds, 'run_timeout_seconds' => $this->inferencePolicy->runTimeoutSeconds($thinkingEffort, true)],
                                ],
                            ]);
                            $generated = $this->performModelCall($toolStep, function () use ($userPrompt, $brief, $source, $coderOptions) {
                                $generated = $this->codeGenerationAgent->generate(
                                    $userPrompt, $brief, $this->codingInstructionRouter->forLanguage($brief->language),
                                    $coderOptions,
                                    $source?->content
                                );
                                if ($generated->hasToolCalls() || blank($generated->content) || ! str_contains($generated->content, '```')) {
                                    throw AiProviderException::invalidCodingResponse();
                                }

                                return $generated;
                            });
                            $this->ensureRunIsActive($run);
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
                        'private_payload' => $result,
                    ]);
                    $this->heartbeat($run, $deadlineAt);

                    $messageHistory[] = new LlmMessage(
                        role: 'tool',
                        content: null,
                        toolCalls: [],
                        toolResult: [
                            'call_id' => $call->id,
                            'tool_name' => $call->name,
                            'result' => $result,
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
                        $this->requestOptions($thinkingEffort, $deadlineAt, $minimumOutputTokens)->withGuard(fn () => $this->ensureRunIsActive($run))
                    )
                );
                $this->ensureRunIsActive($run);
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

            $this->ensureRunIsActive($run);
            $assistantMessage = DB::transaction(function () use ($userMessage, $session, $branch, $run, $proposalRows, $proposals, $finalReply, $finalReasoningContent, $startedAt): AiChatMessage {
                $lockedRun = AiChatRun::query()->lockForUpdate()->findOrFail($run->id);
                if ($lockedRun->status !== 'running') {
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
                $this->elapsedMilliseconds($startedAt)
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

    private function ensureRunIsActive(AiChatRun $run): void
    {
        $run->refresh();
        if ($run->status !== 'running') {
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
