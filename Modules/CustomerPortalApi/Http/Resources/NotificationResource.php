<?php

namespace Modules\CustomerPortalApi\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray($request)
    {
        $data = is_array($this->data) ? $this->data : json_decode((string) $this->data, true);
        $data = is_array($data) ? $data : [];

        return [
            'id' => (string) $this->id,
            'customerId' => (string) $this->notifiable_id,
            'type' => $this->portalType(),
            'title' => (string) ($data['title'] ?? $data['message'] ?? 'New World Cargo update'),
            'body' => (string) ($data['body'] ?? $data['message'] ?? ''),
            'occurredAt' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'displayTime' => $this->created_at ? $this->created_at->format('M j, Y g:i A') : '',
            'shipmentId' => isset($data['shipment_id']) ? (string) $data['shipment_id'] : null,
            'unread' => $this->read_at === null,
            'revision' => (int) ($this->revision ?: 1),
        ];
    }

    private function portalType()
    {
        $type = strtolower((string) $this->type);
        if (strpos($type, 'payment') !== false) {
            return 'payment';
        }
        if (strpos($type, 'exception') !== false || strpos($type, 'fail') !== false) {
            return 'exception';
        }
        if (strpos($type, 'arrival') !== false || strpos($type, 'deliver') !== false) {
            return 'arrival';
        }

        return 'progress';
    }
}
