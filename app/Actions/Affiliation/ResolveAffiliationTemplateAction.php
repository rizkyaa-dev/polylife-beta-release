<?php

namespace App\Actions\Affiliation;

use App\Models\AffiliationTemplate;
use App\Models\User;

class ResolveAffiliationTemplateAction
{
    public function __invoke(?User $actor, ?int $templateId, ?string $type, string $name): AffiliationTemplate
    {
        $normalizedType = $this->nullableString($type);
        $normalizedName = $this->normalizeName($name);

        if ($templateId) {
            $template = AffiliationTemplate::query()
                ->where('is_active', true)
                ->find($templateId);

            if ($template) {
                return $template;
            }
        }

        return AffiliationTemplate::query()->firstOrCreate(
            [
                'affiliation_type' => $normalizedType,
                'affiliation_name' => $normalizedName,
            ],
            [
                'aliases' => [],
                'is_active' => true,
                'created_by' => $actor?->id,
            ]
        );
    }

    private function normalizeName(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($value)) ?: trim($value);
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
