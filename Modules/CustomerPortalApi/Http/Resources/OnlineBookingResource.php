<?php

namespace Modules\CustomerPortalApi\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OnlineBookingResource extends JsonResource
{
    public function toArray($request)
    {
        $status = $this->status === 'accepted' ? 'booking_confirmed'
            : ($this->status === 'rejected' ? 'cancelled' : 'pending');
        $statusLabel = $this->status === 'accepted' ? 'Booking accepted'
            : ($this->status === 'rejected' ? 'Booking declined' : 'Awaiting review');

        return [
            'id' => 'booking-' . $this->id,
            'bookingId' => (string) $this->id,
            'trackingNumber' => $this->shipment?->code ?: $this->reference,
            'reference' => $this->reference,
            'shipmentId' => $this->shipment_id ? (string) $this->shipment_id : null,
            'customerId' => (string) $this->client_id,
            'service' => $this->service,
            'transportMode' => $this->transport_mode,
            'packageName' => $this->payload['cargoRows'][0]['name'] ?? 'Online booking request',
            'parcelOwner' => $this->recipient_name,
            'origin' => $this->pickup_address,
            'destination' => $this->destination_address,
            'status' => $status,
            'statusLabel' => $statusLabel,
            'price' => [
                'currency' => $this->currency,
                'amountMinor' => (int) round(((float) $this->quoted_amount) * 100),
            ],
            'progress' => 0,
            'events' => [[
                'id' => 'booking-submitted-' . $this->id,
                'label' => 'Booking request received',
                'detail' => 'New World Cargo will review this request before creating a shipment.',
                'occurredAt' => optional($this->submitted_at)->toIso8601String(),
                'displayTime' => optional($this->submitted_at)->format('M j, Y g:i A'),
                'complete' => true,
                'current' => true,
            ]],
            'allowedActions' => ['report_issue'],
            'revision' => 1,
            'updatedAt' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
