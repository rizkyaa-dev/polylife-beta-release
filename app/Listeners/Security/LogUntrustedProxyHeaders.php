<?php

namespace App\Listeners\Security;

use App\Events\Security\UntrustedProxyHeadersDetected;
use Illuminate\Support\Facades\Log;

class LogUntrustedProxyHeaders
{
    public function handle(UntrustedProxyHeadersDetected $event): void
    {
        $message = 'Blocked proxy-derived headers from an untrusted source.';
        $context = [
            'remote_address' => $event->remoteAddress,
            'method' => $event->method,
            'path' => $event->path,
            'proxy_headers' => $event->headers,
            'user_agent' => $event->userAgent,
        ];

        $channel = config('security.proxy.log_channel');

        if (is_string($channel) && $channel !== '') {
            try {
                Log::channel($channel)->warning($message, $context);

                return;
            } catch (\Throwable) {
                // Fall back to the default logger instead of failing the request.
            }
        }

        Log::warning($message, $context);
    }
}
