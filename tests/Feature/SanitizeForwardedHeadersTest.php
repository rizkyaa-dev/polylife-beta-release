<?php

use App\Events\Security\UntrustedProxyHeadersDetected;
use App\Support\Security\ProxyTrustSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    TrustProxies::flushState();

    config([
        'security.proxy.trusted_proxies' => [],
        'security.proxy.strip_untrusted_headers' => true,
    ]);

    Route::get('/__proxy-guard-check', function (Request $request) {
        return response()->json([
            'client_ip' => $request->getClientIp(),
            'scheme' => $request->getScheme(),
            'header_forwarded_for' => $request->headers->get('x-forwarded-for'),
            'server_forwarded_for' => $request->server->get('HTTP_X_FORWARDED_FOR'),
        ]);
    });
});

afterEach(function (): void {
    TrustProxies::flushState();
});

it('strips spoofed proxy headers from untrusted sources and emits a security event', function () {
    Event::fake([UntrustedProxyHeadersDetected::class]);

    $response = $this
        ->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.24',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])
        ->get('/__proxy-guard-check');

    $response
        ->assertOk()
        ->assertJson([
            'client_ip' => '203.0.113.10',
            'scheme' => 'http',
            'header_forwarded_for' => null,
            'server_forwarded_for' => null,
        ]);

    Event::assertDispatched(
        UntrustedProxyHeadersDetected::class,
        fn (UntrustedProxyHeadersDetected $event): bool => $event->remoteAddress === '203.0.113.10'
            && $event->path === '/__proxy-guard-check'
            && ($event->headers['x-forwarded-for'] ?? null) === '198.51.100.24'
    );
});

it('preserves proxy headers for explicitly trusted proxies', function () {
    Event::fake([UntrustedProxyHeadersDetected::class]);

    config([
        'security.proxy.trusted_proxies' => ['10.10.10.10'],
    ]);

    TrustProxies::at(['10.10.10.10']);
    TrustProxies::withHeaders(ProxyTrustSettings::trustedHeaders());

    $response = $this
        ->withServerVariables([
            'REMOTE_ADDR' => '10.10.10.10',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.24',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])
        ->get('/__proxy-guard-check');

    $response
        ->assertOk()
        ->assertJson([
            'client_ip' => '198.51.100.24',
            'scheme' => 'https',
            'header_forwarded_for' => '198.51.100.24',
            'server_forwarded_for' => '198.51.100.24',
        ]);

    Event::assertNotDispatched(UntrustedProxyHeadersDetected::class);
});
