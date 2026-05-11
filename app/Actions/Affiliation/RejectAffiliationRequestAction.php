<?php

namespace App\Actions\Affiliation;

use App\Models\AffiliationRequest;
use App\Models\User;
use App\Support\Endmin\AuditLogger;

class RejectAffiliationRequestAction
{
    public function __invoke(User $actor, AffiliationRequest $request, ?string $reason = null): void
    {
        $before = $request->only(['status', 'rejection_reason', 'reviewed_by', 'reviewed_at']);

        $request->forceFill([
            'status' => AffiliationRequest::STATUS_REJECTED,
            'rejection_reason' => $reason ? trim($reason) : null,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ])->save();

        AuditLogger::log(
            actor: $actor,
            module: 'affiliation',
            action: 'request_reject',
            targetUser: $request->user,
            before: $before,
            after: $request->only(['status', 'rejection_reason', 'reviewed_by', 'reviewed_at'])
        );
    }
}
