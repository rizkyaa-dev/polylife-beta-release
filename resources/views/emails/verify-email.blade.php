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
    <title>Verifikasi Email {{ $brandName }}</title>
    <style>
        @media only screen and (max-width: 680px) {
            .email-shell { padding: 16px 8px !important; }
            .email-card { width: 100% !important; }
            .email-section { padding-left: 24px !important; padding-right: 24px !important; }
            .email-title { font-size: 26px !important; line-height: 1.25 !important; }
            .email-button { display: block !important; width: auto !important; min-width: 0 !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f6f1ff;color:#19173c;font-family:Arial,'Helvetica Neue',Helvetica,sans-serif;-webkit-text-size-adjust:100%;text-size-adjust:100%;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        Tinggal satu langkah lagi untuk mengaktifkan email akun {{ $brandName }} kamu.
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background-color:#f6f1ff;">
        <tr>
            <td class="email-shell" align="center" style="padding:24px 12px;">
                <table role="presentation" class="email-card" width="640" cellspacing="0" cellpadding="0" border="0" style="width:640px;max-width:640px;border-collapse:separate;background-color:#ffffff;border:2px solid #d6c8ff;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td class="email-section" style="padding:30px 30px 28px;background-color:#fbf3ff;background-image:linear-gradient(135deg,#f8f4ff 0%,#fff8fb 50%,#ffe8f3 100%);border-bottom:1px solid #e6ddff;">
                            <p style="margin:0 0 16px;color:#5f5794;font-size:13px;line-height:1.35;font-weight:800;letter-spacing:2.8px;text-transform:uppercase;">
                                Verifikasi Akun
                            </p>
                            <h1 class="email-title" style="margin:0 0 10px;color:#19173c;font-size:28px;line-height:1.22;font-weight:800;letter-spacing:-0.2px;">
                                Selamat datang di {{ $brandName }}
                            </h1>
                            <p style="margin:0;color:#25224c;font-size:16px;line-height:1.6;font-weight:400;">
                                Tinggal satu langkah lagi. Klik tombol di bawah untuk mengaktifkan email akun kamu.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td class="email-section" style="padding:30px 30px 24px;background-color:#ffffff;">
                            <p style="margin:0 0 24px;color:#25224c;font-size:16px;line-height:1.7;font-weight:400;">
                                Setelah terverifikasi, kamu bisa langsung memakai semua fitur utama seperti jadwal, reminder, catatan, dan keuangan.
                            </p>

                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 18px;">
                                <tr>
                                    <td>
                                        <a href="{{ $verificationUrl }}" class="email-button" style="display:inline-block;min-width:180px;padding:14px 22px;border-radius:11px;background-color:#7b72f4;border:2px solid #25205f;box-shadow:0 4px 0 #25205f;color:#ffffff;font-size:15px;line-height:1.2;font-weight:800;text-align:center;text-decoration:none;">
                                            Verifikasi Email Sekarang
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 18px;color:#4f4a91;font-size:13px;line-height:1.55;font-weight:500;">
                                Link ini berlaku selama {{ $expiryText }} menit.
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border:1px solid #ded4ff;border-radius:12px;background-color:#faf8ff;">
                                <tr>
                                    <td style="padding:15px 15px 14px;">
                                        <p style="margin:0 0 10px;color:#5f5794;font-size:13px;line-height:1.4;font-weight:800;letter-spacing:1.8px;text-transform:uppercase;">
                                            Tombol tidak bisa diklik?
                                        </p>
                                        <p style="margin:0 0 4px;color:#25224c;font-size:14px;line-height:1.6;font-weight:400;">
                                            Salin dan buka tautan ini di browser:
                                        </p>
                                        <p style="margin:0;color:#3f3bd6;font-size:14px;line-height:1.55;word-break:break-all;overflow-wrap:anywhere;">
                                            <a href="{{ $verificationUrl }}" style="color:#3f3bd6;text-decoration:underline;">{{ $verificationUrl }}</a>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:22px 0 0;color:#4f4a91;font-size:13px;line-height:1.65;font-weight:400;">
                                Jika kamu tidak merasa mendaftar, abaikan email ini.
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
