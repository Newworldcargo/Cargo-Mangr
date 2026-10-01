@component('emails.layouts.brand', ['title' => 'Welcome to New World Cargo', 'preheader' => 'Your account is ready. Manage your shipments in one place.'])
<p style="margin:0 0 16px">Hello {{ $user['name'] }},</p>
<p style="margin:0 0 20px">Your account is ready. You can book a shipment, follow its progress, and manage your payments.</p>
@include('emails.partials.details', ['details' => ['Your email' => $user['email']]])
@include('emails.partials.button', ['url' => 'https://app.newworldcargo.com/shipments', 'label' => 'View my shipments'])
@endcomponent
