<?php

namespace App\Services\Catatan;

use App\Models\Catatan;
use App\Models\CatatanSearchToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CatatanSearchIndexer
{
    /**
     * @var array<string, bool>
     */
    private const STOP_WORDS = [
        'ada' => true,
        'akan' => true,
        'atau' => true,
        'dan' => true,
        'dari' => true,
        'dengan' => true,
        'di' => true,
        'ini' => true,
        'itu' => true,
        'ke' => true,
        'kita' => true,
        'pada' => true,
        'saya' => true,
        'sebagai' => true,
        'untuk' => true,
        'yang' => true,
    ];

    /**
     * @return list<string>
     */
    public function tokens(string $text): array
    {
        $normalized = Str::of(strip_tags($text))
            ->lower()
            ->replaceMatches('/[^\pL\pN]+/u', ' ')
            ->squish()
            ->toString();

        if ($normalized === '') {
            return [];
        }

        $tokens = [];
        foreach (explode(' ', $normalized) as $token) {
            $token = trim($token);
            $length = mb_strlen($token);

            if ($length < 3 || $length > 64 || isset(self::STOP_WORDS[$token])) {
                continue;
            }

            $tokens[$token] = true;
            if (count($tokens) >= 200) {
                break;
            }
        }

        return array_keys($tokens);
    }

    /**
     * @return list<string>
     */
    public function hashesForSearch(string $query): array
    {
        return array_map(fn (string $token): string => $this->hash($token), $this->tokens($query));
    }

    public function sync(Catatan $catatan): void
    {
        if (! $catatan->exists || ! $this->searchTableExists()) {
            return;
        }

        $hashes = array_map(
            fn (string $token): string => $this->hash($token),
            $this->tokens((string) $catatan->isi)
        );

        DB::transaction(function () use ($catatan, $hashes): void {
            CatatanSearchToken::query()
                ->where('catatan_id', $catatan->id)
                ->delete();

            if ($hashes === []) {
                return;
            }

            CatatanSearchToken::query()->insert(array_map(
                fn (string $hash): array => [
                    'catatan_id' => $catatan->id,
                    'user_id' => $catatan->user_id,
                    'token_hash' => $hash,
                ],
                $hashes
            ));
        });
    }

    public function hash(string $token): string
    {
        return hash_hmac('sha256', $token, $this->key());
    }

    private function key(): string
    {
        return (string) config('app.key');
    }

    private function searchTableExists(): bool
    {
        static $exists = null;

        if ($exists !== null) {
            return $exists;
        }

        return $exists = Schema::hasTable('catatan_search_tokens');
    }
}
