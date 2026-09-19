<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Modules\Cargo\Entities\ShipmentSetting;
use Modules\Cargo\Services\MobilePricingSettings;
use Modules\Cargo\Services\UnsupportedMobilePricingRoute;
use Modules\CustomerPortalApi\Models\PortalQuote;
use Modules\CustomerPortalApi\Services\Pricing\MobileBookingQuoteCalculator;
use Modules\CustomerPortalApi\Services\Pricing\MobileBookingQuoteSigner;

class MobileBookingQuoteController extends PortalController
{
    public function store(Request $request, MobileBookingQuoteCalculator $calculator, MobileBookingQuoteSigner $signer, MobilePricingSettings $pricingSettings)
    {
        $validator = Validator::make($request->all(), [
            'service' => ['required', 'in:local,intercity,import,custom'],
            'bookingType' => ['required', 'in:local_delivery,city_to_city,international_import,custom_request'],
            'pickup' => ['required', 'array'],
            'pickup.city' => ['nullable', 'string', 'max:120'],
            'pickup.area' => ['nullable', 'string', 'max:255'],
            'pickup.branchId' => ['nullable', 'string', 'max:60'],
            'pickup.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'pickup.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'destination' => ['required', 'array'],
            'destination.city' => ['nullable', 'string', 'max:120'],
            'destination.area' => ['nullable', 'string', 'max:255'],
            'destination.branchId' => ['nullable', 'string', 'max:60'],
            'destination.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'destination.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'receivingHub' => ['nullable', 'array'],
            'receivingHub.city' => ['nullable', 'string', 'max:120'],
            'receivingHub.area' => ['nullable', 'string', 'max:255'],
            'receivingHub.branchId' => ['nullable', 'string', 'max:60'],
            'receivingHub.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'receivingHub.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'distanceKm' => ['nullable', 'numeric', 'min:0', 'max:25000'],
            'vehicleType' => ['nullable', 'in:scooter,small_van,cargo_van'],
            'transportMode' => ['nullable', 'in:air,sea'],
            'onwardDelivery' => ['nullable', 'in:collection,local,intercity'],
            'onwardVehicleType' => ['nullable', 'in:scooter,small_van,cargo_van'],
            'fulfilment' => ['nullable', 'string', 'max:60'],
            'schedule' => ['nullable', 'string', 'max:60'],
            'scheduledAt' => ['required_if:schedule,scheduled', 'nullable', 'date', 'after:now'],
            'cargo' => ['required', 'array'],
            'cargo.items' => ['sometimes', 'array', 'max:100'],
            'cargo.totalWeight' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'cargo.declaredValue' => ['nullable', 'numeric', 'min:0'],
            'cargo.fragile' => ['required', 'boolean'],
            'cargo.packageType' => ['nullable', 'in:standard,container'],
        ]);

        if ($validator->fails()) {
            return $this->problem($request, 'VALIDATION_FAILED', 'A valid booking quote request is required.', 422, $validator->errors()->toArray());
        }

        $input = $validator->validated();
        if (!$this->bookingTypeMatchesService($input['service'], $input['bookingType'])) {
            return $this->problem($request, 'VALIDATION_FAILED', 'The booking type does not match the selected service.', 422, [
                'bookingType' => ['Select the correct booking type for this service.'],
            ]);
        }

        // Existing non-USD rates must be reviewed by an administrator, never relabelled.
        $currency = strtoupper((string) (ShipmentSetting::getVal('mobile_pricing_currency') ?: config('customerportalapi.booking_pricing.currency', 'USD')));
        if ($currency !== 'USD') {
            return $this->problem($request, 'PRICING_NOT_CONFIGURED', 'USD pricing is not configured. Review and save mobile rates in USD.', 503, [], false);
        }

        try {
            $calculation = $calculator->calculate($input, $pricingSettings->ratesFor($input, $this->pricingRates()));
        } catch (UnsupportedMobilePricingRoute $exception) {
            return $this->problem($request, 'UNSUPPORTED_ROUTE', $exception->getMessage(), 422, [], false);
        } catch (InvalidArgumentException $exception) {
            return $this->problem($request, 'PRICING_NOT_CONFIGURED', 'Pricing is not available for this service in the current environment.', 503, [], true);
        }

        $client = $this->customerContext->requireClient();

        $expiresAt = now()->addMinutes(max(1, (int) config('customerportalapi.booking_pricing.quote_minutes', 15)));
        $quote = PortalQuote::create([
            'client_id' => $client->id,
            'transport_mode' => (string) ($input['transportMode'] ?? $input['service']),
            'delivery_option' => (string) ($input['fulfilment'] ?? $input['vehicleType'] ?? 'standard'),
            'snapshot' => ['request' => $input, 'calculation' => $calculation],
            'currency' => $currency,
            'amount_minor' => max(0, (int) round($calculation['total'] * 100)),
            'assumptions' => ['pricingStatus' => $calculation['pricingStatus']],
            'status' => 'active',
            'expires_at' => $expiresAt,
            'revision' => 1,
        ]);

        $quotePayload = [
            'quoteId' => (string) $quote->id,
            'customerId' => (string) $client->id,
            'service' => $input['service'],
            'bookingType' => $input['bookingType'],
            'currency' => $currency,
            'total' => (float) $calculation['total'],
            'distanceKm' => $calculation['distanceKm'],
            'expiresAt' => $expiresAt->toIso8601String(),
            'revision' => 1,
            'requestHash' => $signer->requestHash($input),
        ];

        return $this->success($request, [
            'source' => 'server',
            'quoteId' => (string) $quote->id,
            'currency' => $currency,
            'total' => (float) $calculation['total'],
            'formattedTotal' => $this->formattedTotal((float) $calculation['total'], $currency),
            'distanceKm' => $calculation['distanceKm'],
            'estimatedDurationMinutes' => $calculation['estimatedDurationMinutes'],
            'expiresAt' => $expiresAt->toIso8601String(),
            'quotePayload' => $quotePayload,
            'quoteSignature' => $signer->sign($quotePayload),
            'breakdown' => [
                'pricingStatus' => $calculation['pricingStatus'],
                'charges' => $calculation['breakdown'],
            ],
        ], 201);
    }

    private function pricingRates(): array
    {
        // Legacy shipment fees may use another currency. Only explicitly configured
        // mobile rates can be used for USD quotes; admin mobile settings override these.
        return (array) config('customerportalapi.booking_pricing.services', []);
    }

    private function bookingTypeMatchesService(string $service, string $bookingType): bool
    {
        return [
            'local' => 'local_delivery',
            'intercity' => 'city_to_city',
            'import' => 'international_import',
            'custom' => 'custom_request',
        ][$service] === $bookingType;
    }

    private function formattedTotal(float $total, string $currency): string
    {
        return $currency === 'ZMW'
            ? 'K ' . number_format($total, 2)
            : $currency . ' ' . number_format($total, 2);
    }
}
