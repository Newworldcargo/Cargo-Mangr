<?php

namespace Modules\Cargo\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Modules\Cargo\Entities\Branch;
use Modules\Cargo\Entities\Client;
use Modules\Cargo\Entities\Country;
use Modules\Cargo\Entities\OnlineBookingRequest;
use Modules\Cargo\Entities\Package;
use Modules\Cargo\Entities\PackageShipment;
use Modules\Cargo\Entities\Shipment;
use Modules\Cargo\Entities\ShipmentSetting;
use Modules\Cargo\Entities\State;
use Modules\CustomerPortalApi\Models\PortalQuote;
use Modules\CustomerPortalApi\Models\PortalShipmentDraft;

class OnlineBookingService
{
    public function createFromLegacyApi(Request $request, User $user): OnlineBookingRequest
    {
        $client = Client::where('user_id', $user->id)->firstOrFail();
        $shipment = (array) $request->input('Shipment', []);
        $cargoRows = collect((array) $request->input('Package', []))->map(fn ($row) => [
            'name' => (string) ($row['description'] ?? 'Customer cargo'),
            'quantity' => max(1, (int) ($row['qty'] ?? 1)),
            'weight' => (float) ($row['weight'] ?? 0),
            'amount' => (float) ($row['amount'] ?? 0),
        ])->values()->all();
        $branch = Branch::where('is_archived', 0)->findOrFail($shipment['branch_id'] ?? null);
        $fromCountry = (int) ($shipment['from_country_id'] ?? 0);
        $toCountry = (int) ($shipment['to_country_id'] ?? 0);
        $service = $fromCountry && $toCountry && $fromCountry !== $toCountry ? 'import'
            : ((int) ($shipment['from_state_id'] ?? 0) && (int) ($shipment['to_state_id'] ?? 0)
                && (int) $shipment['from_state_id'] !== (int) $shipment['to_state_id'] ? 'intercity' : 'local');
        $weight = collect($cargoRows)->sum(fn ($row) => (float) ($row['weight'] ?? 0));

        $booking = OnlineBookingRequest::create([
            'reference' => 'PENDING-' . Str::random(20),
            'client_id' => $client->id,
            'branch_id' => $branch->id,
            'service' => $service,
            'transport_mode' => $service === 'import' ? $this->normaliseMode($shipment['transport_mode'] ?? null) : null,
            'status' => 'pending',
            'pickup_address' => (string) ($shipment['client_address'] ?? 'Not provided'),
            'destination_address' => (string) ($shipment['reciver_address'] ?? 'Not provided'),
            'recipient_name' => (string) ($shipment['reciver_name'] ?? 'Customer'),
            'recipient_phone' => (string) ($shipment['reciver_phone'] ?? 'Not provided'),
            'sender_name' => $client->name,
            'sender_phone' => $shipment['client_phone'] ?? $client->responsible_mobile,
            'schedule' => $shipment['shipping_date'] ?? null,
            'instructions' => $shipment['instructions'] ?? null,
            'total_weight' => $weight > 0 ? $weight : max(1, count($cargoRows)),
            'quoted_amount' => (float) ($shipment['amount_to_be_collected'] ?? 0),
            'currency' => strtoupper((string) ($shipment['currency'] ?? config('customerportalapi.booking_pricing.currency', 'ZMW'))),
            'payload' => ['source' => 'legacy_mobile_api', 'shipment' => $shipment, 'cargoRows' => $cargoRows],
            'submitted_at' => now(),
        ]);
        $booking->reference = 'OBR' . str_pad((string) $booking->id, 7, '0', STR_PAD_LEFT);
        $booking->save();

        return $booking;
    }

    public function createFromPortalDraft(
        PortalShipmentDraft $draft,
        Client $client,
        Branch $branch,
        array $payload,
        array $cargoRows,
        ?PortalQuote $quote
    ): OnlineBookingRequest {
        return DB::transaction(function () use ($draft, $client, $branch, $payload, $cargoRows, $quote) {
            $draft = PortalShipmentDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            if (!in_array($draft->status, ['draft', 'quoted'], true) || $draft->online_booking_id) {
                throw new \DomainException('This draft has already been submitted.');
            }
            $form = (array) ($payload['form'] ?? []);
            $service = $this->normaliseService((string) ($payload['service'] ?? 'custom'));
            $weight = collect($cargoRows)->sum(fn ($row) => (float) ($row['weight'] ?? 0));
            $amount = $quote ? ((int) $quote->amount_minor / 100) : 0;

            $booking = OnlineBookingRequest::create([
                'reference' => 'PENDING-' . Str::random(20),
                'client_id' => $client->id,
                'branch_id' => $branch->id,
                'service' => $service,
                'transport_mode' => $service === 'import' ? $this->normaliseMode($form['transportMode'] ?? $quote?->transport_mode) : null,
                'status' => 'pending',
                'pickup_address' => (string) ($form['pickup'] ?? 'Not provided'),
                'destination_address' => (string) ($form['destination'] ?? 'Not provided'),
                'recipient_name' => (string) ($form['recipient'] ?? 'Customer'),
                'recipient_phone' => (string) ($form['phone'] ?? 'Not provided'),
                'sender_name' => $form['sender'] ?? $client->name,
                'sender_phone' => $form['senderPhone'] ?? $client->responsible_mobile,
                'schedule' => $form['schedule'] ?? null,
                'instructions' => $form['instructions'] ?? null,
                'total_weight' => $weight > 0 ? $weight : max(1, count($cargoRows)),
                'quoted_amount' => $amount,
                'currency' => strtoupper((string) ($quote?->currency ?: config('customerportalapi.booking_pricing.currency', 'ZMW'))),
                'payload' => array_merge($payload, ['cargoRows' => $cargoRows]),
                'submitted_at' => now(),
            ]);

            $booking->reference = 'OBR' . str_pad((string) $booking->id, 7, '0', STR_PAD_LEFT);
            $booking->save();

            $draft->status = 'submitted';
            $draft->quote_id = $quote?->id;
            $draft->online_booking_id = $booking->id;
            $draft->revision = ((int) ($draft->revision ?: 1)) + 1;
            $draft->save();

            return $booking;
        });
    }

    public function convertToShipment(OnlineBookingRequest $booking, User $processor): Shipment
    {
        return DB::transaction(function () use ($booking, $processor) {
            $booking = OnlineBookingRequest::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($booking->shipment_id) {
                return Shipment::findOrFail($booking->shipment_id);
            }
            if ($booking->status !== 'pending') {
                throw new \DomainException('Only pending bookings can be converted into shipments.');
            }

            $origin = $this->locationProfile($booking->branch->name . ' ' . $booking->branch->address);
            $destination = $this->locationProfile($booking->destination_address);
            $shipment = Shipment::create([
                'code' => '-',
                'status_id' => Shipment::REQUESTED_STATUS,
                'type' => Shipment::PICKUP,
                'branch_id' => $booking->branch_id,
                'shipping_date' => now()->toDateString(),
                'client_status' => Shipment::CLIENT_STATUS_CREATED,
                'client_id' => $booking->client_id,
                'client_phone' => $booking->sender_phone ?: $booking->client?->responsible_mobile,
                'client_address' => $booking->pickup_address,
                'reciver_name' => $booking->recipient_name,
                'reciver_phone' => $booking->recipient_phone,
                'reciver_address' => $booking->destination_address,
                'from_country_id' => $origin['country']->id,
                'from_state_id' => $origin['state']->id,
                'to_country_id' => $destination['country']->id,
                'to_state_id' => $destination['state']->id,
                'payment_type' => Shipment::POSTPAID,
                'order_id' => $booking->reference,
                'booking_source' => 'online_booking',
                'total_weight' => $booking->total_weight,
                'shipping_cost' => $booking->quoted_amount,
                'amount_to_be_collected' => $booking->quoted_amount,
                'attachments_before_shipping' => $this->evidenceJson($booking->payload),
            ]);

            $width = max(5, (int) (ShipmentSetting::getVal('shipment_code_count') ?: 5));
            $shipment->barcode = str_pad((string) $shipment->id, $width, '0', STR_PAD_LEFT);
            $shipment->code = (string) (ShipmentSetting::getVal('shipment_prefix') ?: 'NWC') . $shipment->barcode;
            $shipment->save();
            $this->createPackageRows($shipment, (array) ($booking->payload['cargoRows'] ?? []));

            $booking->update([
                'status' => 'accepted',
                'shipment_id' => $shipment->id,
                'processed_by' => $processor->id,
                'processed_at' => now(),
            ]);
            PortalShipmentDraft::where('online_booking_id', $booking->id)->update(['shipment_id' => $shipment->id]);

            return $shipment;
        });
    }

    private function normaliseService(string $service): string
    {
        return in_array($service, ['local', 'intercity', 'import', 'custom'], true) ? $service : 'custom';
    }

    private function normaliseMode($mode): ?string
    {
        $mode = strtolower(trim((string) $mode));
        return in_array($mode, ['air', 'sea'], true) ? $mode : null;
    }

    private function locationProfile(string $text): array
    {
        $value = strtolower($text);
        $countryName = str_contains($value, 'china') ? 'China' : (str_contains($value, 'zimbabwe') ? 'Zimbabwe' : 'Zambia');
        $country = Country::where('covered', 1)->where('name', $countryName)->firstOrFail();
        $preferredState = $countryName === 'China' ? 'Guangdong'
            : ($countryName === 'Zambia' && str_contains($value, 'kitwe') ? 'Copperbelt'
                : ($countryName === 'Zambia' ? 'Lusaka' : null));
        $stateQuery = State::where('covered', 1)->where('country_id', $country->id);
        $state = $preferredState ? (clone $stateQuery)->where('name', 'like', '%' . $preferredState . '%')->first() : null;

        return ['country' => $country, 'state' => $state ?: $stateQuery->orderBy('id')->firstOrFail()];
    }

    private function createPackageRows(Shipment $shipment, array $rows): void
    {
        $packageId = Package::orderBy('id')->value('id');
        if (!$packageId) return;

        foreach ($rows as $row) {
            PackageShipment::create([
                'package_id' => $packageId,
                'shipment_id' => $shipment->id,
                'description' => (string) ($row['name'] ?? 'Customer cargo'),
                'weight' => ($row['weight'] ?? 0) ?: null,
                'length' => 1,
                'width' => 1,
                'height' => 1,
                'qty' => max(1, (int) ($row['quantity'] ?? 1)),
            ]);
        }
    }

    private function evidenceJson(array $payload): ?string
    {
        $draft = (array) ($payload['draft'] ?? []);
        $items = collect(array_merge((array) ($draft['cargoPhotos'] ?? []), [$draft['supportingDocument'] ?? null]))
            ->filter(fn ($item) => is_array($item) && !empty($item['fileId']))
            ->map(fn ($item) => [
                'file_id' => (string) $item['fileId'],
                'name' => (string) ($item['name'] ?? 'Booking evidence'),
                'kind' => (string) ($item['kind'] ?? 'document'),
            ])->values()->all();

        return $items ? json_encode($items) : null;
    }
}
