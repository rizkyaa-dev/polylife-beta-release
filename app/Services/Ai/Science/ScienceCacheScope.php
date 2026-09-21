<?php

namespace App\Services\Ai\Science;

use RuntimeException;

/** Opaque stable namespace; never use a raw user identifier as a browser cache key. */
final class ScienceCacheScope
{
    public function forUser(int $userId): string
    {
        $key = (string) config('app.key');
        if ($userId < 1 || $key === '') {
            throw new RuntimeException('Science cache ownership cannot be established.');
        }

        return hash_hmac('sha256', 'science-browser-cache:user:'.$userId, $key);
    }
}
