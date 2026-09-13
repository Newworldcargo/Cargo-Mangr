<?php

namespace Modules\CustomerPortalApi\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PickupResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => (string) $this->id,
            'customerId' => (string) $this->client_id,
            'shipmentId' => $this->shipment_id ? (string) $this->shipment_id : null,
            'shipmentReference' => optional($this->shipment)->code ?: ($this->shipment_id ? 'Shipment #' . $this->shipment_id : 'Shipment'),
            'status' => $this->status,
            'location' => $this->collection_point,
            'collectionPoint' => $this->collection_point,
            'scheduledSlotId' => $this->scheduled_slot_id ?: $this->slotIdFromSchedule(),
            'scheduledDate' => $this->scheduled_date,
            'scheduledTime' => $this->scheduled_time,
            'revision' => (int) ($this->revision ?: 1),
        ];
    }

    private function slotIdFromSchedule()
    {
        if (!$this->scheduled_date) {
            return 'today-pm';
        }

        $date = (string) $this->scheduled_date;
        $time = (string) $this->scheduled_time;
        $tomorrow = now()->addDay()->format('Y-m-d');

        if ($date === $tomorrow && strpos($time, '09:00') !== false) {
            return 'tomorrow-am';
        }
        if ($date === $tomorrow) {
            return 'tomorrow-pm';
        }

        return 'today-pm';
    }
}
