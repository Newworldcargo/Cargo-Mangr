<?php

namespace Modules\CustomerPortalApi\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PortalPaymentIntentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => (string) $this->intent_id,
            'status' => $this->status,
            'providerReference' => $this->provider_reference,
            'clientToken' => $this->client_token,
            'provider' => $this->provider,
            'method' => $this->method,
            'checkoutUrl' => $this->status === 'requires_action' ? $this->checkout_url : null,
            'canRetry' => $this->status === 'failed',
            'message' => [
                'processing' => 'Your payment is being checked. Do not pay again while confirmation is pending.',
                'requires_action' => 'Continue to secure checkout to complete your card payment.',
                'review' => 'Please contact your branch about this payment. Do not pay again while we check it.',
                'failed' => 'Payment was not completed. Check your details before trying again.',
                'succeeded' => 'Payment confirmed. Your receipt is ready.',
            ][$this->status] ?? 'Your payment is being checked.',
            'amount' => ['currency' => $this->currency, 'amountMinor' => (int) $this->amount_minor],
            'revision' => (int) ($this->revision ?: 1),
        ];
    }
}
