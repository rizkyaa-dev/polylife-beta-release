@php
    $brandName = trim((string) ($appName ?? config('app.name', 'PolyLife')));
    if ($brandName === '' || strtolower($brandName) === 'laravel') {
        $brandName = 'PolyLife';
    }

    $helpEmail = trim((string) ($supportEmail ?? config('mail.from.address', 'support@polylife.app')));
    $expiryText = (int) ($expiresInMinutes ?? 60);
@endphp
Reset Password {{ $brandName }}

Halo,

Kami menerima permintaan untuk mengatur ulang password akun kamu.
Klik tautan berikut untuk melanjutkan:

{{ $resetUrl }}

Link berlaku selama {{ $expiryText }} menit.

Jika kamu tidak meminta reset password, abaikan email ini dan password akun kamu tidak akan berubah.

Butuh bantuan: {{ $helpEmail }}

{{ $brandName }}
