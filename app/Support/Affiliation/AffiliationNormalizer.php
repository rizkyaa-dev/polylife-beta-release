<?php

namespace App\Support\Affiliation;

class AffiliationNormalizer
{
    /**
     * @var array<string, string>
     */
    private array $canonicalWords = [
        'univ' => 'universitas',
        'university' => 'universitas',
        'poltek' => 'politeknik',
        'polytechnic' => 'politeknik',
        'inst' => 'institut',
        'institute' => 'institut',
        'akademy' => 'akademi',
        'academy' => 'akademi',
    ];

    public function displayName(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($value)) ?: trim($value);
    }

    public function type(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public function nameKey(string $value): string
    {
        $normalized = mb_strtolower($this->displayName($value));
        $normalized = preg_replace('/[^\pL\pN]+/u', ' ', $normalized) ?: '';
        $normalized = preg_replace('/\s+/', ' ', trim($normalized)) ?: '';

        if ($normalized === '') {
            return '';
        }

        $tokens = array_map(
            fn (string $token): string => $this->canonicalWords[$token] ?? $token,
            explode(' ', $normalized)
        );

        return implode(' ', $tokens);
    }

    /**
     * @return list<string>
     */
    public function aliases(?array $aliases): array
    {
        $normalized = [];

        foreach ($aliases ?? [] as $alias) {
            $alias = $this->displayName((string) $alias);

            if ($alias !== '') {
                $normalized[] = $alias;
            }
        }

        return array_values(array_unique($normalized));
    }
}
