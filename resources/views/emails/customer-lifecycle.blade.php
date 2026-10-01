@component('emails.layouts.brand', ['title' => $content['subject'], 'preheader' => $content['body']])
<p style="margin:0 0 16px">Hello {{ $content['name'] ?: 'Customer' }},</p>
<p style="margin:0 0 20px">{{ $content['body'] }}</p>
@if(!empty($content['details']))
@include('emails.partials.details', ['details' => $content['details']])
@endif
@include('emails.partials.button', ['url' => 'https://app.newworldcargo.com' . $content['path'], 'label' => $content['action']])
@endcomponent
