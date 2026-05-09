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
    $displayUrl = \Illuminate\Support\Str::limit($verificationUrl, 58);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Verifikasi Email {{ $brandName }}</title>
</head>
<body style="margin:0;padding:0;background-color:#fbf9ff;color:#1f2037;font-family:Arial,'Helvetica Neue',Helvetica,sans-serif;-webkit-text-size-adjust:100%;text-size-adjust:100%;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background-color:#fbf9ff;background-image:linear-gradient(#ece8ff 1px,transparent 1px),linear-gradient(90deg,#ece8ff 1px,transparent 1px);background-size:32px 32px;">
        <tr>
            <td align="center" style="padding:46px 14px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:760px;width:100%;border-collapse:separate;">
                    <tr>
                        <td style="padding:0;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#ffffff;background-image:radial-gradient(circle at 50% 18%,#ffffff 0,#ffffff 170px,#fbf9ff 410px);border:2px solid #beb0ff;border-radius:24px;box-shadow:0 22px 60px rgba(83,72,190,0.12);overflow:hidden;">
                                <tr>
                                    <td style="padding:34px 36px 20px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td align="left">
                                                    <span style="display:inline-block;padding:9px 18px;border:1px solid #dcd3ff;border-radius:999px;background:#ffffff;color:#6659ee;font-size:12px;font-weight:800;letter-spacing:5px;text-transform:uppercase;">
                                                        <span style="display:inline-block;width:10px;height:10px;margin-right:10px;border-radius:2px;background:#6b5cff;vertical-align:-1px;"></span>Verifikasi Akun
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td align="center" style="padding:12px 36px 8px;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 auto;">
                                            <tr>
                                                <td align="center" style="padding:0 0 10px;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                                        <tr>
                                                            <td style="width:22px;height:22px;background:#ffbad4;border-radius:5px;"></td>
                                                            <td style="width:26px;"></td>
                                                            <td rowspan="3" align="center" style="width:168px;height:142px;">
                                                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:154px;height:112px;border-radius:14px;background:#d8d0ff;box-shadow:0 16px 35px rgba(97,83,224,0.18);">
                                                                    <tr>
                                                                        <td align="center" style="height:48px;background:#ffffff;border-radius:14px 14px 0 0;">
                                                                            <span style="display:inline-block;width:58px;height:58px;line-height:58px;margin-top:-16px;border-radius:50%;background:#5a50ec;color:#ffffff;font-size:34px;font-weight:800;box-shadow:0 10px 22px rgba(79,70,229,0.26);">✓</span>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td style="height:64px;border-radius:0 0 14px 14px;background:linear-gradient(135deg,#b9aeff 0%,#ffffff 49%,#b7aaff 100%);"></td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                            <td style="width:26px;"></td>
                                                            <td style="width:22px;height:22px;background:#bde0ff;border-radius:5px;"></td>
                                                        </tr>
                                                        <tr>
                                                            <td style="height:70px;"></td>
                                                            <td></td>
                                                            <td></td>
                                                            <td></td>
                                                        </tr>
                                                        <tr>
                                                            <td></td>
                                                            <td></td>
                                                            <td></td>
                                                            <td align="center" style="font-size:18px;line-height:8px;color:#d9d3ff;letter-spacing:10px;">
                                                                •••<br>•••<br>•••
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td align="center" style="padding:10px 36px 0;">
                                        <h1 style="margin:0;color:#1f2037;font-size:34px;line-height:1.2;font-weight:800;letter-spacing:-0.4px;">
                                            Selamat datang di {{ $brandName }} 👋
                                        </h1>
                                        <p style="margin:16px 0 0;color:#6f6a9f;font-size:18px;line-height:1.55;font-weight:500;">
                                            Tinggal satu langkah lagi untuk mulai menggunakan {{ $brandName }}.
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <td align="center" style="padding:34px 36px 18px;">
                                        <a href="{{ $verificationUrl }}" style="display:inline-block;min-width:330px;padding:18px 28px;border-radius:14px;background:#5b50f2;border:2px solid #211a64;box-shadow:0 6px 0 #241a80;color:#ffffff;font-size:18px;line-height:1.2;font-weight:800;text-decoration:none;">
                                            <span style="display:inline-block;margin-right:14px;font-size:24px;line-height:0;vertical-align:-3px;">✉</span>Verifikasi Email Sekarang
                                        </a>
                                    </td>
                                </tr>

                                <tr>
                                    <td align="center" style="padding:6px 36px 26px;">
                                        <p style="margin:0;color:#706ba1;font-size:14px;line-height:1.5;font-weight:600;">
                                            <span style="display:inline-block;margin-right:8px;color:#6659ee;font-size:17px;vertical-align:-2px;">◷</span>
                                            Link ini berlaku selama {{ $expiryText }} menit.
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:0 36px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td style="border-top:1px solid #e8e1ff;height:1px;line-height:1px;font-size:1px;">&nbsp;</td>
                                                <td align="center" style="width:72px;color:#b0a5ef;font-size:13px;font-weight:700;">atau</td>
                                                <td style="border-top:1px solid #e8e1ff;height:1px;line-height:1px;font-size:1px;">&nbsp;</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:24px 36px 0;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border:1px solid #ded7ff;border-radius:16px;background:#ffffff;">
                                            <tr>
                                                <td style="padding:18px 20px;width:76px;">
                                                    <div style="width:56px;height:56px;line-height:56px;text-align:center;border-radius:12px;background:#ece8ff;color:#6659ee;font-size:28px;font-weight:800;">🔗</div>
                                                </td>
                                                <td style="padding:18px 8px 18px 0;">
                                                    <p style="margin:0 0 6px;color:#1f2037;font-size:15px;line-height:1.35;font-weight:800;">Tombol tidak bisa diklik?</p>
                                                    <p style="margin:0;color:#6f6a9f;font-size:14px;line-height:1.45;font-weight:500;">Salin dan buka tautan ini di browser kamu.</p>
                                                </td>
                                                <td style="padding:18px 20px 18px 8px;width:270px;">
                                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #d8d0ff;border-radius:12px;background:#fbfaff;">
                                                        <tr>
                                                            <td style="padding:13px 12px;color:#6659ee;font-size:14px;line-height:1.3;font-weight:700;word-break:break-all;">
                                                                <a href="{{ $verificationUrl }}" style="color:#6659ee;text-decoration:none;">{{ $displayUrl }}</a>
                                                            </td>
                                                            <td align="center" style="width:74px;border-left:1px solid #d8d0ff;">
                                                                <a href="{{ $verificationUrl }}" style="display:inline-block;color:#6659ee;font-size:13px;font-weight:800;text-decoration:none;">Salin</a>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:18px 36px 0;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border:1px solid #e2dcff;border-radius:12px;background:#ffffff;">
                                            <tr>
                                                <td style="padding:14px 18px;color:#6f6a9f;font-size:14px;line-height:1.5;font-weight:600;">
                                                    <span style="display:inline-block;margin-right:10px;color:#6659ee;font-size:18px;vertical-align:-2px;">ⓘ</span>
                                                    Jika kamu tidak merasa mendaftar, abaikan email ini.
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td align="center" style="padding:28px 36px 34px;">
                                        <p style="margin:0 0 10px;color:#6f6a9f;font-size:14px;line-height:1.5;font-weight:600;">
                                            Butuh bantuan? Hubungi <a href="mailto:{{ $helpEmail }}" style="color:#6659ee;text-decoration:underline;">{{ $helpEmail }}</a>
                                        </p>
                                        <p style="margin:0;color:#7f79ad;font-size:14px;line-height:1.5;font-weight:600;">
                                            &copy; {{ now()->year }} {{ $brandName }}. All rights reserved.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
