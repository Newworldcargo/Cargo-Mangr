<?php

namespace Modules\CustomerPortalApi\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AuthUserResource extends JsonResource
{
    public function toArray($request)
    {
        $parts = preg_split('/\s+/', trim((string) $this->name), 2);
        $client = $this->relationLoaded('portalClient') ? $this->portalClient : null;

        return [
            'id' => (string) $this->id,
            'firstName' => $parts[0] ?? '',
            'lastName' => $parts[1] ?? '',
            'email' => $this->email,
            'phone' => $this->responsible_mobile ?: optional($client)->responsible_mobile,
            'avatar' => $this->avatar ? Storage::url($this->avatar) : null,
            'provider' => $this->provider ?: 'password',
            'verified' => (bool) $this->verified,
        ];
    }
}
