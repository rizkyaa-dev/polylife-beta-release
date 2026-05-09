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

    public static function trustedHeaders(): int
    {
        return Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_PREFIX
            | Request::HEADER_X_FORWARDED_AWS_ELB;
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
            if (! self::isAllowedProxyValue($trustedProxy)) {
                continue;
            }

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
            static fn (string $proxy): bool => self::isAllowedProxyValue($proxy)
        ));
    }

    private static function isAllowedProxyValue(string $proxy): bool
    {
        $normalizedProxy = trim($proxy);
        if ($normalizedProxy === '' || in_array($normalizedProxy, ['*', '**', 'REMOTE_ADDR'], true)) {
            return false;
        }

        [$ipAddress, $prefix] = array_pad(explode('/', $normalizedProxy, 2), 2, null);
        if (! filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            return false;
        }

        if ($prefix === null) {
            return true;
        }

        if ($prefix === '' || ! ctype_digit($prefix)) {
            return false;
        }

        $prefixLength = (int) $prefix;
        $maxPrefixLength = filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 128 : 32;

        return $prefixLength >= 0 && $prefixLength <= $maxPrefixLength;
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
