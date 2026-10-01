@component('emails.layouts.brand', ['title' => 'Support ticket #' . date('Y') . $ticketData['id'], 'preheader' => 'Your support ticket details.'])
@include('emails.partials.details', ['details' => [
    'Subject' => $ticketData['subject'],
    'Category' => $ticketData['category'],
    'Priority' => ucfirst($ticketData['priority']),
    'Shipment number' => $ticketData['shipment_number'] ?? 'N/A',
    'Submitted on' => date('F j, Y, g:i a'),
]])
<h2 style="margin:24px 0 12px;font-size:16px;color:#012642">Message</h2>
<p style="margin:0;white-space:pre-line">{{ $ticketData['message'] }}</p>
<p style="margin:24px 0 0;font-size:14px;color:#52616d">Open your dashboard to respond to this ticket.</p>
@endcomponent
