<?php

namespace Modules\CustomerPortalApi\Http\Resources;

use Carbon\Carbon;
use App\Models\TrackingStage;
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
            'consignmentCode' => optional($consignment)->consignment_code,
            'carrier' => optional($consignment)->shipping_line,
            'transportMode' => $mode,
            'packageName' => $this->packageName(),
            'parcelOwner' => (string) ($this->reciver_name ?: ''),
            'origin' => optional($consignment)->source ?: $this->originAddress(),
            'destination' => optional($consignment)->destination ?: $this->getRawOriginal('reciver_address'),
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
            'nextAction' => $this->allowedActions()[0] ?? null,
            'allowedActions' => $this->allowedActions(),
            'revision' => (int) ($this->revision ?: 1),
        ];
    }

    private function packageName()
    {
        if ($this->relationLoaded('packages') && $this->packages->isNotEmpty()) {
            $package = $this->packages->first();
            return optional($package->pivot)->description ?: $package->name;
        }

        return null;
    }

    private function originAddress()
    {
        if ($this->relationLoaded('from_address') && $this->from_address) {
            return $this->from_address->address;
        }

        return $this->getRawOriginal('client_address');
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

    private function allowedActions()
    {
        $status = $this->portalStatus()['status'];
        $actions = ['report_issue'];
        if ($status === 'pending' && in_array((int) $this->status_id, [Shipment::SAVED_STATUS, Shipment::REQUESTED_STATUS], true)) {
            $actions[] = 'cancel';
        }
        if ($status === 'delivered') {
            $actions[] = 'duplicate';
        }
        if ($status === 'at_destination') {
            $actions[] = 'edit_delivery';
            $actions[] = 'collect_from_depot';
        }
        return $actions;
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

        $stages = TrackingStage::where('cargo_type', $consignment->cargo_type)
            ->orderBy('order')
            ->get();
        $history = $consignment->trackingHistory->keyBy('stage_id');
        $currentStageId = (int) $consignment->getCurrentStage();
        $events = [];

        foreach ($stages as $stage) {
            $completed = $history->get($stage->id);
            $isCurrent = !$completed && $currentStageId === (int) $stage->id;
            $events[] = [
                'id' => 'stage-' . $stage->id,
                'label' => $stage->description ?: ($stage->name ?: 'Tracking update'),
                'detail' => 'Shipment (Parcel) Code: ' . $this->code,
                'occurredAt' => $completed?->completed_at?->toIso8601String(),
                'displayTime' => $completed?->completed_at ? $completed->completed_at->format('M j, Y g:i A') : ($isCurrent ? 'Current stage' : 'Pending'),
                'complete' => (bool) $completed,
                'current' => $isCurrent,
            ];
        }

        if ($events && !collect($events)->contains(fn ($event) => $event['current'])) {
            $lastCompleted = array_key_last(array_filter($events, fn ($event) => $event['complete']));
            if ($lastCompleted !== null) $events[$lastCompleted]['current'] = true;
        }

        return $events;
    }
}
