<?php

namespace Modules\CustomerPortalApi\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Cargo\Entities\Shipment;

class ShipmentResource extends JsonResource
{
    public function toArray($request)
    {
        $consignment = $this->consignment;
        $mode = strtolower((string) optional($consignment)->cargo_type);
        $mode = in_array($mode, ['air', 'sea'], true) ? $mode : 'air';
        $status = $this->portalStatus();

        return [
            'id' => (string) $this->id,
            'customerId' => (string) $this->client_id,
            'trackingNumber' => (string) $this->code,
            'carrier' => optional($consignment)->shipping_line,
            'transportMode' => $mode,
            'packageName' => $this->description,
            'origin' => optional($consignment)->source ?: $this->getRawOriginal('from_address'),
            'destination' => optional($consignment)->destination ?: $this->getRawOriginal('to_address'),
            'etaAt' => $this->isoDate(optional($consignment)->eta),
            'etaLabel' => $this->displayDate(optional($consignment)->eta),
            'status' => $status['status'],
            'statusLabel' => $status['label'],
            'price' => [
                'currency' => 'USD',
                'amountMinor' => max(0, (int) round(((float) ($this->amount_to_be_collected ?: $this->shipping_cost ?: 0)) * 100)),
            ],
            'imageUrl' => null,
            'progress' => $this->portalProgress(),
            'events' => $this->portalEvents(),
            'nextAction' => null,
            'allowedActions' => [],
            'revision' => (int) ($this->revision ?: 1),
        ];
    }

    private function isoDate($value)
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function displayDate($value)
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('M j, Y');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function portalStatus()
    {
        $consignmentStatus = strtolower((string) optional($this->consignment)->status);

        if (in_array($consignmentStatus, ['pending', 'in_transit', 'delivered', 'cancelled'], true)) {
            return [
                'status' => $consignmentStatus,
                'label' => ucfirst(str_replace('_', ' ', $consignmentStatus)),
            ];
        }

        $map = [
            Shipment::SAVED_STATUS => ['pending', 'Pending'],
            Shipment::REQUESTED_STATUS => ['pending', 'Pending'],
            Shipment::APPROVED_STATUS => ['pending', 'Approved'],
            Shipment::CLOSED_STATUS => ['cancelled', 'Closed'],
            Shipment::RECIVED_STATUS => ['at_destination', 'Received'],
            Shipment::DELIVERED_STATUS => ['delivered', 'Delivered'],
            Shipment::IN_STOCK_STATUS => ['at_destination', 'At destination'],
            Shipment::RETURNED_STATUS => ['failed', 'Returned'],
            Shipment::RETURNED_STOCK => ['failed', 'Returned to stock'],
            Shipment::RETURNED_CLIENT_GIVEN => ['failed', 'Returned to customer'],
        ];

        $fallback = $map[(int) $this->status_id] ?? ['pending', 'Pending'];

        return ['status' => $fallback[0], 'label' => $fallback[1]];
    }

    private function portalProgress()
    {
        $consignment = $this->consignment;
        $stageCount = $consignment ? count($consignment->getTrackingStages()) : 0;
        $stage = $consignment ? (int) $consignment->getCurrentStage() : 0;

        if ($stageCount < 1 || $stage < 1) {
            return $this->portalStatus()['status'] === 'delivered' ? 100 : 0;
        }

        return min(100, max(0, (int) round(($stage / $stageCount) * 100)));
    }

    private function portalEvents()
    {
        $consignment = $this->consignment;
        if (!$consignment || !$consignment->relationLoaded('trackingHistory')) {
            return [];
        }

        $stageDescriptions = $consignment->getTrackingStages();

        return $consignment->trackingHistory->sortBy('completed_at')->values()->map(function ($event) use ($stageDescriptions) {
            $stageId = (int) $event->stage_id;
            $occurredAt = $event->completed_at ? $event->completed_at->toIso8601String() : null;

            return [
                'id' => (string) $event->id,
                'label' => $stageDescriptions[$stageId] ?? ('Stage ' . $stageId),
                'detail' => $event->notes ?: $event->location,
                'occurredAt' => $occurredAt,
                'displayTime' => $event->completed_at ? $event->completed_at->format('M j, Y g:i A') : 'Pending',
                'complete' => $event->status === 'completed',
                'current' => false,
            ];
        })->all();
    }
}
