<?php

namespace Modules\CustomerPortalApi\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PortalFileResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'fileId' => (string) $this->file_id,
            'url' => $this->authorizedUrl,
            'contentType' => $this->content_type,
            'sizeBytes' => (int) $this->size_bytes,
            'purpose' => $this->purpose,
            'status' => $this->status,
            'expiresAt' => $this->expires_at ? $this->expires_at->toIso8601String() : null,
            'revision' => (int) ($this->revision ?: 1),
        ];
    }
}
