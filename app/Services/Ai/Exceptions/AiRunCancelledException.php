<?php

namespace App\Services\Ai\Exceptions;

use RuntimeException;

final class AiRunCancelledException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Run AI dibatalkan oleh pengguna.');
    }
}
