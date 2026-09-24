<?php

namespace Modules\Cargo\Services;

use Modules\Cargo\Entities\ShipmentSetting;
use Modules\Cargo\Entities\Branch;

class MobilePricingSettings
{
    private $storedValues;

    public function advancePricingEnabled(string $service): bool
    {
        return in_array($service, ['local', 'intercity', 'import'], true)
            && $this->value('mobile_pricing_' . $service . '_advance_enabled') === '1';
    }

    public const GLOBAL_FIELDS = [
        'local_base_fee' => 'mobile_pricing_local_base_fee',
        'local_per_km' => 'mobile_pricing_local_per_km',
        'local_per_kg' => 'mobile_pricing_local_per_kg',
        'local_fragile_fee' => 'mobile_pricing_local_fragile_fee',
        'local_scooter_fee' => 'mobile_pricing_local_scooter_fee',
        'local_small_van_fee' => 'mobile_pricing_local_small_van_fee',
        'local_cargo_van_fee' => 'mobile_pricing_local_cargo_van_fee',
        'intercity_base_fee' => 'mobile_pricing_intercity_base_fee',
        'intercity_per_km' => 'mobile_pricing_intercity_per_km',
        'intercity_per_kg' => 'mobile_pricing_intercity_per_kg',
        'intercity_fragile_fee' => 'mobile_pricing_intercity_fragile_fee',
        'intercity_container_fee' => 'mobile_pricing_intercity_container_fee',
        'import_base_fee' => 'mobile_pricing_import_base_fee',
        'import_per_kg' => 'mobile_pricing_import_per_kg',
        'import_fragile_fee' => 'mobile_pricing_import_fragile_fee',
        'import_container_fee' => 'mobile_pricing_import_container_fee',
    ];

    public function globalValues(): array
    {
        $values = [];
        foreach (self::GLOBAL_FIELDS as $field => $key) {
            $values[$field] = $this->value($key);
        }

        return $values;
    }

    public function ratesFor(array $input, array $defaults): array
    {
        $values = $this->globalValues();
        $rates = $defaults;

        $this->override($rates['local'], 'base_fee', $values['local_base_fee']);
        $this->override($rates['local'], 'per_km', $values['local_per_km']);
        $this->override($rates['local'], 'per_kg', $values['local_per_kg']);
        $this->override($rates['local'], 'fragile_fee', $values['local_fragile_fee']);
        $this->override($rates['local']['vehicle_fees'], 'scooter', $values['local_scooter_fee']);
        $this->override($rates['local']['vehicle_fees'], 'small_van', $values['local_small_van_fee']);
        $this->override($rates['local']['vehicle_fees'], 'cargo_van', $values['local_cargo_van_fee']);

        $this->override($rates['intercity'], 'base_fee', $values['intercity_base_fee']);
        $this->override($rates['intercity'], 'per_km', $values['intercity_per_km']);
        $this->override($rates['intercity'], 'per_kg', $values['intercity_per_kg']);
        $this->override($rates['intercity'], 'fragile_fee', $values['intercity_fragile_fee']);
        $this->override($rates['intercity'], 'container_fee', $values['intercity_container_fee']);

        $this->override($rates['import'], 'base_fee', $values['import_base_fee']);
        $this->override($rates['import'], 'per_kg', $values['import_per_kg']);
        $this->override($rates['import'], 'fragile_fee', $values['import_fragile_fee']);
        $this->override($rates['import'], 'container_fee', $values['import_container_fee']);

        $service = (string) ($input['service'] ?? '');
        if ($service === 'intercity') {
            $rates['intercity'] = $this->routeRates('intercity', $input, $rates['intercity'] ?? []);
        }
        if ($service === 'import') {
            $internationalLeg = $input;
            $internationalLeg['destination'] = $input['receivingHub'] ?? $input['destination'] ?? [];
            $rates['import'] = $this->routeRates('import', $internationalLeg, $rates['import'] ?? []);

            if (($input['onwardDelivery'] ?? 'collection') === 'intercity' && isset($input['receivingHub'])) {
                $onwardLeg = $input;
                $onwardLeg['pickup'] = $input['receivingHub'];
                $rates['intercity'] = $this->routeRates('intercity', $onwardLeg, $rates['intercity'] ?? []);
            }
        }

        return $rates;
    }

    public function routeValues(string $service, int $originId, int $destinationId, ?string $mode = null): array
    {
        $fields = $service === 'import'
            ? ['enabled', 'base_fee', 'per_kg', 'fragile_fee', 'container_fee']
            : ['enabled', 'base_fee', 'per_km', 'per_kg', 'fragile_fee', 'container_fee'];
        $values = [];
        foreach ($fields as $field) {
            $values[$field] = $this->value($this->routeKey($service, $originId, $destinationId, $field, $mode));
        }

        return $values;
    }

    public function routeKey(string $service, int $originId, int $destinationId, string $field, ?string $mode = null): string
    {
        $modeSegment = $service === 'import' ? '_' . $mode : '';

        return "mobile_pricing_{$service}_route_{$originId}_{$destinationId}{$modeSegment}_{$field}";
    }

    private function routeRates(string $service, array $input, array $fallback): array
    {
        $originId = (string) ($input['pickup']['branchId'] ?? '');
        $destinationId = (string) ($input['destination']['branchId'] ?? '');
        $mode = $service === 'import' ? (string) ($input['transportMode'] ?? '') : null;

        if (!ctype_digit($originId) || !ctype_digit($destinationId) || $originId === $destinationId) {
            throw new UnsupportedMobilePricingRoute('Select two different supported branches for this service.');
        }
        if (Branch::where('is_archived', 0)->whereIn('id', [(int) $originId, (int) $destinationId])->count() !== 2) {
            throw new UnsupportedMobilePricingRoute('One or more selected branches are no longer available.');
        }
        if ($service === 'import' && !in_array($mode, ['air', 'sea'], true)) {
            throw new UnsupportedMobilePricingRoute('Select Air or Sea for this international route.');
        }

        $route = $this->routeValues($service, (int) $originId, (int) $destinationId, $mode);
        if (($route['enabled'] ?? null) !== '1') {
            throw new UnsupportedMobilePricingRoute('This branch route is not enabled for mobile booking.');
        }

        foreach (['base_fee', 'per_km', 'per_kg', 'fragile_fee', 'container_fee'] as $field) {
            if (array_key_exists($field, $route)) {
                $this->override($fallback, $field, $route[$field]);
            }
        }

        return $fallback;
    }

    private function override(array &$target, string $key, $value): void
    {
        if ($value !== null && $value !== '' && is_numeric($value)) {
            $target[$key] = (float) $value;
        }
    }

    private function value(string $key)
    {
        if ($this->storedValues === null) {
            $this->storedValues = ShipmentSetting::where('key', 'like', 'mobile_pricing_%')->pluck('value', 'key')->all();
        }

        return $this->storedValues[$key] ?? null;
    }
}
