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
            'checkoutUrl' => $this->checkout_url,
            'amount' => ['currency' => $this->currency, 'amountMinor' => (int) $this->amount_minor],
            'revision' => (int) ($this->revision ?: 1),
        ];
    }
}
