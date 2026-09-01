<?php

namespace Modules\CustomerPortalApi\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SupportCaseResource extends JsonResource
{
    public function toArray($request)
    {
        $attachments = is_array($this->attachments) ? $this->attachments : [];

        return [
            'id' => (string) $this->id,
            'customerId' => (string) $this->user_id,
            'category' => $this->category,
            'subject' => $this->subject,
            'detail' => $this->message,
            'status' => $this->status === 'in_progress' ? 'in_review' : $this->status,
            'createdAt' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'displayCreatedAt' => $this->created_at ? $this->created_at->format('M j, Y') : null,
            'attachmentFileId' => isset($attachments[0]) ? (string) $attachments[0] : null,
            'revision' => (int) ($this->portal_revision ?: 1),
        ];
    }
}
