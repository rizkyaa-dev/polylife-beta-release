@php
    $brandName = trim((string) ($appName ?? config('app.name', 'PolyLife')));
    if ($brandName === '' || strtolower($brandName) === 'laravel') {
        $brandName = 'PolyLife';
    } elseif (strtolower($brandName) === 'polylife') {
        $brandName = 'PolyLife';
    }

    $helpEmail = trim((string) ($supportEmail ?? config('mail.from.address', 'no-reply@polylife.site')));
    if ($helpEmail === '') {
        $helpEmail = 'no-reply@polylife.site';
    }

    $expiryText = (int) ($expiresInMinutes ?? 60);
@endphp
Verifikasi Email {{ $brandName }}

Selamat datang di {{ $brandName }}.

Klik tautan berikut untuk memverifikasi email dan mulai menggunakan akun kamu:

{{ $verificationUrl }}

Link ini berlaku selama {{ $expiryText }} menit.

Jika kamu tidak merasa membuat akun {{ $brandName }}, abaikan email ini. Akun tidak akan aktif sebelum email diverifikasi.

Butuh bantuan? Hubungi {{ $helpEmail }}

(c) {{ now()->year }} {{ $brandName }}. All rights reserved.
