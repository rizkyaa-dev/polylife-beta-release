@php
    $brandName = trim((string) ($appName ?? config('app.name', 'PolyLife')));
    if ($brandName === '' || strtolower($brandName) === 'laravel') {
        $brandName = 'PolyLife';
    }

    $helpEmail = trim((string) ($supportEmail ?? config('mail.from.address', 'support@polylife.app')));
    $expiryText = (int) ($expiresInMinutes ?? 60);
@endphp
Verifikasi Email {{ $brandName }}

Halo,

Tinggal satu langkah lagi untuk mengaktifkan akun kamu.
Klik tautan berikut untuk verifikasi email:

{{ $verificationUrl }}

Link berlaku selama {{ $expiryText }} menit.

Jika kamu tidak merasa mendaftar, abaikan email ini.

Butuh bantuan: {{ $helpEmail }}

{{ $brandName }}
