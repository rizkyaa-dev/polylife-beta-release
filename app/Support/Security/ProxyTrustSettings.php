<?php

namespace App\Support\Security;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

final class ProxyTrustSettings
{
    /**
     * Headers commonly used to override client/network metadata.
     *
     * @var array<int, string>
     */
    private const PROXY_HEADERS = [
        'forwarded',
        'x-forwarded-for',
        'x-forwarded-host',
        'x-forwarded-port',
        'x-forwarded-proto',
        'x-forwarded-prefix',
        'x-forwarded-server',
        'x-real-ip',
        'front-end-https',
    ];

    /**
     * Resolve immutable proxy security config from environment.
     *
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
            'trusted_proxies' => self::trustedProxies(),
            'strip_untrusted_headers' => self::booleanEnv('SECURITY_STRIP_UNTRUSTED_PROXY_HEADERS', true),
            'log_channel' => self::nullableStringEnv('SECURITY_PROXY_LOG_CHANNEL'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function trustedProxies(): array
    {
        return self::parseTrustedProxies(self::nullableStringEnv('TRUSTED_PROXIES'));
    }

    /**
     * @param  array<int, string>|null  $trustedProxies
     */
    public static function isTrustedProxy(?string $ipAddress, ?array $trustedProxies = null): bool
    {
        $candidate = trim((string) $ipAddress);
        if ($candidate === '') {
            return false;
        }

        $trustedProxies ??= self::trustedProxies();

        foreach ($trustedProxies as $trustedProxy) {
            if (IpUtils::checkIp($candidate, $trustedProxy)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    public static function proxyHeaderNames(): array
    {
        return self::PROXY_HEADERS;
    }

    /**
     * @return array<int, string>
     */
    private static function parseTrustedProxies(?string $rawValue): array
    {
        $raw = trim((string) $rawValue);
        if ($raw === '') {
            return [];
        }

        $items = preg_split('/[\s,]+/', $raw) ?: [];

        return array_values(array_filter(
            array_unique(array_map('trim', $items)),
            static fn (string $proxy): bool => $proxy !== '' && ! in_array($proxy, ['*', '**', 'REMOTE_ADDR'], true)
        ));
    }

    private static function nullableStringEnv(string $key): ?string
    {
        $value = env($key);
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function booleanEnv(string $key, bool $default): bool
    {
        $value = env($key);
        if ($value === null) {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $parsed ?? $default;
    }
}
