<?php

namespace Modules\Billing\Services;

use Illuminate\Support\Facades\Validator;

final class ShipmentDetails
{
    public static function validated(array $input): ?array
    {
        $mode = $input['document_mode'] ?? (empty($input['shipment_details']) ? 'plain' : 'shipment');
        Validator::make(['mode' => $mode], ['mode' => 'required|in:plain,shipment'])->validate();
        if ($mode === 'plain') return null;
        $rules = [
            'shipment_type' => 'required|in:pickup,drop_off',
            'shipping_date' => 'required|date_format:Y-m-d', 'collection_time' => 'nullable|date_format:H:i',
            'sender_phone' => 'required|string|max:40', 'sender_address' => 'required|string|max:500',
            'receiver_name' => 'required|string|max:160', 'receiver_phone' => 'required|string|max:40',
            'receiver_address' => 'required|string|max:500',
            'from_country' => 'required|string|max:100', 'to_country' => 'required|string|max:100',
            'from_region' => 'nullable|string|max:100', 'to_region' => 'nullable|string|max:100',
            'from_area' => 'nullable|string|max:100', 'to_area' => 'nullable|string|max:100',
            'payment_type' => 'required|in:postpaid,prepaid',
            'payment_method' => 'required|in:cash,bank_transfer,mobile_money,card',
            'order_id' => 'nullable|string|max:120', 'delivery_time' => 'nullable|string|max:120',
            'packages' => 'required|array|min:1|max:100',
            'packages.*.type' => 'required|string|max:80', 'packages.*.description' => 'required|string|max:500',
            'packages.*.quantity' => 'required|integer|min:1|max:10000',
            'packages.*.weight' => 'required|numeric|min:0.001|max:100000',
        ];
        foreach (['length', 'width', 'height'] as $dimension) $rules['packages.*.'.$dimension] = 'nullable|numeric|min:0.01|max:100000';
        $prefixed = [];
        foreach ($rules as $key => $rule) $prefixed['shipment_details.'.$key] = $rule;
        $validated = Validator::make($input, $prefixed)->validate()['shipment_details'];
        // Whitelist nested fields as well: totals and source shipment IDs cannot be supplied by the browser.
        $data = array_intersect_key($validated, array_flip(array_filter(array_keys($rules), fn ($key) => !str_contains($key, '.'))));
        $weight = 0;
        $data['packages'] = array_map(function ($package) use (&$weight) {
            $package = array_intersect_key($package, array_flip(['type', 'description', 'quantity', 'weight', 'length', 'width', 'height']));
            $weight += (int) $package['quantity'] * (int) round((float) $package['weight'] * 1000);
            return $package;
        }, $validated['packages']);
        $data['total_weight_kg'] = number_format($weight / 1000, 3, '.', '');
        return $data;
    }
}
