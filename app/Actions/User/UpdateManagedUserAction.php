<?php

namespace App\Actions\User;

use App\Actions\Affiliation\ResolveAffiliationTemplateAction;
use App\Models\AdminAssignment;
use App\Models\AffiliationTemplate;
use App\Models\User;
use App\Support\Endmin\AuditLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UpdateManagedUserAction
{
    public function __construct(
        private readonly CaptureUserSnapshotAction $captureUserSnapshotAction,
        private readonly ResolveAffiliationTemplateAction $resolveAffiliationTemplateAction,
        private readonly UpsertAdminAssignmentAction $upsertAdminAssignmentAction
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function __invoke(User $actor, User $user, array $validated): void
    {
        $before = ($this->captureUserSnapshotAction)($user);
        $isSelf = (int) $actor->id === (int) $user->id;
        $targetIsSuperAdmin = $user->isSuperAdmin();
        $password = (string) ($validated['password'] ?? '');

        if ($targetIsSuperAdmin && ! $isSelf && $password !== '') {
            throw ValidationException::withMessages([
                'password' => 'Password super admin lain tidak dapat diubah.',
            ]);
        }

        if ($targetIsSuperAdmin && ! $isSelf && $validated['account_status'] === 'banned') {
            throw ValidationException::withMessages([
                'account_status' => 'Super admin tidak dapat membanned sesama super admin.',
            ]);
        }

        if ($validated['account_status'] === 'banned' && $isSelf) {
            throw ValidationException::withMessages([
                'account_status' => 'Tidak bisa memblokir akun sendiri.',
            ]);
        }

        $emailChanged = $validated['email'] !== $user->email;
        $affiliationStatusChanged = ($validated['affiliation_status'] ?? $user->affiliation_status) !== $user->affiliation_status;
        $affiliationChanged = $this->affiliationChanged($user, $validated);
        $oldAdminLevel = $user->adminLevel();
        $requestedEmailVerified = (bool) ($validated['email_verified'] ?? false);
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->account_status = $validated['account_status'];
        $user->affiliation_status = $validated['affiliation_status'];
        $user->student_id_type = $this->nullableString($validated['student_id_type'] ?? null);
        $user->student_id_number = $this->nullableString($validated['student_id_number'] ?? null);
        $this->applyAffiliation($actor, $user, $validated);

        if ($password !== '') {
            $user->password = Hash::make($password);
        }

        if ($emailChanged) {
            $user->email_verified_at = ($requestedEmailVerified || $user->isSuperAdmin()) ? now() : null;
        } elseif (array_key_exists('email_verified', $validated)) {
            $user->email_verified_at = $requestedEmailVerified ? ($user->email_verified_at ?: now()) : null;
        }

        if ($user->affiliation_status === 'verified') {
            $user->affiliation_verified_at = $user->affiliation_verified_at ?: now();
            $user->affiliation_verified_by = $user->affiliation_verified_by ?: $actor->id;
        } else {
            $user->affiliation_verified_at = null;
            $user->affiliation_verified_by = null;
        }

        if ($validated['account_status'] === 'banned') {
            $user->banned_at = $user->banned_at ?: now();
            $user->banned_by = $actor->id;
            $user->ban_reason_code = $validated['ban_reason_code'] ?? null;
            $user->ban_reason_text = $validated['ban_reason_text'] ?? null;
        } else {
            $user->banned_at = null;
            $user->banned_by = null;
            $user->ban_reason_code = null;
            $user->ban_reason_text = null;
        }

        $user->save();

        if ($oldAdminLevel === User::ADMIN_LEVEL_ADMIN && ($affiliationChanged || $affiliationStatusChanged)) {
            if ($user->affiliation_status === 'verified' && $user->isActiveAccount()) {
                AdminAssignment::query()
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->where(function ($query) use ($user): void {
                        $query->where('affiliation_template_id', '!=', $user->affiliation_template_id)
                            ->orWhereNull('affiliation_template_id')
                            ->orWhere('affiliation_name', '!=', $user->affiliation_name);
                    })
                    ->update([
                        'status' => 'revoked',
                        'revoked_by' => $actor->id,
                        'revoked_at' => now(),
                        'revoke_reason' => 'Afiliasi admin dipindahkan.',
                        'updated_at' => now(),
                    ]);
            }

            ($this->upsertAdminAssignmentAction)(
                $user,
                $user->affiliation_status === 'verified' && $user->isActiveAccount() ? 'active' : 'revoked',
                $user->affiliation_status === 'verified' ? null : 'Afiliasi admin tidak terverifikasi.',
                $actor->id
            );
        }

        AuditLogger::log(
            actor: $actor,
            module: 'users',
            action: 'update_account',
            targetUser: $user,
            before: $before,
            after: ($this->captureUserSnapshotAction)($user),
            context: [
                'password_updated' => $password !== '',
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function applyAffiliation(User $actor, User $user, array $validated): void
    {
        $templateId = $validated['affiliation_template_id'] ?? null;

        if ($templateId) {
            $template = AffiliationTemplate::query()
                ->where('is_active', true)
                ->find($templateId);

            if (! $template) {
                throw ValidationException::withMessages([
                    'affiliation_template_id' => 'Template afiliasi tidak valid.',
                ]);
            }

            $user->affiliation_template_id = $template->id;
            $user->affiliation_type = $template->affiliation_type;
            $user->affiliation_name = $template->affiliation_name;

            return;
        }

        $manualName = $this->nullableString($validated['affiliation_name'] ?? null);
        $manualType = $this->nullableString($validated['affiliation_type'] ?? null);

        if ($user->affiliation_status === 'verified' && $manualName) {
            $template = ($this->resolveAffiliationTemplateAction)(
                $actor,
                null,
                $manualType,
                $manualName
            );

            $user->affiliation_template_id = $template->id;
            $user->affiliation_type = $template->affiliation_type;
            $user->affiliation_name = $template->affiliation_name;

            return;
        }

        $user->affiliation_template_id = null;
        $user->affiliation_type = $manualType;
        $user->affiliation_name = $manualName;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function affiliationChanged(User $user, array $validated): bool
    {
        return (int) ($validated['affiliation_template_id'] ?? 0) !== (int) ($user->affiliation_template_id ?? 0)
            || $this->nullableString($validated['affiliation_type'] ?? null) !== $this->nullableString($user->affiliation_type)
            || $this->nullableString($validated['affiliation_name'] ?? null) !== $this->nullableString($user->affiliation_name);
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
