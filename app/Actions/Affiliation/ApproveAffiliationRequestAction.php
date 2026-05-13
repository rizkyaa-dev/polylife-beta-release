<?php

namespace App\Actions\Affiliation;

use App\Models\AffiliationRequest;
use App\Models\User;
use App\Support\Endmin\AuditLogger;

class ApproveAffiliationRequestAction
{
    public function __construct(
        private readonly ResolveAffiliationTemplateAction $resolveAffiliationTemplateAction,
        private readonly \App\Actions\User\UpsertAdminAssignmentAction $upsertAdminAssignmentAction
    ) {}

    public function __invoke(
        User $actor,
        AffiliationRequest $request,
        ?int $templateId = null,
        ?string $canonicalType = null,
        ?string $canonicalName = null
    ): void
    {
        $template = ($this->resolveAffiliationTemplateAction)(
            $actor,
            $templateId,
            $canonicalType ?: $request->affiliation_type,
            $canonicalName ?: $request->affiliation_name
        );

        $user = $request->user;
        $before = $user?->only([
            'affiliation_type',
            'affiliation_name',
            'affiliation_template_id',
            'student_id_type',
            'student_id_number',
            'affiliation_status',
            'affiliation_verified_at',
            'affiliation_verified_by',
        ]);

        $user->forceFill([
            'affiliation_type' => $template->affiliation_type,
            'affiliation_name' => $template->affiliation_name,
            'affiliation_template_id' => $template->id,
            'student_id_type' => $request->student_id_type,
            'student_id_number' => $request->student_id_number,
            'affiliation_status' => 'verified',
            'affiliation_verified_at' => now(),
            'affiliation_verified_by' => $actor->id,
        ])->save();

        $request->forceFill([
            'affiliation_template_id' => $template->id,
            'affiliation_type' => $template->affiliation_type,
            'affiliation_name' => $template->affiliation_name,
            'status' => AffiliationRequest::STATUS_APPROVED,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ])->save();

        if ($user?->isAdminOnly() && $user->isActiveAccount()) {
            ($this->upsertAdminAssignmentAction)($user, 'active', null, (int) $actor->id);
        }

        AuditLogger::log(
            actor: $actor,
            module: 'affiliation',
            action: 'request_approve',
            targetUser: $user,
            before: $before,
            after: $user->only([
                'affiliation_type',
                'affiliation_name',
                'affiliation_template_id',
                'student_id_type',
                'student_id_number',
                'affiliation_status',
                'affiliation_verified_at',
                'affiliation_verified_by',
            ])
        );
    }
}
