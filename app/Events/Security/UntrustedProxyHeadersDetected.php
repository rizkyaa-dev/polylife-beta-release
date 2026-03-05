<?php

namespace App\Events\Security;

final class UntrustedProxyHeadersDetected
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly string $remoteAddress,
        public readonly string $method,
        public readonly string $path,
        public readonly array $headers,
        public readonly ?string $userAgent,
    ) {
    }
}
