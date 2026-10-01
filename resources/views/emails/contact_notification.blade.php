@component('emails.layouts.brand', ['title' => 'New contact message', 'preheader' => 'A message has been received through the contact form.'])
@include('emails.partials.details', ['details' => ['Name' => $contactData['name'], 'Email' => $contactData['email']]])
<h2 style="margin:24px 0 12px;font-size:16px;color:#012642">Message</h2>
<p style="margin:0;white-space:pre-line">{{ $contactData['message'] }}</p>
@endcomponent
