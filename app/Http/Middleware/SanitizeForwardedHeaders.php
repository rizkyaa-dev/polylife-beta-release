<?php

namespace App\Http\Middleware;

use App\Events\Security\UntrustedProxyHeadersDetected;
use App\Support\Security\ProxyTrustSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeForwardedHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.proxy.strip_untrusted_headers', true)) {
            return $next($request);
        }

        $remoteAddress = trim((string) $request->server->get('REMOTE_ADDR'));
        if (ProxyTrustSettings::isTrustedProxy($remoteAddress, config('security.proxy.trusted_proxies', []))) {
            return $next($request);
        }

        $detectedHeaders = $this->extractProxyHeaders($request);
        if ($detectedHeaders === []) {
            return $next($request);
        }

        foreach (array_keys($detectedHeaders) as $headerName) {
            $request->headers->remove($headerName);
            $request->server->remove($this->serverKeyForHeader($headerName));
        }

        event(new UntrustedProxyHeadersDetected(
            remoteAddress: $remoteAddress !== '' ? $remoteAddress : 'unknown',
            method: $request->getMethod(),
            path: $request->getPathInfo(),
            headers: $detectedHeaders,
            userAgent: $request->userAgent(),
        ));

        return $next($request);
    }

    /**
     * @return array<string, string>
     */
    private function extractProxyHeaders(Request $request): array
    {
        $detected = [];

        foreach (ProxyTrustSettings::proxyHeaderNames() as $headerName) {
            $value = $request->headers->get($headerName);
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $detected[$headerName] = mb_substr(trim($value), 0, 500);
        }

        return $detected;
    }

    private function serverKeyForHeader(string $headerName): string
    {
        return 'HTTP_'.str_replace('-', '_', strtoupper($headerName));
    }
}
