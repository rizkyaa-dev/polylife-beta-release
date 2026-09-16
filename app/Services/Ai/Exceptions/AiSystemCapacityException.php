<?php

namespace App\Services\Ai\Exceptions;

use RuntimeException;

final class AiSystemCapacityException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Antrean AI sedang penuh. Coba kembali beberapa saat lagi.');
    }
}
