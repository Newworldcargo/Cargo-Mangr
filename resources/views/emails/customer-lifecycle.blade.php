<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $content['subject'] }}</title></head>
<body style="margin:0;background:#f3f4f6;color:#17202a;font-family:Arial,sans-serif;line-height:1.6">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center" style="padding:24px 12px">
<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:100%;max-width:600px;background:#ffffff">
<tr><td style="padding:24px;background:#0666ba;color:#ffffff;font-size:22px;font-weight:bold">New World Cargo</td></tr>
<tr><td style="padding:24px">
<h1 style="font-size:22px;line-height:1.3;margin:0 0 20px">{{ $content['subject'] }}</h1>
<p>Hello {{ $content['name'] ?: 'Customer' }},</p>
<p>{{ $content['body'] }}</p>
@if(!empty($content['details']))
<table width="100%" cellspacing="0" cellpadding="8" style="border-collapse:collapse">
@foreach($content['details'] as $label => $value)
<tr><th scope="row" style="text-align:left;vertical-align:top;border-bottom:1px solid #e5e7eb">{{ $label }}</th><td style="border-bottom:1px solid #e5e7eb;overflow-wrap:anywhere">{{ $value }}</td></tr>
@endforeach
</table>
@endif
<p style="margin:28px 0"><a href="https://app.newworldcargo.com{{ $content['path'] }}" style="display:inline-block;background:#0666ba;color:#ffffff;padding:12px 20px;text-decoration:none;border-radius:4px">{{ $content['action'] }}</a></p>
<p>Thank you,<br>The New World Cargo team</p>
</td></tr></table>
</td></tr></table>
</body></html>
