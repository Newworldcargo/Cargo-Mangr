<?php

use App\Models\CurrencyExchangeRate;
use App\Models\User;
use App\Models\Consignment;
use Modules\Cargo\Entities\Shipment;
use Modules\Cargo\Entities\Client;

if (!function_exists('convert_currency')) {

    function current_x_rate() {
        $rate = CurrencyExchangeRate::first();
        return $rate ? $rate->exchange_rate : 0;
    }

    function convert_currency($amount, $from, $to) {
        $rate = CurrencyExchangeRate::first();
        if ($rate && $rate->exchange_rate) {
            return $amount * $rate->exchange_rate;
        }

        return $amount; // fallback if no rate found
    }

function customer_numbers($consignment_id)
{
    $shipments = Shipment::where('consignment_id', $consignment_id)->get();
    $numbers = [];

    foreach ($shipments as $shipment) {
        if (!empty($shipment->client_phone)) {
            $raw = preg_replace('/\D/', '', trim($shipment->client_phone)); // remove non-numeric

            // Ensure it starts with 260 and is of valid length (11 or more digits)
            if (strlen($raw) >= 9) {
                if (strpos($raw, '260') !== 0) {
                    // If it doesn't start with 260, prepend it
                    $raw = '260' . substr($raw, -9); // Keep last 9 digits
                }
                $numbers[] = '+' . $raw; // Prepend +
            }
        }
    }

    return array_unique($numbers);
}




}