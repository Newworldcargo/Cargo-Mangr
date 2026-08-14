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
    // Convert a stored amount (always in ZMW) to the target currency using the
    // currency_exchange_rates table. Keeps the system settings-driven: no
    // hard-coded country or currency logic. ZMW->X uses the stored rate;
    // X->ZMW uses 1/rate. Same-currency returns the amount unchanged.
    function convert_amount_to_branch_currency($amount, $target) {
        if (!$target || strtoupper($target) === 'ZMW') {
            return (float) $amount;
        }
        $rate = \App\Models\CurrencyExchangeRate::where('from_currency', 'ZMW')
            ->where('to_currency', strtoupper($target))
            ->first();
        if (!$rate) {
            // try the inverse row
            $rate = \App\Models\CurrencyExchangeRate::where('from_currency', strtoupper($target))
                ->where('to_currency', 'ZMW')
                ->first();
            if ($rate && $rate->exchange_rate > 0) {
                return (float) $amount / $rate->exchange_rate;
            }
            return (float) $amount;
        }
        if ($rate->exchange_rate <= 0) {
            return (float) $amount;
        }
        return (float) ($amount / $rate->exchange_rate);
    }

    // Currency symbol for display, driven by the currencies table.
    function currency_symbol_for($code) {
        $cur = \Modules\Currency\Entities\Currency::where('code', strtoupper($code))->first();
        if ($cur && !empty($cur->symbol)) {
            return $cur->symbol;
        }
        if (strtoupper($code) === 'ZMW') {
            return 'K';
        }
        return '$';
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