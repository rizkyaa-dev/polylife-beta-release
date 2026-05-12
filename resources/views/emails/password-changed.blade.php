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
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Password {{ $brandName }} Berhasil Diubah</title>
    <style>
        @media only screen and (max-width: 680px) {
            .email-shell { padding: 16px 8px !important; }
            .email-card { width: 100% !important; }
            .email-section { padding-left: 24px !important; padding-right: 24px !important; }
            .email-title { font-size: 26px !important; line-height: 1.25 !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f6f1ff;color:#19173c;font-family:Arial,'Helvetica Neue',Helvetica,sans-serif;-webkit-text-size-adjust:100%;text-size-adjust:100%;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        Password akun {{ $brandName }} kamu baru saja diubah.
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background-color:#f6f1ff;">
        <tr>
            <td class="email-shell" align="center" style="padding:24px 12px;">
                <table role="presentation" class="email-card" width="640" cellspacing="0" cellpadding="0" border="0" style="width:640px;max-width:640px;border-collapse:separate;background-color:#ffffff;border:2px solid #d6c8ff;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td class="email-section" style="padding:30px 30px 28px;background-color:#fbf3ff;background-image:linear-gradient(135deg,#f8f4ff 0%,#fff8fb 50%,#ffe8f3 100%);border-bottom:1px solid #e6ddff;">
                            <p style="margin:0 0 16px;color:#5f5794;font-size:13px;line-height:1.35;font-weight:800;letter-spacing:2.8px;text-transform:uppercase;">
                                Keamanan Akun
                            </p>
                            <h1 class="email-title" style="margin:0 0 10px;color:#19173c;font-size:28px;line-height:1.22;font-weight:800;">
                                Password berhasil diubah
                            </h1>
                            <p style="margin:0;color:#25224c;font-size:16px;line-height:1.6;">
                                Ini adalah pemberitahuan bahwa password akun {{ $brandName }} kamu baru saja diperbarui.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td class="email-section" style="padding:30px;background-color:#ffffff;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border:1px solid #ded4ff;border-radius:12px;background-color:#faf8ff;">
                                <tr>
                                    <td style="padding:16px;">
                                        <p style="margin:0 0 10px;color:#5f5794;font-size:13px;line-height:1.4;font-weight:800;letter-spacing:1.8px;text-transform:uppercase;">
                                            Detail aktivitas
                                        </p>
                                        <p style="margin:0 0 6px;color:#25224c;font-size:14px;line-height:1.6;">
                                            <strong>Waktu:</strong> {{ optional($changedAt ?? now())->format('d M Y H:i') }}
                                        </p>
                                        <p style="margin:0 0 6px;color:#25224c;font-size:14px;line-height:1.6;">
                                            <strong>IP:</strong> {{ $ipAddress ?: '-' }}
                                        </p>
                                        <p style="margin:0;color:#25224c;font-size:14px;line-height:1.6;word-break:break-word;">
                                            <strong>Perangkat:</strong> {{ $userAgent ?: '-' }}
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:22px 0 0;color:#25224c;font-size:15px;line-height:1.7;">
                                Jika perubahan ini kamu lakukan sendiri, kamu tidak perlu melakukan apa pun.
                            </p>
                            <p style="margin:12px 0 0;color:#4f4a91;font-size:13px;line-height:1.65;">
                                Jika kamu tidak merasa mengubah password, segera reset password akun kamu dan hubungi <a href="mailto:{{ $helpEmail }}" style="color:#3f3bd6;text-decoration:underline;">{{ $helpEmail }}</a>.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td class="email-section" style="padding:20px 30px 22px;background-color:#f7f3ff;border-top:1px solid #e6ddff;">
                            <p style="margin:0 0 8px;color:#5f5794;font-size:12px;line-height:1.55;font-weight:500;">
                                Butuh bantuan? Hubungi <a href="mailto:{{ $helpEmail }}" style="color:#3f3bd6;text-decoration:underline;">{{ $helpEmail }}</a>
                            </p>
                            <p style="margin:0;color:#5f5794;font-size:12px;line-height:1.55;font-weight:500;">
                                &copy; {{ now()->year }} {{ $brandName }}. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
