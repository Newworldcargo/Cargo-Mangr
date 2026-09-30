@if(app(\Modules\Cargo\Services\ShipmentOperationAccessService::class)->canOperate(auth()->user(), $shipment, 'confirm-shipment-payment'))
    @php
        $cashSwitchReview = \Modules\CustomerPortalApi\Models\PortalPaymentIntent::where('shipment_id', $shipment->id)
            ->whereNotNull('cash_override_at')->whereIn('status', ['processing', 'requires_action', 'review'])->latest('id')->first();
    @endphp
    @if($cashSwitchReview)
        <div class="alert {{ $cashSwitchReview->status === 'review' ? 'alert-danger' : 'alert-warning' }}" role="alert">
            <strong>{{ $cashSwitchReview->status === 'review' ? 'Payment reconciliation required' : 'Cash switch recorded' }}</strong>
            <p class="mb-1">{{ $cashSwitchReview->status === 'review' ? 'An online payment needs review after the cash switch. Ask the payment team to reconcile any additional funds before issuing a refund or credit.' : 'Cancellation unconfirmed. A payment prompt may still arrive. Do not approve it after paying cash.' }}</p>
            <small>{{ $cashSwitchReview->currency }} {{ number_format($cashSwitchReview->amount_minor / 100, 2) }} &middot; Reference: {{ $cashSwitchReview->provider_reference ?: $cashSwitchReview->intent_id }}</small>
        </div>
    @endif
@endif
