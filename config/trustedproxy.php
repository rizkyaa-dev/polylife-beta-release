<?php

use App\Support\Security\ProxyTrustSettings;

return [
    /*
    |--------------------------------------------------------------------------
    | Trusted Reverse Proxies
    |--------------------------------------------------------------------------
    |
    | Only explicitly listed proxy IPs / CIDR ranges are trusted. Wildcards
    | like "*" are ignored by ProxyTrustSettings to keep this fail-closed.
    |
    */
    'proxies' => ProxyTrustSettings::trustedProxies(),
];
