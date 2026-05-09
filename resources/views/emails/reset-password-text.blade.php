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
Reset Password {{ $brandName }}

Kami menerima permintaan untuk mengatur ulang password akun kamu.

Klik tautan berikut untuk melanjutkan:

{{ $resetUrl }}

Link ini berlaku selama {{ $expiryText }} menit.

Jika kamu tidak meminta reset password, abaikan email ini. Password akun kamu tidak akan berubah.

Butuh bantuan? Hubungi {{ $helpEmail }}

(c) {{ now()->year }} {{ $brandName }}. All rights reserved.
