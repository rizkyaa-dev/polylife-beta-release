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
    $displayUrl = \Illuminate\Support\Str::limit(preg_replace('#^https?://#', '', $verificationUrl), 46);
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
        @media only screen and (max-width: 720px) {
            .email-shell { padding: 22px 10px !important; }
            .email-card { width: 100% !important; }
            .email-body { padding: 30px 20px 28px !important; }
            .email-header { padding: 26px 20px 10px !important; }
            .email-title { font-size: 28px !important; line-height: 1.25 !important; }
            .email-copy { font-size: 16px !important; }
            .email-button { display: block !important; min-width: 0 !important; width: auto !important; }
            .fallback-cell { display: block !important; width: auto !important; padding: 0 0 14px !important; }
            .fallback-link-cell { display: block !important; width: auto !important; padding: 0 !important; }
            .fallback-link-table { width: 100% !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#fdf8ff;color:#2d2d3c;font-family:Arial,'Helvetica Neue',Helvetica,sans-serif;-webkit-text-size-adjust:100%;text-size-adjust:100%;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        Tinggal satu langkah lagi untuk mulai menggunakan {{ $brandName }}.
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background-color:#fdf8ff;background-image:linear-gradient(transparent 31px,rgba(129,129,255,0.12) 32px),linear-gradient(90deg,transparent 31px,rgba(129,129,255,0.12) 32px),linear-gradient(135deg,#fff7fb 0%,#f7f4ff 50%,#fdf8ff 100%);background-size:32px 32px,32px 32px,100% 100%;">
        <tr>
            <td class="email-shell" align="center" style="padding:48px 16px;">
                <table role="presentation" class="email-card" width="760" cellspacing="0" cellpadding="0" border="0" style="width:760px;max-width:760px;border-collapse:separate;background-color:#fdfbff;border:4px solid #8181ff;border-radius:28px;box-shadow:12px 12px 0 #c5d4ff;overflow:hidden;">
                    <tr>
                        <td class="email-header" style="padding:34px 36px 12px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="left">
                                        <span style="display:inline-block;padding:9px 18px;border:2px solid rgba(43,34,80,0.20);border-radius:14px;background-color:#ffffff;color:#6d6797;font-size:12px;line-height:1.2;font-weight:800;letter-spacing:5px;text-transform:uppercase;">
                                            <span style="display:inline-block;width:9px;height:9px;margin-right:10px;background-color:#8181ff;vertical-align:1px;"></span>Verifikasi Akun
                                        </span>
                                    </td>
                                    <td align="right" style="width:64px;">
                                        <span style="display:inline-block;width:42px;height:42px;border:4px solid #2b2250;background-color:#f49cc8;box-shadow:6px 6px 0 #2b2250;"></span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td class="email-body" style="padding:20px 36px 34px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="center" style="padding:0 0 26px;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 auto;">
                                            <tr>
                                                <td style="width:26px;height:26px;background-color:#f49cc8;border:3px solid #2b2250;box-shadow:4px 4px 0 #2b2250;font-size:1px;line-height:1px;">&nbsp;</td>
                                                <td width="42" style="font-size:1px;line-height:1px;">&nbsp;</td>
                                                <td rowspan="2" align="center" style="width:160px;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 auto;">
                                                        <tr>
                                                            <td align="center" style="padding:0;">
                                                                <div style="width:62px;height:62px;line-height:62px;border:4px solid #2b2250;border-radius:18px;background-color:#8181ff;color:#ffffff;font-size:36px;font-weight:800;box-shadow:6px 6px 0 #2b2250;">
                                                                    &#10003;
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td align="center" style="padding:0;">
                                                                <div style="width:128px;height:84px;background-color:#f6f4ff;background-image:linear-gradient(135deg,#c5d4ff 0%,#ffffff 50%,#b598ff 100%);border:3px solid #8181ff;box-shadow:6px 6px 0 #c5d4ff;"></div>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                                <td width="42" style="font-size:1px;line-height:1px;">&nbsp;</td>
                                                <td style="width:44px;height:28px;background-color:#b5f1ff;border:3px solid #2b2250;box-shadow:4px 4px 0 #2b2250;font-size:1px;line-height:1px;">&nbsp;</td>
                                            </tr>
                                            <tr>
                                                <td style="height:92px;font-size:1px;line-height:1px;">&nbsp;</td>
                                                <td style="font-size:1px;line-height:1px;">&nbsp;</td>
                                                <td style="font-size:1px;line-height:1px;">&nbsp;</td>
                                                <td align="center" style="font-size:17px;line-height:9px;color:#c5d4ff;letter-spacing:9px;">
                                                    &bull;&bull;&bull;<br>&bull;&bull;&bull;<br>&bull;&bull;&bull;
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td align="center">
                                        <h1 class="email-title" style="margin:0;color:#2d2d3c;font-size:34px;line-height:1.2;font-weight:800;letter-spacing:-0.4px;">
                                            Selamat datang di {{ $brandName }} <span style="font-size:30px;line-height:1;">&#128075;</span>
                                        </h1>
                                        <p class="email-copy" style="margin:18px 0 0;color:#6d6797;font-size:18px;line-height:1.55;font-weight:500;">
                                            Tinggal satu langkah lagi untuk mulai menggunakan {{ $brandName }}.
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <td align="center" style="padding:34px 0 16px;">
                                        <a href="{{ $verificationUrl }}" class="email-button" style="display:inline-block;min-width:410px;padding:18px 28px;border-radius:18px;background-color:#8181ff;border:3px solid #2b2250;box-shadow:6px 6px 0 #2b2250;color:#ffffff;font-size:18px;line-height:1.2;font-weight:800;text-decoration:none;text-align:center;letter-spacing:0.2px;">
                                            <span style="display:inline-block;margin-right:14px;font-size:25px;line-height:0;vertical-align:-4px;">&#9993;</span>Verifikasi Email Sekarang
                                        </a>
                                    </td>
                                </tr>

                                <tr>
                                    <td align="center" style="padding:8px 0 28px;">
                                        <p style="margin:0;color:#6f6aa1;font-size:14px;line-height:1.5;font-weight:600;">
                                            <span style="display:inline-block;margin-right:8px;color:#8181ff;font-size:17px;vertical-align:-2px;">&#9716;</span>
                                            Link ini berlaku selama {{ $expiryText }} menit.
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:0 0 22px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td style="border-top:2px solid #dcd3ff;height:1px;line-height:1px;font-size:1px;">&nbsp;</td>
                                                <td align="center" width="72" style="width:72px;color:#b598ff;font-size:13px;line-height:1;font-weight:800;">atau</td>
                                                <td style="border-top:2px solid #dcd3ff;height:1px;line-height:1px;font-size:1px;">&nbsp;</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:0 0 18px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border:2px solid rgba(129,129,255,0.40);border-radius:18px;background-color:#f6f4ff;box-shadow:4px 4px 0 #c5d4ff;">
                                            <tr>
                                                <td class="fallback-cell" width="76" style="width:76px;padding:18px 0 18px 18px;">
                                                    <div style="width:58px;height:58px;line-height:58px;text-align:center;border-radius:18px;background-color:#ffffff;border:2px solid #dcd3ff;color:#8181ff;font-size:30px;font-weight:800;">&#128279;</div>
                                                </td>
                                                <td class="fallback-cell" style="padding:18px 14px;">
                                                    <p style="margin:0 0 7px;color:#2d2d3c;font-size:15px;line-height:1.35;font-weight:800;">Tombol tidak bisa diklik?</p>
                                                    <p style="margin:0;color:#6d6797;font-size:14px;line-height:1.45;font-weight:500;">Salin dan buka tautan ini di browser kamu.</p>
                                                </td>
                                                <td class="fallback-link-cell" width="330" style="width:330px;padding:18px 18px 18px 0;">
                                                    <table role="presentation" class="fallback-link-table" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border:2px solid #dcd3ff;border-radius:14px;background-color:#ffffff;">
                                                        <tr>
                                                            <td style="padding:13px 14px;color:#8181ff;font-size:14px;line-height:1.35;font-weight:700;word-break:break-all;overflow-wrap:anywhere;">
                                                                <a href="{{ $verificationUrl }}" style="color:#8181ff;text-decoration:none;">{{ $displayUrl }}</a>
                                                            </td>
                                                            <td align="center" width="82" style="width:82px;border-left:2px solid #dcd3ff;">
                                                                <a href="{{ $verificationUrl }}" style="display:inline-block;color:#8181ff;font-size:13px;font-weight:800;text-decoration:none;">
                                                                    <span style="font-size:18px;vertical-align:-3px;margin-right:5px;">&#128203;</span>Salin
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:0 0 26px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border:2px solid rgba(43,34,80,0.12);border-radius:18px;background-color:#ffffff;">
                                            <tr>
                                                <td style="padding:14px 18px;color:#6d6797;font-size:14px;line-height:1.5;font-weight:600;">
                                                    <span style="display:inline-block;margin-right:10px;color:#8181ff;font-size:18px;vertical-align:-2px;">&#9432;</span>
                                                    Jika kamu tidak merasa mendaftar, abaikan email ini.
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td align="center">
                                        <p style="margin:0 0 8px;color:#6d6797;font-size:14px;line-height:1.5;font-weight:600;">
                                            Butuh bantuan? Hubungi <a href="mailto:{{ $helpEmail }}" style="color:#8181ff;text-decoration:underline;text-decoration-style:dashed;text-underline-offset:4px;">{{ $helpEmail }}</a>
                                        </p>
                                        <p style="margin:0;color:#7d78ad;font-size:14px;line-height:1.5;font-weight:600;">
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
