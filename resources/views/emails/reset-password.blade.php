@php
    $brandName = trim((string) ($appName ?? config('app.name', 'PolyLife')));
    if ($brandName === '' || strtolower($brandName) === 'laravel') {
        $brandName = 'PolyLife';
    }

    $helpEmail = trim((string) ($supportEmail ?? config('mail.from.address', 'support@polylife.app')));
    $expiryText = (int) ($expiresInMinutes ?? 60);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password {{ $brandName }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f7f4ff;color:#2d2d3c;font-family:Arial,'Helvetica Neue',Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f7f4ff;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px;background-color:#ffffff;border:2px solid #d7ceff;border-radius:18px;overflow:hidden;">
                    <tr>
                        <td style="padding:28px 30px;background:linear-gradient(135deg,#fdfbff 0%,#f6f3ff 52%,#ffe7f2 100%);border-bottom:1px solid #ebe4ff;">
                            <p style="margin:0;font-size:12px;letter-spacing:1.5px;text-transform:uppercase;color:#6d6797;font-weight:700;">
                                Keamanan Akun
                            </p>
                            <h1 style="margin:12px 0 8px;font-size:26px;line-height:1.2;color:#2b2250;">
                                Reset password {{ $brandName }}
                            </h1>
                            <p style="margin:0;font-size:15px;line-height:1.6;color:#4c4c63;">
                                Kami menerima permintaan untuk mengatur ulang password akun kamu. Klik tombol di bawah untuk melanjutkan.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px 30px 24px;">
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.7;color:#4c4c63;">
                                Demi keamanan, tautan ini hanya bisa dipakai dalam waktu terbatas dan sebaiknya segera digunakan.
                            </p>

                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:24px 0 16px;">
                                <tr>
                                    <td>
                                        <a href="{{ $resetUrl }}" style="display:inline-block;padding:13px 24px;border-radius:12px;background-color:#8181ff;border:2px solid #2b2250;color:#ffffff;font-size:14px;font-weight:700;letter-spacing:0.4px;text-decoration:none;">
                                            Atur Ulang Password
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 14px;font-size:13px;line-height:1.6;color:#6d6797;">
                                Link ini berlaku selama {{ $expiryText }} menit.
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f8f6ff;border:1px solid #e4ddff;border-radius:12px;">
                                <tr>
                                    <td style="padding:14px 14px 10px;">
                                        <p style="margin:0 0 8px;font-size:12px;color:#6d6797;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;">
                                            Tombol tidak bisa diklik?
                                        </p>
                                        <p style="margin:0;font-size:13px;line-height:1.6;color:#4c4c63;word-break:break-all;">
                                            Salin dan buka tautan ini di browser:<br>
                                            <a href="{{ $resetUrl }}" style="color:#5a57c9;text-decoration:underline;">{{ $resetUrl }}</a>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:18px 0 0;font-size:13px;line-height:1.6;color:#6d6797;">
                                Jika kamu tidak meminta reset password, abaikan email ini dan password akun kamu tidak akan berubah.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 30px 22px;background-color:#f6f3ff;border-top:1px solid #ebe4ff;">
                            <p style="margin:0 0 6px;font-size:12px;line-height:1.6;color:#6d6797;">
                                Butuh bantuan? Hubungi <a href="mailto:{{ $helpEmail }}" style="color:#5a57c9;text-decoration:underline;">{{ $helpEmail }}</a>
                            </p>
                            <p style="margin:0;font-size:12px;color:#8a83b8;">
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
