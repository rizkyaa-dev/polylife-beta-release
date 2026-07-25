<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (config('security.headers.csp.enabled', true)) {
            $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
        }

        if (config('security.headers.hsts.enabled', true) && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', $this->hstsHeader());
        }

        $response->headers->set('Referrer-Policy', (string) config('security.headers.referrer_policy', 'strict-origin-when-cross-origin'));
        $response->headers->set('X-Frame-Options', (string) config('security.headers.frame_options', 'SAMEORIGIN'));
        $response->headers->set('X-Content-Type-Options', (string) config('security.headers.x_content_type_options', 'nosniff'));
        $response->headers->set('Permissions-Policy', (string) config('security.headers.permissions_policy', ''));

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $directives = $this->parsePolicy((string) config('security.headers.csp.policy', "default-src 'self'"));
        $this->appendSources($directives, 'script-src', $this->configuredSources('security.headers.csp.extra_script_sources'));

        if ($this->allowsViteDevServer()) {
            $devHosts = $this->configuredSources('security.headers.csp.dev_hosts');
            $this->appendSources($directives, 'script-src', $devHosts);
            $this->appendSources($directives, 'connect-src', array_merge(
                $devHosts,
                $this->webSocketSources($devHosts)
            ));
        }

        return collect($directives)
            ->map(fn (array $sources, string $name): string => trim($name.' '.implode(' ', array_unique($sources))))
            ->implode('; ');
    }

    /**
     * @return array<string, list<string>>
     */
    private function parsePolicy(string $policy): array
    {
        $directives = [];

        foreach (explode(';', $policy) as $rawDirective) {
            $parts = preg_split('/\s+/', trim($rawDirective)) ?: [];
            $name = array_shift($parts);

            if (! $name) {
                continue;
            }

            $directives[$name] = array_values(array_filter($parts));
        }

        return $directives;
    }

    /**
     * @param  array<string, list<string>>  $directives
     * @param  list<string>  $sources
     */
    private function appendSources(array &$directives, string $directive, array $sources): void
    {
        if ($sources === []) {
            return;
        }

        $directives[$directive] = array_values(array_unique(array_merge(
            $directives[$directive] ?? [],
            $sources
        )));
    }

    /**
     * @return list<string>
     */
    private function configuredSources(string $key): array
    {
        $value = config($key, []);
        $sources = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map(
            fn (mixed $source): string => trim((string) $source),
            $sources
        )));
    }

    private function allowsViteDevServer(): bool
    {
        return (bool) config('security.headers.csp.dev_allow_vite', true)
            && (app()->environment('local') || is_file(public_path('hot')));
    }

    /**
     * @param  list<string>  $sources
     * @return list<string>
     */
    private function webSocketSources(array $sources): array
    {
        return array_values(array_filter(array_map(function (string $source): ?string {
            $url = parse_url($source);
            $host = $url['host'] ?? null;
            $port = isset($url['port']) ? ':'.$url['port'] : '';

            if (! $host) {
                return null;
            }

            $scheme = ($url['scheme'] ?? 'http') === 'https' ? 'wss' : 'ws';

            return $scheme.'://'.$host.$port;
        }, $sources)));
    }

    private function hstsHeader(): string
    {
        $parts = ['max-age='.(int) config('security.headers.hsts.max_age', 31536000)];

        if (config('security.headers.hsts.include_subdomains', true)) {
            $parts[] = 'includeSubDomains';
        }

        if (config('security.headers.hsts.preload', false)) {
            $parts[] = 'preload';
        }

        return implode('; ', $parts);
    }
}
