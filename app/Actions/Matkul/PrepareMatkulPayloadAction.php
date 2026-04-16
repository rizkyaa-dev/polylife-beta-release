<?php

namespace App\Actions\Matkul;

use Illuminate\Support\Str;

class PrepareMatkulPayloadAction
{
    public function __invoke(array $data): array
    {
        $data['kelas'] = $this->normalizeKelasValues($data['kelas'] ?? '');
        $data['hari'] = $this->buildSemicolonFromInput($data['hari'] ?? null, true) ?? $data['hari'];
        $data['jam_mulai'] = $this->buildSemicolonFromInput($data['jam_mulai'] ?? null) ?? $data['jam_mulai'];
        $data['jam_selesai'] = $this->buildSemicolonFromInput($data['jam_selesai'] ?? null) ?? $data['jam_selesai'];
        $data['ruangan'] = $this->buildSemicolonFromInput($data['ruangan'] ?? null) ?? $data['ruangan'];

        return $data;
    }

    private function formatSemicolonValues(iterable $values, bool $lowercase = false): ?string
    {
        $normalized = collect($values)
            ->map(function ($value) use ($lowercase) {
                $value = trim((string) $value);
                if ($lowercase) {
                    $value = Str::lower($value);
                }

                return $value;
            })
            ->filter(fn ($value) => $value !== '')
            ->values();

        if ($normalized->isEmpty()) {
            return null;
        }

        return $normalized->implode(';') . ';';
    }

    private function buildSemicolonFromInput(mixed $value, bool $lowercase = false): ?string
    {
        if (is_null($value)) {
            return null;
        }

        if (is_iterable($value)) {
            $values = $value;
        } else {
            $values = preg_split('/[;,]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
        }

        return $this->formatSemicolonValues($values, $lowercase);
    }

    private function normalizeKelasValues(?string $value): string
    {
        $chunks = preg_split('/[;,]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
        if (! $chunks) {
            $chunks = ['A'];
        }

        return $this->formatSemicolonValues($chunks) ?? 'A;';
    }
}
