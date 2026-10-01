@component('emails.layouts.brand', ['title' => 'Your verification code', 'preheader' => 'Use this code to complete your request. Never share it with anyone.'])
<p style="margin:0 0 16px">Hello {{ $customerName }},</p>
<p style="margin:0 0 24px">Enter this code to complete your request.</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:20px;background-color:#f2f4f6;border:1px solid #e1e6ea;border-radius:4px">
<span style="font-family:Consolas,'Courier New',monospace;font-size:36px;line-height:1.4;font-weight:700;letter-spacing:0;color:#012642">{{ $otp }}</span>
</td></tr></table>
<p style="margin:20px 0 0;font-size:14px;color:#52616d">This code expires in 10 minutes. Never share it with anyone, including our staff.</p>
<p style="margin:16px 0 0;font-size:14px;color:#52616d">If you did not request this code, you can ignore this email.</p>
@endcomponent
