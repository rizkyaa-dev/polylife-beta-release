@php
    $brandName = trim((string) ($appName ?? config('app.name', 'PolyLife')));
    if ($brandName === '' || strtolower($brandName) === 'laravel') {
        $brandName = 'PolyLife';
    }

    $helpEmail = trim((string) ($supportEmail ?? config('mail.from.address', 'no-reply@polylife.site')));
    if ($helpEmail === '') {
        $helpEmail = 'no-reply@polylife.site';
    }

    $expiryText = (int) ($expiresInMinutes ?? 60);
@endphp
VERIFIKASI AKUN

Selamat datang di {{ $brandName }}.

Tinggal satu langkah lagi untuk mulai menggunakan {{ $brandName }}.
Verifikasi email kamu melalui tautan berikut:

{{ $verificationUrl }}

Link ini berlaku selama {{ $expiryText }} menit.

Jika kamu tidak merasa mendaftar, abaikan email ini.

Butuh bantuan? Hubungi {{ $helpEmail }}

© {{ now()->year }} {{ $brandName }}. All rights reserved.
