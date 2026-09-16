<?php

namespace App\Services\Ai\Exceptions;

use RuntimeException;

final class AiUserCapacityException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Terlalu banyak proses AI aktif. Tunggu salah satunya selesai lalu coba lagi.');
    }
}
