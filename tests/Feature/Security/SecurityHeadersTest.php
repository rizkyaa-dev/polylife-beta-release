<?php

test('web responses include baseline security headers', function () {
    $response = $this->get('/');

    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Content-Security-Policy');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'")
        ->toContain("'inline-speculation-rules'")
        ->toContain("style-src 'self' 'unsafe-inline' https://fonts.bunny.net")
        ->toContain("font-src 'self' data: https://fonts.bunny.net")
        ->not->toContain('cdn.jsdelivr.net');
});

test('local vite stylesheets are allowed by content security policy', function () {
    $previousEnvironment = app()->environment();
    app()->detectEnvironment(fn (): string => 'local');

    try {
        $response = $this->get('/');
    } finally {
        app()->detectEnvironment(fn (): string => $previousEnvironment);
    }

    expect($response->headers->get('Content-Security-Policy'))
        ->toMatch('/style-src[^;]*http:\/\/localhost:5173/')
        ->toMatch('/script-src[^;]*http:\/\/localhost:5173/');
});
