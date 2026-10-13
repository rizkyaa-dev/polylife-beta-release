<?php

namespace App\Services\Ai\Eco;

use Illuminate\Support\Str;

final class EcoContextRelevance
{
    private const STOP_WORDS = ['yang', 'dan', 'atau', 'untuk', 'dengan', 'dari', 'saya', 'aku', 'kamu', 'anda', 'ini', 'itu', 'the', 'and', 'for', 'with', 'this', 'that', 'please', 'tolong', 'bisa', 'buat', 'apa', 'bagaimana', 'gimana', 'nya', 'sebuah'];

    /** @return list<string> */
    public function terms(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}_]+/u', Str::lower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_slice(array_values(array_unique(array_filter($words,
            fn (string $word): bool => (mb_strlen($word) >= 3 || ctype_digit($word)) && ! in_array($word, self::STOP_WORDS, true)
        ))), 0, 64);
    }

    /** @param list<string> $terms */
    public function score(string $text, array $terms): int
    {
        $text = Str::lower($text);

        return count(array_filter($terms, fn (string $term): bool => str_contains($text, $term)));
    }

    public function isFollowUp(string $prompt): bool
    {
        return count($this->terms($prompt)) < 4
            || preg_match('/\b(tadi|sebelumnya|lanjutkan|lanjut|revisi|ubah|pertama|kedua|terakhir|seperti itu|previous|continue|revise)\b/iu', $prompt) === 1;
    }
}
