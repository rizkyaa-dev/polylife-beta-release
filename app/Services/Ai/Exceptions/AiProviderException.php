<?php

namespace App\Services\Ai\Exceptions;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;
use Throwable;

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

    public static function transportFailure(Throwable $exception): self
    {
        for ($cause = $exception; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof ConnectException || $cause instanceof RequestException) {
                if (($cause->getHandlerContext()['errno'] ?? null) === 28) {
                    return self::timeout();
                }
            }
        }

        return new self('provider_connection_failed', true, 'Koneksi ke penyedia AI terputus atau tidak dapat dibuka.');
    }

    public static function truncated(): self
    {
        return new self('provider_response_truncated', true, 'Respons penyedia AI terpotong sebelum selesai.');
    }

    public static function invalidCodingResponse(): self
    {
        return new self(
            'coding_agent_invalid_response',
            true,
            'Agen coding tidak menghasilkan artifact yang dapat digunakan.'
        );
    }

    public static function circuitOpen(): self
    {
        return new self('provider_circuit_open', true, 'Penyedia AI sedang dipulihkan setelah beberapa kegagalan.');
    }

    public static function overloaded(): self
    {
        return new self('provider_overloaded', true, 'Kapasitas AI sedang penuh. Silakan coba kembali sesaat lagi.');
    }

    public static function toolCallLimitExceeded(): self
    {
        return new self(
            'provider_tool_call_limit_exceeded',
            false,
            'Penyedia AI menghasilkan terlalu banyak pemanggilan alat dalam satu proses.'
        );
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
