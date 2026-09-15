<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\AiChatRun;
use App\Services\Ai\ActionProposalExecutor;
use App\Services\Ai\AiActionReceiptPresenter;
use App\Services\Ai\AiAgentOrchestrator;
use App\Services\Ai\AiChatResponseFactory;
use App\Services\Ai\AiRunErrorPresenter;
use App\Services\Ai\AiRunStateManager;
use App\Services\Ai\Exceptions\AiActionException;
use App\Services\Ai\Exceptions\AiConversationBusyException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class AiChatController extends Controller
{
    public function __construct(
        private readonly AiAgentOrchestrator $orchestrator,
        private readonly ActionProposalExecutor $proposalExecutor,
        private readonly AiChatResponseFactory $responseFactory,
        private readonly AiRunErrorPresenter $errorPresenter,
        private readonly AiRunStateManager $runStateManager,
        private readonly AiActionReceiptPresenter $receiptPresenter
    ) {}

    public function sendMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'session_id' => [
                'nullable',
                'integer',
                Rule::exists('ai_chat_sessions', 'id')->where(fn ($query) => $query->where('user_id', $request->user()->id)),
            ],
        ]);

        try {
            $result = $this->orchestrator->enqueue(
                $request->user(),
                $validated['message'],
                $validated['session_id'] ?? null
            );

            return response()->json($this->acceptedResponse($result), 202);
        } catch (AiConversationBusyException $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 409);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => 'error',
                'message' => 'Pesan belum dapat diproses. Silakan coba kembali sesaat lagi.',
            ], 500);
        }
    }

    public function editMessage(Request $request, int $message): JsonResponse
    {
        $ownedMessage = $request->user()->aiChatSessions()
            ->whereHas('messages', fn ($query) => $query->whereKey($message))
            ->firstOrFail()
            ->messages()
            ->whereKey($message)
            ->where('role', 'user')
            ->where('status', 'completed')
            ->firstOrFail();
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        try {
            return response()->json($this->acceptedResponse(
                $this->orchestrator->enqueueEdit($request->user(), $ownedMessage->id, $validated['message'])
            ), 202);
        } catch (AiConversationBusyException $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 409);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => 'error',
                'message' => 'Pesan belum dapat diedit. Versi sebelumnya tetap aman.',
            ], 500);
        }
    }

    public function runStatus(Request $request, int $run): JsonResponse
    {
        $chatRun = AiChatRun::query()
            ->whereKey($run)
            ->whereHas('session', fn ($query) => $query->where('user_id', $request->user()->id))
            ->firstOrFail();

        if ($chatRun->status === 'running' && $chatRun->lease_expires_at?->isPast()) {
            $this->runStateManager->expire($chatRun);
            $chatRun->refresh();
        }

        if ($chatRun->status === 'completed') {
            return response()->json($this->responseFactory->completed($chatRun));
        }
        if ($chatRun->status === 'failed') {
            if ($chatRun->error_code === 'user_cancelled') {
                return response()->json([
                    'status' => 'cancelled',
                    'run_id' => $chatRun->id,
                    'message' => $this->errorPresenter->message($chatRun->error_code),
                ]);
            }

            return response()->json([
                'status' => 'failed',
                'run_id' => $chatRun->id,
                'retryable' => $chatRun->retryable,
                'message' => $this->errorPresenter->message($chatRun->error_code),
            ]);
        }

        return response()->json([
            'status' => 'running',
            'run_id' => $chatRun->id,
            'steps' => $chatRun->steps()->get()->map(fn ($step) => [
                'kind' => $step->kind,
                'status' => $step->status,
                'label' => $step->label,
            ])->values(),
        ], 202);
    }

    public function cancelRun(Request $request, int $run): JsonResponse
    {
        $chatRun = AiChatRun::query()
            ->whereKey($run)
            ->whereHas('session', fn ($query) => $query->where('user_id', $request->user()->id))
            ->firstOrFail();

        if ($chatRun->status === 'running') {
            $this->runStateManager->fail($chatRun, 'user_cancelled', false);
        }

        return response()->json([
            'status' => 'success',
            'run_status' => $chatRun->fresh()->error_code === 'user_cancelled' ? 'cancelled' : $chatRun->fresh()->status,
        ]);
    }

    public function confirmAction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action_id' => ['required', 'string'],
            'signature' => ['required', 'string'],
        ]);

        try {
            $result = $this->proposalExecutor->execute(
                $request->user(),
                $validated['action_id'],
                $validated['signature']
            );
            $receipt = $this->receiptPresenter->present($result['proposal']->tool_name);

            return response()->json([
                'status' => 'success',
                'message' => $receipt['message'],
                'action_id' => $result['proposal']->action_id,
                'receipt' => $receipt,
            ]);
        } catch (AiActionException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 422);
        } catch (ValidationException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data proposal tidak valid.',
                'errors' => $exception->errors(),
            ], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => 'error',
                'message' => 'Aksi tidak dapat diproses saat ini.',
            ], 500);
        }
    }

    public function rejectAction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action_id' => ['required', 'string'],
        ]);

        try {
            $proposal = $this->proposalExecutor->reject(
                $request->user(),
                $validated['action_id']
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Proposal aksi telah dibatalkan.',
                'action_id' => $proposal->action_id,
            ]);
        } catch (AiActionException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => 'error',
                'message' => 'Proposal tidak dapat dibatalkan saat ini.',
            ], 500);
        }
    }

    /** @param array<string, mixed> $result */
    private function acceptedResponse(array $result): array
    {
        return [
            'status' => 'accepted',
            'run_id' => $result['run']->id,
            'session_id' => $result['session']->id,
            'active_branch_id' => $result['branch']->id,
            'user_message' => [
                'id' => $result['user_message']->id,
                'content' => $result['user_message']->content,
                'branch_id' => $result['user_message']->branch_id,
            ],
        ];
    }
}
