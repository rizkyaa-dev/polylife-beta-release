<?php

namespace App\Services\Ai;

use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Throwable;

final class UserTimeContext
{
    public function timezone(User $user): DateTimeZone
    {
        $candidate = trim((string) ($user->profile?->timezone ?: config('app.dashboard_timezone')));

        try {
            return new DateTimeZone($candidate !== '' ? $candidate : 'Asia/Jakarta');
        } catch (Throwable) {
            return new DateTimeZone('Asia/Jakarta');
        }
    }

    public function now(User $user): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone($user));
    }

    public function parse(User $user, mixed $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, $this->timezone($user));
    }
}
