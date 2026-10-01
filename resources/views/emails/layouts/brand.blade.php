<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{{ $title }}</title>
<style>
    body, table, td, a { -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; }
    table { mso-table-lspace:0pt; mso-table-rspace:0pt; }
    @media only screen and (max-width:480px) {
        .email-padding { padding:24px 20px !important; }
        .email-outer { padding:12px 8px !important; }
    }
</style>
</head>
<body style="margin:0;padding:0;background-color:#f2f4f6;color:#202b33;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.65;letter-spacing:0">
<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all">{{ $preheader ?? $title }}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f2f4f6"><tr><td class="email-outer" align="center" style="padding:32px 12px">
<!--[if mso]><table role="presentation" width="600" align="center"><tr><td><![endif]-->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background-color:#ffffff;border:1px solid #e1e6ea;border-radius:8px">
<tr><td class="email-padding" bgcolor="#012642" style="padding:24px 32px;background-color:#012642;border-bottom:4px solid #ffcc04">
<a href="https://app.newworldcargo.com" style="color:#ffcc04;text-decoration:none"><img src="https://admin.newworldcargo.com/assets/lte/cargo-logo-email.png" width="180" height="72" alt="New World Cargo" style="display:block;width:180px;height:72px;max-width:100%;border:0;color:#ffcc04;font-size:20px"></a>
</td></tr>
<tr><td class="email-padding" style="padding:32px;overflow-wrap:anywhere;word-break:break-word">
<h1 style="margin:0 0 24px;font-size:24px;line-height:1.3;font-weight:700;color:#012642">{{ $title }}</h1>
{{ $slot }}
<p style="margin:28px 0 0;font-size:14px;color:#52616d">Thank you,<br><strong style="color:#012642">The New World Cargo team</strong></p>
</td></tr>
<tr><td class="email-padding" style="padding:20px 32px;border-top:1px solid #e1e6ea;font-size:13px;line-height:1.7;color:#52616d">
<a href="https://app.newworldcargo.com" style="color:#0055a4;text-decoration:underline">Customer portal</a>
<span aria-hidden="true" style="color:#52616d"> &nbsp;|&nbsp; </span>
<a href="https://newworldcargo.com/contact-us" style="color:#0055a4;text-decoration:underline">Contact us</a>
<p style="margin:12px 0 0">&copy; {{ date('Y') }} New World Cargo. All rights reserved.</p>
</td></tr></table>
<!--[if mso]></td></tr></table><![endif]-->
</td></tr></table>
</body>
</html>
