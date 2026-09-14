<?php

namespace Modules\CustomerPortalApi\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray($request)
    {
        $data = is_array($this->data) ? $this->data : json_decode((string) $this->data, true);
        $data = is_array($data) ? $data : [];
        $message = $this->notificationMessage($data);

        return [
            'id' => (string) $this->id,
            'customerId' => (string) $this->notifiable_id,
            'type' => $this->portalType(),
            'title' => $this->notificationTitle($data, $message),
            'body' => $this->notificationBody($data, $message),
            'occurredAt' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'displayTime' => $this->created_at ? $this->created_at->format('M j, Y g:i A') : '',
            'shipmentId' => $this->shipmentId($data),
            'unread' => $this->read_at === null,
            'revision' => (int) ($this->revision ?: 1),
        ];
    }

    private function notificationMessage(array $data): array
    {
        $message = $data['message'] ?? [];
        if (is_array($message)) {
            return $message;
        }

        return ['content' => is_scalar($message) ? (string) $message : ''];
    }

    private function notificationTitle(array $data, array $message): string
    {
        foreach ([$data['title'] ?? null, $message['title'] ?? null, $message['subject'] ?? null, $message['content'] ?? null] as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return 'New World Cargo update';
    }

    private function notificationBody(array $data, array $message): string
    {
        foreach ([$data['body'] ?? null, $message['body'] ?? null, $message['content'] ?? null, $message['subject'] ?? null] as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return '';
    }

    private function shipmentId(array $data): ?string
    {
        $message = $this->notificationMessage($data);
        foreach ([$data['shipment_id'] ?? null, $data['shipmentId'] ?? null, $message['shipment_id'] ?? null, $message['shipmentId'] ?? null, $message['id'] ?? null] as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return null;
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
