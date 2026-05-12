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
@endphp
Password {{ $brandName }} Berhasil Diubah

Password akun kamu baru saja diubah.

Waktu: {{ optional($changedAt ?? now())->format('d M Y H:i') }}
IP: {{ $ipAddress ?: '-' }}
Perangkat: {{ $userAgent ?: '-' }}

Jika perubahan ini kamu lakukan sendiri, kamu tidak perlu melakukan apa pun.

Jika kamu tidak merasa mengubah password, segera reset password akun kamu dan hubungi {{ $helpEmail }}.

(c) {{ now()->year }} {{ $brandName }}. All rights reserved.
