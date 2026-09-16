<?php

namespace App\Services\Ai\Exceptions;

use RuntimeException;

final class AiIdempotencyConflictException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Request ID sudah digunakan untuk pesan yang berbeda. Kirim ulang dengan request ID baru.');
    }
}
