<?php

namespace App\Services\Ai\Exceptions;

use RuntimeException;

final class AiProviderException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly bool $retryable,
        string $message
    ) {
        parent::__construct($message);
    }

    public static function timeout(): self
    {
        return new self('provider_timeout', true, 'Penyedia AI melewati batas waktu respons.');
    }

    public static function truncated(): self
    {
        return new self('provider_response_truncated', true, 'Respons penyedia AI terpotong sebelum selesai.');
    }

    public static function forStatus(int $status): self
    {
        return match ($status) {
            401, 403 => new self('provider_authentication', false, 'Konfigurasi autentikasi penyedia AI ditolak.'),
            400, 404, 422 => new self('provider_request_invalid', false, 'Penyedia AI menolak format permintaan.'),
            429 => new self('provider_rate_limited', true, 'Penyedia AI sedang membatasi permintaan.'),
            500, 502, 503, 504 => new self('provider_unavailable', true, 'Penyedia AI sedang tidak tersedia.'),
            default => new self('provider_error', $status >= 500, 'Penyedia AI gagal memproses permintaan.'),
        };
    }
}
