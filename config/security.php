<?php

return [
    'registration' => [
        'blocked_agents' => explode(',', env('REGISTER_BLOCKED_AGENTS', 'curl,python,wget,guzzle')),
        'honeypot_field' => env('REGISTER_HONEYPOT_FIELD', 'middle_name'),
        'min_delay' => (int) env('REGISTER_MIN_DELAY', 3),
        'max_delay' => (int) env('REGISTER_MAX_DELAY', 15 * 60),
        'log_channel' => env('REGISTER_SECURITY_LOG_CHANNEL', null),
    ],

    'rate_limit' => [
        'attempt_limit' => (int) env('REGISTER_ATTEMPT_LIMIT', 1),
        'attempt_decay' => (int) env('REGISTER_ATTEMPT_DECAY', 60),
        'view_limit' => (int) env('REGISTER_VIEW_LIMIT', 12),
        'view_decay' => (int) env('REGISTER_VIEW_DECAY', 60),
        'log_channel' => env('REGISTER_RATE_LIMIT_LOG_CHANNEL', null),
    ],

    'headers' => [
        'hsts' => [
            'enabled' => env('SECURITY_HSTS', true),
            'max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
            'include_subdomains' => env('SECURITY_HSTS_INCLUDE_SUBDOMAINS', true),
            'preload' => env('SECURITY_HSTS_PRELOAD', false),
        ],
        'csp' => [
            'enabled' => env('SECURITY_CSP', true),
            'policy' => env('SECURITY_CSP_POLICY', "default-src 'self'; img-src 'self' data: https:; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; font-src 'self' data:; connect-src 'self' https:; frame-ancestors 'self'; form-action 'self'; base-uri 'self'"),
            'dev_allow_vite' => env('SECURITY_CSP_DEV_ALLOW_VITE', true),
            'dev_hosts' => explode(',', env('SECURITY_CSP_DEV_HOSTS', 'http://localhost:5173')),
            'extra_script_sources' => explode(',', env('SECURITY_CSP_EXTRA_SCRIPT_SOURCES', 'https://cdn.jsdelivr.net')),
        ],
        'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),
        'frame_options' => env('SECURITY_FRAME_OPTIONS', 'SAMEORIGIN'),
        'x_content_type_options' => env('SECURITY_X_CONTENT_TYPE_OPTIONS', 'nosniff'),
        'permissions_policy' => env('SECURITY_PERMISSIONS_POLICY', "accelerometer=(), autoplay=(), camera=(), clipboard-read=(), clipboard-write=(), display-capture=(), document-domain=(), encrypted-media=(), fullscreen=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), midi=(), payment=(), picture-in-picture=(), publickey-credentials-get=(), sync-xhr=(), usb=(), vr=()"),
    ],
];
