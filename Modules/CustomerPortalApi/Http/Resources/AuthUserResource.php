<?php

namespace Modules\CustomerPortalApi\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Modules\CustomerPortalApi\Models\PortalFile;

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
            'avatar' => $this->avatarUrl($client),
            'provider' => $this->provider ?: 'password',
            'verified' => (bool) $this->verified,
        ];
    }

    private function avatarUrl($client)
    {
        if (!$this->avatar) {
            return null;
        }

        if (str_starts_with($this->avatar, 'portal-file:')) {
            $fileId = substr($this->avatar, strlen('portal-file:'));
            $file = PortalFile::where('file_id', $fileId)
                ->when($client, fn ($query) => $query->where('client_id', $client->id))
                ->where('purpose', 'profile-photo')
                ->where('status', 'scan_pending')
                ->first();

            return $file?->authorizedUrl;
        }

        try {
            $mimeType = Storage::disk('public')->mimeType($this->avatar);
            if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                return null;
            }
        } catch (\Throwable $exception) {
            return null;
        }

        return Storage::disk('public')->url($this->avatar);
    }
}
