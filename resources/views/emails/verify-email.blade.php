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
<body style="margin:0;padding:0;background-color:#fbf9ff;color:#1c2138;font-family:Arial,'Helvetica Neue',Helvetica,sans-serif;-webkit-text-size-adjust:100%;text-size-adjust:100%;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        Tinggal satu langkah lagi untuk mulai menggunakan {{ $brandName }}.
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background-color:#fbf9ff;background-image:linear-gradient(#eeeaff 1px,transparent 1px),linear-gradient(90deg,#eeeaff 1px,transparent 1px);background-size:28px 28px;">
        <tr>
            <td class="email-shell" align="center" style="padding:48px 16px;">
                <table role="presentation" class="email-card" width="760" cellspacing="0" cellpadding="0" border="0" style="width:760px;max-width:760px;border-collapse:separate;background-color:#ffffff;border:2px solid #b8a8ff;border-radius:24px;box-shadow:0 20px 50px rgba(91,80,242,0.10);overflow:hidden;">
                    <tr>
                        <td class="email-header" style="padding:34px 36px 12px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="left">
                                        <span style="display:inline-block;padding:9px 18px;border:1px solid #ded7ff;border-radius:999px;background-color:#ffffff;color:#6254f5;font-size:12px;line-height:1.2;font-weight:800;letter-spacing:5px;text-transform:uppercase;">
                                            <span style="display:inline-block;width:9px;height:9px;margin-right:10px;border-radius:2px;background-color:#6254f5;vertical-align:1px;"></span>Verifikasi Akun
                                        </span>
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
                                                <td style="width:26px;height:26px;background-color:#ffb6d0;border-radius:5px;font-size:1px;line-height:1px;">&nbsp;</td>
                                                <td width="42" style="font-size:1px;line-height:1px;">&nbsp;</td>
                                                <td rowspan="2" align="center" style="width:160px;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 auto;">
                                                        <tr>
                                                            <td align="center" style="padding:0;">
                                                                <div style="width:62px;height:62px;line-height:62px;border-radius:50%;background-color:#5b50f2;color:#ffffff;font-size:36px;font-weight:800;box-shadow:0 10px 20px rgba(91,80,242,0.22);">
                                                                    &#10003;
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td align="center" style="padding:0;">
                                                                <div style="width:128px;height:84px;border-radius:0 0 14px 14px;background-color:#cfc6ff;background-image:linear-gradient(135deg,#eeeaff 0%,#ffffff 48%,#b9adff 100%);border:1px solid #c9bfff;box-shadow:0 14px 28px rgba(91,80,242,0.12);"></div>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                                <td width="42" style="font-size:1px;line-height:1px;">&nbsp;</td>
                                                <td style="width:28px;height:20px;background-color:#bde2ff;border-radius:5px;font-size:1px;line-height:1px;">&nbsp;</td>
                                            </tr>
                                            <tr>
                                                <td style="height:92px;font-size:1px;line-height:1px;">&nbsp;</td>
                                                <td style="font-size:1px;line-height:1px;">&nbsp;</td>
                                                <td style="font-size:1px;line-height:1px;">&nbsp;</td>
                                                <td align="center" style="font-size:17px;line-height:9px;color:#d8d1ff;letter-spacing:9px;">
                                                    &bull;&bull;&bull;<br>&bull;&bull;&bull;<br>&bull;&bull;&bull;
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td align="center">
                                        <h1 class="email-title" style="margin:0;color:#1f233b;font-size:34px;line-height:1.2;font-weight:800;letter-spacing:-0.4px;">
                                            Selamat datang di {{ $brandName }} <span style="font-size:30px;line-height:1;">&#128075;</span>
                                        </h1>
                                        <p class="email-copy" style="margin:18px 0 0;color:#6d6797;font-size:18px;line-height:1.55;font-weight:500;">
                                            Tinggal satu langkah lagi untuk mulai menggunakan {{ $brandName }}.
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <td align="center" style="padding:34px 0 16px;">
                                        <a href="{{ $verificationUrl }}" class="email-button" style="display:inline-block;min-width:410px;padding:18px 28px;border-radius:14px;background-color:#6254f5;background-image:linear-gradient(135deg,#6d5cff 0%,#584cf1 100%);border:2px solid #211a64;box-shadow:0 6px 0 #2b226f;color:#ffffff;font-size:18px;line-height:1.2;font-weight:800;text-decoration:none;text-align:center;">
                                            <span style="display:inline-block;margin-right:14px;font-size:25px;line-height:0;vertical-align:-4px;">&#9993;</span>Verifikasi Email Sekarang
                                        </a>
                                    </td>
                                </tr>

                                <tr>
                                    <td align="center" style="padding:8px 0 28px;">
                                        <p style="margin:0;color:#6f6aa1;font-size:14px;line-height:1.5;font-weight:600;">
                                            <span style="display:inline-block;margin-right:8px;color:#6254f5;font-size:17px;vertical-align:-2px;">&#9716;</span>
                                            Link ini berlaku selama {{ $expiryText }} menit.
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:0 0 22px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td style="border-top:1px solid #e7e1ff;height:1px;line-height:1px;font-size:1px;">&nbsp;</td>
                                                <td align="center" width="72" style="width:72px;color:#b3a8ef;font-size:13px;line-height:1;font-weight:700;">atau</td>
                                                <td style="border-top:1px solid #e7e1ff;height:1px;line-height:1px;font-size:1px;">&nbsp;</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:0 0 18px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border:1px solid #ded7ff;border-radius:16px;background-color:#ffffff;">
                                            <tr>
                                                <td class="fallback-cell" width="76" style="width:76px;padding:18px 0 18px 18px;">
                                                    <div style="width:58px;height:58px;line-height:58px;text-align:center;border-radius:12px;background-color:#eeeaff;color:#6254f5;font-size:30px;font-weight:800;">&#128279;</div>
                                                </td>
                                                <td class="fallback-cell" style="padding:18px 14px;">
                                                    <p style="margin:0 0 7px;color:#1f233b;font-size:15px;line-height:1.35;font-weight:800;">Tombol tidak bisa diklik?</p>
                                                    <p style="margin:0;color:#6d6797;font-size:14px;line-height:1.45;font-weight:500;">Salin dan buka tautan ini di browser kamu.</p>
                                                </td>
                                                <td class="fallback-link-cell" width="330" style="width:330px;padding:18px 18px 18px 0;">
                                                    <table role="presentation" class="fallback-link-table" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border:1px solid #d9d1ff;border-radius:12px;background-color:#fbfaff;">
                                                        <tr>
                                                            <td style="padding:13px 14px;color:#6254f5;font-size:14px;line-height:1.35;font-weight:700;word-break:break-all;overflow-wrap:anywhere;">
                                                                <a href="{{ $verificationUrl }}" style="color:#6254f5;text-decoration:none;">{{ $displayUrl }}</a>
                                                            </td>
                                                            <td align="center" width="82" style="width:82px;border-left:1px solid #d9d1ff;">
                                                                <a href="{{ $verificationUrl }}" style="display:inline-block;color:#6254f5;font-size:13px;font-weight:800;text-decoration:none;">
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
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border:1px solid #e2dcff;border-radius:12px;background-color:#ffffff;">
                                            <tr>
                                                <td style="padding:14px 18px;color:#6d6797;font-size:14px;line-height:1.5;font-weight:600;">
                                                    <span style="display:inline-block;margin-right:10px;color:#6254f5;font-size:18px;vertical-align:-2px;">&#9432;</span>
                                                    Jika kamu tidak merasa mendaftar, abaikan email ini.
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td align="center">
                                        <p style="margin:0 0 8px;color:#6d6797;font-size:14px;line-height:1.5;font-weight:600;">
                                            Butuh bantuan? Hubungi <a href="mailto:{{ $helpEmail }}" style="color:#6254f5;text-decoration:underline;">{{ $helpEmail }}</a>
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
