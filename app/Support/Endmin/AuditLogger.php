<?php

namespace App\Support\Endmin;

use App\Models\EndminAuditLog;
use App\Models\User;

class AuditLogger
{
    public static function log(
        ?User $actor,
        string $module,
        string $action,
        ?User $targetUser = null,
        array $before = [],
        array $after = [],
        array $context = []
    ): EndminAuditLog {
        $request = request();

        return EndminAuditLog::create([
            'actor_id' => $actor?->id,
            'target_user_id' => $targetUser?->id,
            'module' => $module,
            'action' => $action,
            'before_data' => empty($before) ? null : $before,
            'after_data' => empty($after) ? null : $after,
            'context' => empty($context) ? null : $context,
            'ip_address' => $request ? $request->ip() : null,
            'user_agent' => $request ? (string) $request->userAgent() : null,
        ]);
    }
}
