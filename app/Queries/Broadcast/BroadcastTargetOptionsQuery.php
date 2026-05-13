<?php

namespace App\Queries\Broadcast;

use App\Models\AdminAssignment;
use App\Models\AffiliationBroadcast;
use App\Models\AffiliationTemplate;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class BroadcastTargetOptionsQuery
{
    /**
     * @return array{targetOptions: array<int, array<string, mixed>>, canUseGlobal: bool, creationBlocked: bool}
     */
    public function forActor(User $actor): array
    {
        if ($actor->isSuperAdmin()) {
            $templateOptions = AffiliationTemplate::query()
                ->select('id as affiliation_template_id', 'affiliation_type', 'affiliation_name')
                ->where('is_active', true)
                ->orderBy('affiliation_name')
                ->get();

            $legacyOptions = User::query()
                ->selectRaw('NULL as affiliation_template_id, affiliation_type, affiliation_name')
                ->whereNull('affiliation_template_id')
                ->whereNotNull('affiliation_name')
                ->where('affiliation_name', '!=', '')
                ->distinct()
                ->orderBy('affiliation_name')
                ->get();

            $rawOptions = $templateOptions->concat($legacyOptions);
            $targetOptions = $this->mapTargetOptions($rawOptions->all());

            return [
                'targetOptions' => $targetOptions,
                'canUseGlobal' => true,
                'creationBlocked' => $targetOptions === [],
            ];
        }

        $rawOptions = AdminAssignment::query()
            ->select('affiliation_template_id', 'affiliation_type', 'affiliation_name')
            ->where('user_id', $actor->id)
            ->where('status', 'active')
            ->whereNotNull('affiliation_name')
            ->where('affiliation_name', '!=', '')
            ->where('affiliation_name', '!=', 'Unassigned Affiliation')
            ->distinct()
            ->orderBy('affiliation_name')
            ->get();

        if ($actor->affiliation_status !== 'verified') {
            $rawOptions = collect();
        } elseif ($rawOptions->isEmpty() && filled($actor->affiliation_name)) {
            $rawOptions = collect([
                (object) [
                    'affiliation_template_id' => $actor->affiliation_template_id,
                    'affiliation_type' => $actor->affiliation_type,
                    'affiliation_name' => $actor->affiliation_name,
                ],
            ]);
        }

        $targetOptions = $this->mapTargetOptions($rawOptions->all());

        return [
            'targetOptions' => $targetOptions,
            'canUseGlobal' => false,
            'creationBlocked' => $targetOptions === [],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function selectedValues(AffiliationBroadcast $broadcast): array
    {
        return $broadcast->targets
            ->map(fn ($target) => $this->encodeTargetValue($target->affiliation_type, $target->affiliation_name, $target->affiliation_template_id))
            ->values()
            ->all();
    }

    public function resolveTargetMode(User $actor, ?string $requestedTargetMode, bool $canUseGlobal): string
    {
        if (! $actor->isSuperAdmin() || ! $canUseGlobal) {
            return AffiliationBroadcast::TARGET_MODE_AFFILIATION;
        }

        return $requestedTargetMode === AffiliationBroadcast::TARGET_MODE_GLOBAL
            ? AffiliationBroadcast::TARGET_MODE_GLOBAL
            : AffiliationBroadcast::TARGET_MODE_AFFILIATION;
    }

    /**
     * @param  array<int, string>  $rawTargets
     * @param  array<int, array<string, mixed>>  $targetOptions
     * @return array<int, array{affiliation_template_id: ?int, affiliation_type: ?string, affiliation_name: string}>
     */
    public function resolveSelectedTargets(array $rawTargets, array $targetOptions, string $targetMode): array
    {
        if ($targetMode === AffiliationBroadcast::TARGET_MODE_GLOBAL) {
            return [];
        }

        $allowedMap = collect($targetOptions)
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option])
            ->all();

        $selected = [];
        foreach (array_unique(array_map('strval', $rawTargets)) as $rawTargetValue) {
            $targetValue = trim($rawTargetValue);
            if ($targetValue === '') {
                continue;
            }

            if (! array_key_exists($targetValue, $allowedMap)) {
                throw ValidationException::withMessages([
                    'targets' => 'Terdapat target afiliasi yang tidak diizinkan.',
                ]);
            }

            $selected[] = [
                'affiliation_type' => $allowedMap[$targetValue]['affiliation_type'],
                'affiliation_name' => $allowedMap[$targetValue]['affiliation_name'],
                'affiliation_template_id' => $allowedMap[$targetValue]['affiliation_template_id'],
            ];
        }

        return $selected;
    }

    /**
     * @param  array<int, mixed>  $rawOptions
     * @return array<int, array<string, mixed>>
     */
    private function mapTargetOptions(array $rawOptions): array
    {
        $options = [];
        $seen = [];

        foreach ($rawOptions as $option) {
            $type = filled($option->affiliation_type ?? null)
                ? trim((string) $option->affiliation_type)
                : null;
            $name = trim((string) ($option->affiliation_name ?? ''));
            $templateId = filled($option->affiliation_template_id ?? null)
                ? (int) $option->affiliation_template_id
                : null;

            if ($name === '') {
                continue;
            }

            $key = $this->encodeTargetValue($type, $name, $templateId);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $labelPrefix = $type ? strtoupper($type) . ' - ' : '';
            $options[] = [
                'value' => $key,
                'affiliation_template_id' => $templateId,
                'affiliation_type' => $type,
                'affiliation_name' => $name,
                'label' => $labelPrefix . $name,
            ];
        }

        return $options;
    }

    private function encodeTargetValue(?string $affiliationType, string $affiliationName, ?int $templateId = null): string
    {
        if ($templateId) {
            return 'template:'.$templateId;
        }

        return ($affiliationType ?: '') . '||' . $affiliationName;
    }
}
