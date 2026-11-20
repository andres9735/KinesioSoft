<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <meta name="color-scheme" content="light">
    <title>{{ $titulo }} — KinesioSoft</title>
    <style>
        body, table, td, a { -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; }
        table { mso-table-lspace:0pt; mso-table-rspace:0pt; }
        @media only screen and (max-width:480px) {
            .email-page { padding:24px 16px !important; }
            .email-card { padding:24px 20px !important; }
            .email-title { font-size:27px !important; line-height:33px !important; }
            .email-column { display:block !important; width:100% !important; box-sizing:border-box; }
            .email-column-last { padding-top:16px !important; }
            .email-time { text-align:left !important; padding-top:10px !important; }
            .email-button-cell { display:block !important; padding:0 0 10px !important; }
            .email-button { display:block !important; text-align:center; padding:14px 12px !important; }
            .email-notice { font-size:17px !important; line-height:23px !important; }
            .email-notice-break { display:none !important; }
            .email-button-table { width:100% !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#edf0f7;color:#1b233d;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#edf0f7" style="width:100%;background-color:#edf0f7;">
        <tr><td align="center" class="email-page" style="padding:31px 24px 32px;">
            <!--[if mso]><table role="presentation" width="612" align="center"><tr><td><![endif]-->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:612px;">
                <tr><td style="padding:0 0 17.5px;">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td width="30" height="30" align="center" valign="middle" bgcolor="#473ac5" style="width:30px;height:30px;border-radius:9px;background-color:#473ac5;">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                                    <tr><td width="5" height="5"></td><td width="5" height="5" bgcolor="#ffffff" style="background-color:#ffffff;"></td><td width="5" height="5"></td></tr>
                                    <tr><td width="5" height="5" bgcolor="#ffffff" style="background-color:#ffffff;"></td><td width="5" height="5" bgcolor="#ffffff" style="background-color:#ffffff;"></td><td width="5" height="5" bgcolor="#ffffff" style="background-color:#ffffff;"></td></tr>
                                    <tr><td width="5" height="5"></td><td width="5" height="5" bgcolor="#ffffff" style="background-color:#ffffff;"></td><td width="5" height="5"></td></tr>
                                </table>
                            </td>
                            <td style="padding-left:10px;font-size:20px;line-height:30px;font-weight:700;color:#1b233d;">KinesioSoft</td>
                        </tr>
                    </table>
                </td></tr>
                <tr><td class="email-card" bgcolor="#ffffff" style="padding:25.5px 31px {{ $pie ?? 30 }}px;background-color:#ffffff;border:1px solid #dee3ee;border-radius:16px;box-shadow:0 5px 24px rgba(36,46,75,.06);">
                    <table role="presentation" width="33" cellpadding="0" cellspacing="0" border="0" style="width:33px;"><tr><td height="4" bgcolor="#473ac5" style="height:4px;line-height:4px;font-size:1px;background-color:#473ac5;border-radius:3px;">&nbsp;</td></tr></table>
                    <h1 class="email-title" style="margin:14px 0 8px;font-size:31px;line-height:36px;font-weight:700;color:#1b233d;">{{ $titulo }}</h1>
                    @yield('contenido')
                </td></tr>
            </table>
            <!--[if mso]></td></tr></table><![endif]-->
        </td></tr>
    </table>
</body>
</html>
