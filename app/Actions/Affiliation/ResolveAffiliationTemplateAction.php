<?php

namespace App\Actions\Affiliation;

use App\Models\AffiliationTemplate;
use App\Models\User;
use App\Support\Affiliation\AffiliationNormalizer;

class ResolveAffiliationTemplateAction
{
    public function __construct(private readonly AffiliationNormalizer $normalizer) {}

    public function __invoke(?User $actor, ?int $templateId, ?string $type, string $name): AffiliationTemplate
    {
        $normalizedType = $this->normalizer->type($type);
        $displayName = $this->normalizer->displayName($name);
        $normalizedName = $this->normalizer->nameKey($displayName);

        if ($templateId) {
            $template = AffiliationTemplate::query()
                ->where('is_active', true)
                ->whereNull('merged_into_id')
                ->find($templateId);

            if ($template) {
                return $template;
            }
        }

        $existing = AffiliationTemplate::query()
            ->where('is_active', true)
            ->whereNull('merged_into_id')
            ->where('normalized_name', $normalizedName)
            ->when(
                $normalizedType === null,
                fn ($query) => $query->whereNull('affiliation_type'),
                fn ($query) => $query->where('affiliation_type', $normalizedType)
            )
            ->first();

        if ($existing) {
            return $existing;
        }

        return AffiliationTemplate::query()->firstOrCreate(
            [
                'affiliation_type' => $normalizedType,
                'normalized_name' => $normalizedName,
            ],
            [
                'affiliation_name' => $displayName,
                'aliases' => [],
                'is_active' => true,
                'created_by' => $actor?->id,
            ]
        );
    }
}
