<?php

namespace Modules\CustomerPortalApi\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentMethodResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => (string) $this->id,
            'method' => $this->method,
            'label' => $this->label ?: ucfirst(str_replace('-', ' ', $this->method)),
            'detail' => $this->detail ?: 'Saved for faster checkout',
            'isDefault' => (bool) $this->is_default,
            'revision' => (int) ($this->revision ?: 1),
            'createdAt' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updatedAt' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];
    }
}
