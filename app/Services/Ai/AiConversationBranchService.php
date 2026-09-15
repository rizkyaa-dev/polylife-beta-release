<?php

namespace App\Services\Ai;

use App\Models\AiChatBranch;
use App\Models\AiChatMessage;
use App\Models\AiChatRun;
use App\Models\AiChatSession;
use App\Models\User;
use App\Services\Ai\Exceptions\AiConversationBusyException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AiConversationBranchService
{
    public function __construct(private readonly AiRunStateManager $runStateManager) {}

    /**
     * @return array{session: AiChatSession, branch: AiChatBranch, user_message: AiChatMessage, run: AiChatRun, parent_id: ?int}
     */
    public function beginTurn(User $user, string $prompt, ?int $sessionId = null, ?int $editedMessageId = null): array
    {
        return DB::transaction(function () use ($user, $prompt, $sessionId, $editedMessageId): array {
            $session = $sessionId
                ? AiChatSession::query()->where('user_id', $user->id)->lockForUpdate()->findOrFail($sessionId)
                : AiChatSession::query()->create([
                    'user_id' => $user->id,
                    'title' => Str::limit($prompt, 60, '...'),
                ]);

            $this->recoverExpiredRuns($session);
            if ($session->runs()->where('status', 'running')->exists()) {
                throw new AiConversationBusyException;
            }

            $previousBranchId = $session->active_branch_id;
            if ($editedMessageId !== null) {
                $edited = $session->messages()
                    ->whereKey($editedMessageId)
                    ->where('role', 'user')
                    ->where('status', 'completed')
                    ->firstOrFail();
                $parentId = $edited->parent_message_id;
                $branch = $session->branches()->create([
                    'forked_from_message_id' => $parentId,
                    'head_message_id' => $parentId,
                ]);
                $revisionGroupId = $edited->revision_group_id ?: (string) Str::uuid();
                if ($edited->revision_group_id === null) {
                    $edited->update(['revision_group_id' => $revisionGroupId]);
                }
            } else {
                $branch = $this->ensureActiveBranch($session);
                $parentId = $branch->head_message_id;
                $revisionGroupId = (string) Str::uuid();
            }

            $userMessage = $session->messages()->create([
                'parent_message_id' => $parentId,
                'branch_id' => $branch->id,
                'revision_group_id' => $revisionGroupId,
                'role' => 'user',
                'content' => $prompt,
                'status' => 'pending',
            ]);
            $branch->update(['head_message_id' => $userMessage->id]);
            $session->update(['active_branch_id' => $branch->id]);

            $run = $session->runs()->create([
                'branch_id' => $branch->id,
                'previous_branch_id' => $previousBranchId,
                'user_message_id' => $userMessage->id,
                'status' => 'running',
                'started_at' => now(),
                'lease_expires_at' => now()->addMinutes(10),
            ]);

            return compact('session', 'branch', 'userMessage', 'run', 'parentId') + [
                'user_message' => $userMessage,
                'parent_id' => $parentId,
            ];
        });
    }

    public function activate(User $user, int $branchId): AiChatSession
    {
        return DB::transaction(function () use ($user, $branchId): AiChatSession {
            $branch = AiChatBranch::query()
                ->whereKey($branchId)
                ->whereHas('session', fn ($query) => $query->where('user_id', $user->id))
                ->firstOrFail();
            $session = AiChatSession::query()->whereKey($branch->session_id)->lockForUpdate()->firstOrFail();

            $this->recoverExpiredRuns($session);
            if ($session->runs()->where('status', 'running')->exists()) {
                throw new AiConversationBusyException;
            }

            $session->update(['active_branch_id' => $branch->id]);

            return $session;
        });
    }

    public function ensureActiveBranch(AiChatSession $session): AiChatBranch
    {
        if ($session->active_branch_id) {
            $branch = $session->branches()->find($session->active_branch_id);
            if ($branch) {
                return $branch;
            }
        }

        $branch = $session->branches()->oldest('id')->first();
        if (! $branch) {
            $branch = $session->branches()->create();
            $parentId = null;
            foreach ($session->messages()->orderBy('created_at')->orderBy('id')->get() as $message) {
                $message->update([
                    'parent_message_id' => $parentId,
                    'branch_id' => $branch->id,
                    'revision_group_id' => $message->revision_group_id ?: (string) Str::uuid(),
                ]);
                $parentId = $message->id;
            }
            $branch->update(['head_message_id' => $parentId]);
        }

        $session->update(['active_branch_id' => $branch->id]);

        return $branch;
    }

    /** @return EloquentCollection<int, AiChatMessage> */
    public function lineage(AiChatSession $session, ?int $headMessageId = null): EloquentCollection
    {
        $headMessageId ??= $this->ensureActiveBranch($session)->head_message_id;
        if ($headMessageId === null) {
            return new EloquentCollection;
        }

        $messages = $session->messages()->reorder()->get()->keyBy('id');
        $lineage = [];
        $visited = [];
        $messageId = $headMessageId;

        while ($messageId !== null) {
            if (isset($visited[$messageId])) {
                throw new RuntimeException('Siklus terdeteksi pada riwayat percakapan AI.');
            }
            $message = $messages->get($messageId);
            if (! $message) {
                throw new RuntimeException('Rantai percakapan AI tidak lengkap.');
            }
            $visited[$messageId] = true;
            $lineage[] = $message;
            $messageId = $message->parent_message_id;
        }

        return new EloquentCollection(array_reverse($lineage));
    }

    /** @return EloquentCollection<int, AiChatMessage> */
    public function visibleMessages(AiChatSession $session, int $limit = 100): EloquentCollection
    {
        return new EloquentCollection($this->lineage($session)
            ->filter(fn (AiChatMessage $message) => $message->status === 'completed' && in_array($message->role, ['user', 'assistant'], true))
            ->take(-$limit)
            ->values()
            ->all());
    }

    private function recoverExpiredRuns(AiChatSession $session): void
    {
        $expiredRuns = $session->runs()
            ->where('status', 'running')
            ->whereNotNull('lease_expires_at')
            ->where('lease_expires_at', '<=', now())
            ->lockForUpdate()
            ->get();

        foreach ($expiredRuns as $run) {
            $this->runStateManager->expire($run);
        }
    }
}
