<?php

namespace App\Services\Ai;

use App\Services\Ai\DTOs\AiCodingBrief;
use App\Services\Ai\DTOs\AiCodingRoute;
use App\Services\Ai\DTOs\AiDesignIntent;

final class AiCodingBriefBuilder
{
    private const MAX_FILES = 8;

    private const MAX_ITEMS = 16;

    public function fromResponse(?string $content, string $prompt, AiCodingRoute $route): AiCodingBrief
    {
        $decoded = $this->decodeObject($content);

        return new AiCodingBrief(
            language: $route->language,
            runtime: $this->runtime($decoded['runtime'] ?? null, $route->language),
            files: $this->files($decoded['files'] ?? null, $route->language),
            requirements: $this->items($decoded['requirements'] ?? null, [$this->bounded($prompt, 1200)]),
            acceptanceCriteria: $this->items(
                $decoded['acceptance_criteria'] ?? null,
                ['Hasil lengkap, valid, dan dapat digunakan sesuai runtime target.']
            ),
            visualDirection: $this->visualDirection($decoded['visual_direction'] ?? null),
            runnable: $this->runnable($decoded['runnable'] ?? null, $route->language),
            designIntent: is_array($decoded['design_intent'] ?? null) ? AiDesignIntent::fromArray($decoded['design_intent']) : null
        );
    }

    /** @return array<string, mixed> */
    private function decodeObject(?string $content): array
    {
        if (! is_string($content) || trim($content) === '') {
            return [];
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start === false || $end === false || $end < $start) {
            return [];
        }

        $decoded = json_decode(substr($content, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function runtime(mixed $runtime, string $language): string
    {
        $allowed = ['browser', 'server', 'cli', 'mobile', 'library'];
        $runtime = is_string($runtime) ? strtolower(trim($runtime)) : '';
        if (in_array($runtime, $allowed, true)) {
            return $runtime;
        }

        return match ($language) {
            'html', 'css', 'javascript', 'typescript' => 'browser',
            'dart' => 'mobile',
            'bash' => 'cli',
            default => 'server',
        };
    }

    /** @return list<string> */
    private function files(mixed $files, string $language): array
    {
        $fallback = ['code.'.$this->extension($language)];
        if (! is_array($files)) {
            return $fallback;
        }

        $normalized = [];
        foreach (array_slice($files, 0, self::MAX_FILES) as $file) {
            if (! is_string($file)) {
                continue;
            }

            $file = str_replace('\\', '/', trim($file));
            $file = preg_replace('~/+~', '/', $file) ?? '';
            if ($file === '' || str_starts_with($file, '/') || str_contains($file, '..')) {
                continue;
            }

            $safe = preg_replace('/[^a-zA-Z0-9._\/-]/', '-', $file) ?? '';
            if ($safe !== '') {
                $normalized[] = $this->bounded($safe, 160);
            }
        }

        return array_values(array_unique($normalized ?: $fallback));
    }

    /**
     * @param  list<string>  $fallback
     * @return list<string>
     */
    private function items(mixed $items, array $fallback): array
    {
        if (! is_array($items)) {
            return $fallback;
        }

        $normalized = [];
        foreach (array_slice($items, 0, self::MAX_ITEMS) as $item) {
            if (is_string($item) && trim($item) !== '') {
                $normalized[] = $this->bounded(trim($item), 500);
            }
        }

        return array_values(array_unique($normalized ?: $fallback));
    }

    /** @return array<string, string> */
    private function visualDirection(mixed $direction): array
    {
        if (! is_array($direction)) {
            return [];
        }

        $normalized = [];
        foreach (array_slice($direction, 0, 8, true) as $key => $value) {
            if (is_string($key) && is_string($value) && trim($value) !== '') {
                $safeKey = preg_replace('/[^a-z0-9_\-]/', '_', strtolower($key)) ?? '';
                if ($safeKey !== '') {
                    $normalized[$safeKey] = $this->bounded(trim($value), 300);
                }
            }
        }

        return $normalized;
    }

    private function extension(string $language): string
    {
        return match ($language) {
            'javascript' => 'js', 'typescript' => 'ts', 'python' => 'py',
            'csharp' => 'cs', 'cpp' => 'cpp', 'bash' => 'sh', 'rust' => 'rs',
            default => $language === 'text' ? 'txt' : $language,
        };
    }

    private function runnable(mixed $value, string $language): bool
    {
        return is_bool($value) ? $value : $language === 'html';
    }

    private function bounded(string $value, int $length): string
    {
        return mb_substr($value, 0, $length);
    }
}
