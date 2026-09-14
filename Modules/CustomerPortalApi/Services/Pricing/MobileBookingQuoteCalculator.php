<?php

namespace Modules\CustomerPortalApi\Services\Pricing;

use InvalidArgumentException;

class MobileBookingQuoteCalculator
{
    public function calculate(array $request, array $rates): array
    {
        $service = (string) ($request['service'] ?? '');
        $pickup = (array) ($request['pickup'] ?? []);
        $destination = (array) ($request['destination'] ?? []);
        $distanceKm = $this->distanceKm($pickup, $destination);
        $cargo = (array) ($request['cargo'] ?? []);
        $weightKg = max(0, (float) ($cargo['totalWeight'] ?? 0));

        if ($service === 'custom') {
            return [
                'total' => 0.0,
                'distanceKm' => $distanceKm,
                'estimatedDurationMinutes' => null,
                'pricingStatus' => 'manual_review',
                'breakdown' => [
                    ['code' => 'manual_review', 'label' => 'Manual pricing review', 'amount' => 0.0],
                ],
            ];
        }

        $serviceRates = (array) ($rates[$service] ?? []);
        $baseFee = $this->requiredRate($serviceRates, 'base_fee');
        $perKm = $this->requiredRate($serviceRates, 'per_km', true);
        $perKg = $this->requiredRate($serviceRates, 'per_kg', true);
        $breakdown = [];

        $this->addCharge($breakdown, 'base_fee', 'Base service fee', $baseFee);
        $this->addCharge($breakdown, 'distance', 'Distance charge', $distanceKm * $perKm);
        $this->addCharge($breakdown, 'weight', 'Cargo weight charge', $weightKg * $perKg);

        if ($service === 'local') {
            $vehicle = (string) ($request['vehicleType'] ?? 'scooter');
            $vehicleFees = (array) ($serviceRates['vehicle_fees'] ?? []);
            $this->addCharge($breakdown, 'vehicle', 'Vehicle adjustment', (float) ($vehicleFees[$vehicle] ?? 0));
        }

        if ($service === 'import') {
            $mode = (string) ($request['transportMode'] ?? '');
            $modeFees = (array) ($serviceRates['transport_fees'] ?? []);
            $this->addCharge($breakdown, 'transport', ucfirst($mode ?: 'international') . ' freight adjustment', (float) ($modeFees[$mode] ?? 0));
        }

        $fulfilment = (string) ($request['fulfilment'] ?? '');
        $fulfilmentFees = (array) ($serviceRates['fulfilment_fees'] ?? []);
        $this->addCharge($breakdown, 'fulfilment', 'Delivery option adjustment', (float) ($fulfilmentFees[$fulfilment] ?? 0));

        $schedule = (string) ($request['schedule'] ?? '');
        $scheduleFees = (array) ($serviceRates['schedule_fees'] ?? []);
        $this->addCharge($breakdown, 'schedule', 'Schedule adjustment', (float) ($scheduleFees[$schedule] ?? 0));

        if ((bool) ($cargo['fragile'] ?? false)) {
            $this->addCharge($breakdown, 'fragile', 'Fragile cargo handling', (float) ($serviceRates['fragile_fee'] ?? 0));
        }

        if (($cargo['packageType'] ?? 'standard') === 'container') {
            $this->addCharge($breakdown, 'container', 'Container handling', (float) ($serviceRates['container_fee'] ?? 0));
        }

        $total = round(array_sum(array_column($breakdown, 'amount')), 2);

        return [
            'total' => $total,
            'distanceKm' => $distanceKm,
            'estimatedDurationMinutes' => $distanceKm > 0 ? max(15, (int) round($distanceKm * (float) ($serviceRates['minutes_per_km'] ?? 4))) : null,
            'pricingStatus' => 'priced',
            'breakdown' => $breakdown,
        ];
    }

    private function distanceKm(array $pickup, array $destination): float
    {
        foreach ([$pickup, $destination] as $point) {
            if (!isset($point['latitude'], $point['longitude'])) {
                return 0.0;
            }
        }

        $earthKm = 6371;
        $startLat = deg2rad((float) $pickup['latitude']);
        $endLat = deg2rad((float) $destination['latitude']);
        $deltaLat = $endLat - $startLat;
        $deltaLon = deg2rad((float) $destination['longitude'] - (float) $pickup['longitude']);
        $a = sin($deltaLat / 2) ** 2 + cos($startLat) * cos($endLat) * sin($deltaLon / 2) ** 2;

        return round($earthKm * 2 * atan2(sqrt($a), sqrt(1 - $a)), 1);
    }

    private function requiredRate(array $rates, string $key, bool $zeroAllowed = false): float
    {
        $value = $rates[$key] ?? null;
        if (!is_numeric($value) || (!$zeroAllowed && (float) $value <= 0) || (float) $value < 0) {
            throw new InvalidArgumentException("Booking pricing rate {$key} is not configured.");
        }

        return (float) $value;
    }

    private function addCharge(array &$breakdown, string $code, string $label, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $breakdown[] = [
            'code' => $code,
            'label' => $label,
            'amount' => round($amount, 2),
        ];
    }
}
