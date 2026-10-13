<?php

namespace App\Services\Ai\Exceptions;

use RuntimeException;

final class AiEcoUnavailableException extends RuntimeException
{
    public const MESSAGE = 'Mode Eco belum tersedia. Pilih Default atau coba lagi setelah layanan siap.';

    public function __construct(public readonly string $reason)
    {
        parent::__construct(self::MESSAGE);
    }
}
