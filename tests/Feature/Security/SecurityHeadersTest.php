<?php

test('web responses include baseline security headers', function () {
    $response = $this->get('/');

    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Content-Security-Policy');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'")
        ->not->toContain('cdn.jsdelivr.net');
});
