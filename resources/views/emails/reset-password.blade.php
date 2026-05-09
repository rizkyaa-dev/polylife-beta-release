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
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Reset Password {{ $brandName }}</title>
    <style>
        @media only screen and (max-width: 640px) {
            .email-shell { padding: 20px 12px !important; }
            .email-card { width: 100% !important; }
            .email-body { padding: 28px 20px !important; }
            .email-title { font-size: 25px !important; line-height: 1.28 !important; }
            .email-button { display: block !important; width: auto !important; }
            .email-link-box { font-size: 12px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f4f6fb;color:#172033;font-family:Arial,'Helvetica Neue',Helvetica,sans-serif;-webkit-text-size-adjust:100%;text-size-adjust:100%;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        Gunakan link ini untuk mengatur ulang password akun {{ $brandName }}.
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background-color:#f4f6fb;">
        <tr>
            <td class="email-shell" align="center" style="padding:32px 16px;">
                <table role="presentation" class="email-card" width="600" cellspacing="0" cellpadding="0" border="0" style="width:600px;max-width:600px;background-color:#ffffff;border:1px solid #e4e7f0;border-radius:18px;border-collapse:separate;overflow:hidden;">
                    <tr>
                        <td style="padding:22px 28px;background-color:#ffffff;border-bottom:1px solid #eef1f7;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="left" style="font-size:18px;line-height:1.2;font-weight:800;color:#2f36d6;">
                                        {{ $brandName }}
                                    </td>
                                    <td align="right" style="font-size:12px;line-height:1.4;font-weight:700;color:#667085;text-transform:uppercase;letter-spacing:0.08em;">
                                        Keamanan akun
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td class="email-body" style="padding:36px 40px 34px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="center" style="padding:0 0 22px;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 auto;">
                                            <tr>
                                                <td align="center" style="width:64px;height:64px;border-radius:50%;background-color:#fff3e8;color:#c05621;font-size:30px;line-height:64px;font-weight:800;">
                                                    !
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td align="center">
                                        <h1 class="email-title" style="margin:0;color:#172033;font-size:30px;line-height:1.22;font-weight:800;letter-spacing:-0.2px;">
                                            Reset password {{ $brandName }}
                                        </h1>
                                        <p style="margin:14px 0 0;color:#526071;font-size:16px;line-height:1.65;font-weight:400;">
                                            Kami menerima permintaan untuk mengatur ulang password akun kamu. Klik tombol di bawah untuk melanjutkan.
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <td align="center" style="padding:30px 0 16px;">
                                        <a href="{{ $resetUrl }}" class="email-button" style="display:inline-block;background-color:#5148e5;border-radius:12px;color:#ffffff;font-size:16px;line-height:1.2;font-weight:800;text-decoration:none;padding:16px 30px;min-width:240px;text-align:center;">
                                            Atur Ulang Password
                                        </a>
                                    </td>
                                </tr>

                                <tr>
                                    <td align="center" style="padding:0 0 26px;">
                                        <p style="margin:0;color:#667085;font-size:13px;line-height:1.5;">
                                            Link ini berlaku selama {{ $expiryText }} menit.
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:20px;border:1px solid #e4e7f0;border-radius:14px;background-color:#f8f9fc;">
                                        <p style="margin:0 0 8px;color:#172033;font-size:14px;line-height:1.5;font-weight:800;">
                                            Tombol tidak bisa diklik?
                                        </p>
                                        <p style="margin:0 0 12px;color:#526071;font-size:13px;line-height:1.55;">
                                            Salin dan buka tautan ini di browser:
                                        </p>
                                        <p class="email-link-box" style="margin:0;color:#5148e5;font-size:13px;line-height:1.55;word-break:break-all;overflow-wrap:anywhere;">
                                            <a href="{{ $resetUrl }}" style="color:#5148e5;text-decoration:underline;">{{ $resetUrl }}</a>
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:22px 0 0;">
                                        <p style="margin:0;color:#667085;font-size:13px;line-height:1.65;">
                                            Jika kamu tidak meminta reset password, abaikan email ini. Password akun kamu tidak akan berubah.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 28px;background-color:#f8f9fc;border-top:1px solid #eef1f7;">
                            <p style="margin:0 0 6px;color:#667085;font-size:12px;line-height:1.55;">
                                Butuh bantuan? Hubungi <a href="mailto:{{ $helpEmail }}" style="color:#5148e5;text-decoration:underline;">{{ $helpEmail }}</a>
                            </p>
                            <p style="margin:0;color:#98a2b3;font-size:12px;line-height:1.55;">
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
