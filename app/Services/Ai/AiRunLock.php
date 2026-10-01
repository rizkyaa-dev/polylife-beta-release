<?php

namespace App\Services\Ai;

use App\Models\AiChatRun;
use App\Models\AiChatSession;

/** Use inside a transaction: session → run → science ticket → step/branch. */
final class AiRunLock
{
    public static function find(int $runId): ?AiChatRun
    {
        $sessionId = AiChatRun::query()->whereKey($runId)->value('session_id');
        if ($sessionId === null || ! AiChatSession::query()->lockForUpdate()->find($sessionId)) {
            return null;
        }

        return AiChatRun::query()->where('session_id', $sessionId)->lockForUpdate()->find($runId);
    }
}
