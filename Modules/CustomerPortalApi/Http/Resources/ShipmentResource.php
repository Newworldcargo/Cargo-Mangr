<?php

namespace Modules\CustomerPortalApi\Http\Resources;

use Carbon\Carbon;
use App\Models\TrackingStage;
use App\Models\Transxn;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Cargo\Entities\Shipment;
use Modules\CustomerPortalApi\Models\PortalShipmentDraft;
use Modules\CustomerPortalApi\Models\PortalQuote;

class ShipmentResource extends JsonResource
{
    private $portalDraftResolved = false;
    private $portalDraftCache;

    public function toArray($request)
    {
        $consignment = $this->consignment;
        $mode = strtolower((string) optional($consignment)->cargo_type);
        $mode = in_array($mode, ['air', 'sea'], true) ? $mode : null;
        $status = $this->portalStatus();

        return [
            'id' => (string) $this->id,
            'customerId' => (string) $this->client_id,
            'trackingNumber' => (string) $this->code,
            // Shared handover secret, returned only on this customer-scoped
            // resource. It must never reach PublicTrackingResource, which is
            // unauthenticated -- anyone holding a tracking number could
            // otherwise collect someone else's parcel.
            'confirmationCode' => $this->otp ? (string) $this->otp : null,
            'consignmentCode' => optional($consignment)->consignment_code,
            'carrier' => optional($consignment)->shipping_line ?: 'New World Cargo',
            'transportMode' => $mode,
            'service' => $this->portalService($mode),
            'packageName' => $this->packageName(),
            'parcelOwner' => (string) ($this->reciver_name ?: ''),
            'origin' => optional($consignment)->source ?: $this->originAddress(),
            'destination' => optional($consignment)->destination ?: $this->getRawOriginal('reciver_address'),
            'etaAt' => $this->isoDate(optional($consignment)->eta),
            'etaLabel' => $this->displayDate(optional($consignment)->eta) ?: 'To be confirmed',
            'status' => $status['status'],
            'statusLabel' => $status['label'],
            'price' => [
                'currency' => $this->portalCurrency(),
                'amountMinor' => max(0, (int) round(((float) ($this->amount_to_be_collected ?: $this->shipping_cost ?: 0)) * 100)),
            ],
            'imageUrl' => null,
            'progress' => $this->portalProgress(),
            'events' => $this->portalEvents(),
            'nextAction' => $this->allowedActions()[0] ?? null,
            'allowedActions' => $this->allowedActions(),
            'revision' => (int) ($this->revision ?: 1),
            'updatedAt' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];
    }

    private function portalService($mode)
    {
        $draft = $this->portalDraft();
        $service = is_array(optional($draft)->payload) ? ($draft->payload['service'] ?? null) : null;
        if (in_array($service, ['local', 'intercity', 'import', 'custom'], true)) {
            return $service;
        }

        return $mode ? 'import' : 'local';
    }

    private function portalCurrency()
    {
        $draft = $this->portalDraft();
        if ($draft && $draft->quote_id) {
            $quoteCurrency = PortalQuote::whereKey($draft->quote_id)->value('currency');
            if ($quoteCurrency) return strtoupper((string) $quoteCurrency);
        }

        $invoiceCurrency = Transxn::where('shipment_id', $this->id)->latest('id')->value('currency');
        return strtoupper((string) ($invoiceCurrency ?: config('customerportalapi.booking_pricing.currency', 'ZMW')));
    }

    private function portalDraft()
    {
        if (!$this->portalDraftResolved) {
            $this->portalDraftCache = PortalShipmentDraft::where('shipment_id', $this->id)->orderByDesc('id')->first();
            $this->portalDraftResolved = true;
        }

        return $this->portalDraftCache;
    }

    private function packageName()
    {
        if ($this->relationLoaded('packages') && $this->packages->isNotEmpty()) {
            $package = $this->packages->first();
            return optional($package->pivot)->description ?: $package->name;
        }

        return 'Customer shipment order';
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
