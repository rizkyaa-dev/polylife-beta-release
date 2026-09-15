<?php

namespace App\Services\Ai\Exceptions;

use RuntimeException;

class AiConversationBusyException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Percakapan ini masih memproses pesan lain. Tunggu hingga selesai lalu coba kembali.');
    }
}
