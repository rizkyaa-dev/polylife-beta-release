<?php

namespace App\Services\Ai;

use App\Models\AiChatBranch;
use App\Models\AiChatMessage;
use App\Models\AiChatRun;
use App\Models\AiChatSession;
use App\Models\User;
use App\Services\Ai\Exceptions\AiConversationBusyException;
use App\Services\Ai\Exceptions\AiIdempotencyConflictException;
use App\Services\Ai\Exceptions\AiSystemCapacityException;
use App\Services\Ai\Exceptions\AiUserCapacityException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AiConversationBranchService
{
    private const CONTEXT_MESSAGE_LIMIT = 240;

    public function __construct(private readonly AiRunStateManager $runStateManager) {}

    /**
     * @return array{session: AiChatSession, branch: AiChatBranch, user_message: AiChatMessage, run: AiChatRun, parent_id: ?int}
     */
    public function beginTurn(
        User $user,
        string $prompt,
        ?int $sessionId = null,
        ?int $editedMessageId = null,
        ?string $requestId = null
    ): array {
        if ($requestId && $existing = $this->existingTurn($user, $requestId, $prompt, $sessionId, $editedMessageId)) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($user, $prompt, $sessionId, $editedMessageId, $requestId): array {
                $session = $sessionId
                        ? AiChatSession::query()->where('user_id', $user->id)->lockForUpdate()->findOrFail($sessionId)
                        : AiChatSession::query()->create([
                            'user_id' => $user->id,
                            'title' => Str::limit($prompt, 60, '...'),
                        ]);

                User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

                $this->recoverExpiredRuns($session);
                if ($requestId && $existing = $this->existingTurn($user, $requestId, $prompt, $sessionId, $editedMessageId)) {
                    return $existing;
                }
                $activeRuns = AiChatRun::query()
                    ->where('status', 'running')
                    ->whereHas('session', fn ($query) => $query->where('user_id', $user->id))
                    ->count();
                if ($activeRuns >= max(1, (int) config('services.ai_max_active_runs_per_user', 2))) {
                    throw new AiUserCapacityException;
                }
                $globalActiveRuns = AiChatRun::query()->where('status', 'running')->count();
                if ($globalActiveRuns >= max(1, (int) config('services.ai_max_active_runs_global', 100))) {
                    throw new AiSystemCapacityException;
                }
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
                    'request_id' => $requestId,
                    'request_fingerprint' => $this->requestFingerprint($prompt, $sessionId, $editedMessageId),
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
                    'is_new' => true,
                ];
            });
        } catch (QueryException $exception) {
            if ($requestId && $existing = $this->existingTurn($user, $requestId, $prompt, $sessionId, $editedMessageId)) {
                return $existing;
            }

            throw $exception;
        }
    }

    /**
     * @return array{session: AiChatSession, branch: AiChatBranch, user_message: AiChatMessage, run: AiChatRun, parent_id: ?int}|null
     */
    private function existingTurn(User $user, string $requestId, string $prompt, ?int $sessionId, ?int $editedMessageId): ?array
    {
        $run = AiChatRun::query()
            ->where('request_id', $requestId)
            ->whereHas('session', fn ($query) => $query->where('user_id', $user->id))
            ->with(['session', 'branch', 'userMessage'])
            ->first();
        if (! $run || ! $run->session || ! $run->branch || ! $run->userMessage) {
            return null;
        }
        if (! hash_equals((string) $run->userMessage->content, $prompt)) {
            throw new AiIdempotencyConflictException;
        }
        if ($run->request_fingerprint !== null
            && ! hash_equals($run->request_fingerprint, $this->requestFingerprint($prompt, $sessionId, $editedMessageId))) {
            throw new AiIdempotencyConflictException;
        }
        if ($sessionId !== null && $run->session_id !== $sessionId) {
            throw new AiIdempotencyConflictException;
        }

        return [
            'session' => $run->session,
            'branch' => $run->branch,
            'user_message' => $run->userMessage,
            'run' => $run,
            'parent_id' => $run->userMessage->parent_message_id,
            'is_new' => false,
        ];
    }

    private function requestFingerprint(string $prompt, ?int $sessionId, ?int $editedMessageId): string
    {
        return hash('sha256', json_encode([$prompt, $sessionId, $editedMessageId], JSON_THROW_ON_ERROR));
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

    /**
     * Return a bounded ancestor window without hydrating every message in the session.
     *
     * @return EloquentCollection<int, AiChatMessage>
     */
    public function recentLineage(
        AiChatSession $session,
        ?int $headMessageId = null,
        int $limit = self::CONTEXT_MESSAGE_LIMIT
    ): EloquentCollection {
        $headMessageId ??= $this->ensureActiveBranch($session)->head_message_id;

        return $this->ancestorWindow($session, $headMessageId, $limit);
    }

    /**
     * Read the active branch without repairing or creating conversation state.
     * Write paths continue to use ensureActiveBranch() inside a transaction.
     *
     * @return EloquentCollection<int, AiChatMessage>
     */
    public function recentActiveLineage(
        AiChatSession $session,
        int $limit = self::CONTEXT_MESSAGE_LIMIT
    ): EloquentCollection {
        if (! $session->active_branch_id) {
            return $this->recentUnbranchedMessages($session, $limit);
        }

        $branch = $session->branches()->find($session->active_branch_id);

        if (! $branch) {
            return $this->recentUnbranchedMessages($session, $limit);
        }

        return $this->ancestorWindow($session, $branch->head_message_id, $limit);
    }

    /** @return EloquentCollection<int, AiChatMessage> */
    private function recentUnbranchedMessages(AiChatSession $session, int $limit): EloquentCollection
    {
        $messages = $session->messages()
            ->reorder()
            ->latest('created_at')
            ->latest('id')
            ->limit(max(1, $limit))
            ->get()
            ->reverse()
            ->values();

        return new EloquentCollection($messages->all());
    }

    /**
     * @return array{messages: EloquentCollection<int, AiChatMessage>, has_more: bool}
     */
    public function completedPageBefore(AiChatSession $session, int $beforeMessageId, int $limit = 100): array
    {
        $cursor = $session->messages()->whereKey($beforeMessageId)->firstOrFail();

        if ($cursor->branch_id === null) {
            return $this->completedUnbranchedPageBefore($session, $cursor->id, $limit);
        }

        $window = $this->ancestorWindow($session, $cursor->parent_message_id, $limit + 1, true);
        $hasMore = $window->count() > $limit;

        if ($hasMore) {
            $window = new EloquentCollection($window->take(-$limit)->values()->all());
        }

        return ['messages' => $window, 'has_more' => $hasMore];
    }

    /**
     * Legacy sessions have no parent chain. Keep their pagination read-only and
     * bounded until the next write path upgrades the session transactionally.
     *
     * @return array{messages: EloquentCollection<int, AiChatMessage>, has_more: bool}
     */
    private function completedUnbranchedPageBefore(
        AiChatSession $session,
        int $beforeMessageId,
        int $limit
    ): array {
        $window = $session->messages()
            ->where('id', '<', $beforeMessageId)
            ->where('status', 'completed')
            ->whereIn('role', ['user', 'assistant'])
            ->reorder()
            ->latest('id')
            ->limit(max(1, $limit) + 1)
            ->get();
        $hasMore = $window->count() > $limit;
        $messages = $window
            ->take(max(1, $limit))
            ->reverse()
            ->values();

        return [
            'messages' => new EloquentCollection($messages->all()),
            'has_more' => $hasMore,
        ];
    }

    /**
     * @return EloquentCollection<int, AiChatMessage>
     */
    private function ancestorWindow(
        AiChatSession $session,
        ?int $headMessageId,
        int $limit,
        bool $completedOnly = false
    ): EloquentCollection {
        if ($headMessageId === null || $limit < 1) {
            return new EloquentCollection;
        }

        // Extra traversal room prevents a small number of failed/pending messages from
        // reducing a completed-only page while keeping the query strictly bounded.
        $maxDepth = $completedOnly ? max($limit * 3, $limit + 16) : $limit;
        $statusFilter = $completedOnly
            ? "WHERE status = 'completed' AND role IN ('user', 'assistant')"
            : '';
        $sql = <<<SQL
            WITH RECURSIVE lineage AS (
                SELECT ai_chat_messages.*, 0 AS lineage_depth
                FROM ai_chat_messages
                WHERE session_id = ? AND id = ?

                UNION ALL

                SELECT parent.*, lineage.lineage_depth + 1
                FROM ai_chat_messages AS parent
                INNER JOIN lineage ON lineage.parent_message_id = parent.id
                WHERE parent.session_id = ? AND lineage.lineage_depth < ?
            )
            SELECT * FROM lineage
            {$statusFilter}
            ORDER BY lineage_depth ASC
            LIMIT ?
            SQL;
        $rows = DB::select($sql, [
            $session->id,
            $headMessageId,
            $session->id,
            $maxDepth - 1,
            $limit,
        ]);
        $messages = array_map(function (object $row): AiChatMessage {
            $attributes = (array) $row;
            unset($attributes['lineage_depth']);

            return (new AiChatMessage)->newFromBuilder($attributes);
        }, array_reverse($rows));

        return new EloquentCollection($messages);
    }

    /** @return EloquentCollection<int, AiChatMessage> */
    public function visibleMessages(AiChatSession $session, int $limit = 100): EloquentCollection
    {
        return new EloquentCollection($this->recentLineage($session, limit: $limit * 2)
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
