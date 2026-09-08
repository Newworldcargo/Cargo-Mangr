<?php

use App\Models\CurrencyExchangeRate;
use App\Models\User;
use App\Models\Consignment;
use Modules\Cargo\Entities\Shipment;
use Modules\Cargo\Entities\Client;

if (!function_exists('convert_currency')) {

    function current_x_rate() {
        $systemCurrency = app(\Modules\Cargo\Services\BranchAccessService::class)->systemCurrency();
        $rate = CurrencyExchangeRate::where('from_currency', 'USD')->where('to_currency', $systemCurrency)->first();
        return $rate ? $rate->exchange_rate : 0;
    }

    function convert_currency($amount, $from, $to) {
        $amount = (float) ($amount ?? 0);
        $from = strtoupper((string) $from);
        $to = strtoupper((string) $to);
        if ($from === $to || $from === '' || $to === '') {
            return $amount;
        }

        $directRate = CurrencyExchangeRate::where('from_currency', $from)->where('to_currency', $to)->value('exchange_rate');
        if ($directRate && $directRate > 0) {
            return $amount * (float) $directRate;
        }

        $inverseRate = CurrencyExchangeRate::where('from_currency', $to)->where('to_currency', $from)->value('exchange_rate');
        if ($inverseRate && $inverseRate > 0) {
            return $amount / (float) $inverseRate;
        }

        if ($from !== 'USD' && $to !== 'USD') {
            $usdToFrom = CurrencyExchangeRate::where('from_currency', 'USD')->where('to_currency', $from)->value('exchange_rate');
            $usdToTarget = CurrencyExchangeRate::where('from_currency', 'USD')->where('to_currency', $to)->value('exchange_rate');
            if ($usdToFrom && $usdToFrom > 0 && $usdToTarget && $usdToTarget > 0) {
                return ($amount / (float) $usdToFrom) * (float) $usdToTarget;
            }
        }

        return $amount;
    }
    // Convert a stored amount (always in ZMW) to the target currency using the
    // currency_exchange_rates table. Keeps the system settings-driven: no
    // hard-coded country or currency logic. ZMW->X uses the stored rate;
    // X->ZMW uses 1/rate. Same-currency returns the amount unchanged.
    function convert_amount_to_branch_currency($amount, $target) {
        return convert_currency($amount, 'ZMW', $target);
    }

    // Currency symbol for display, driven by the currencies table.
    function currency_symbol_for($code) {
        $cur = \Modules\Currency\Entities\Currency::where('code', strtoupper($code))->first();
        if ($cur && !empty($cur->symbol)) {
            return $cur->symbol;
        }
        return strtoupper($code) === 'USD' ? '$' : '';
    }

    // Currency used for operational shipment amounts. Prefer the logged-in
    // user's assigned/owned branch, then the shipment branch, then the
    // Currency module's configured system default.
    function shipment_display_currency($shipment = null, $user = null) {
        $user = $user ?: auth()->user();
        $shipmentBranch = null;
        if ($shipment && $shipment->branch_id) {
            $shipmentBranch = $shipment->relationLoaded('branch') ? $shipment->branch : \Modules\Cargo\Entities\Branch::find($shipment->branch_id);
        }

        return app(\Modules\Cargo\Services\BranchAccessService::class)->currencyFor($user, $shipmentBranch);
    }

    function convert_usd_to_display_currency($amount, $currency) {
        $currency = strtoupper($currency ?: 'ZMW');
        return convert_currency($amount, 'USD', $currency);
    }

    function format_shipment_price($amount, $shipment = null, $includeCode = true) {
        $currency = shipment_display_currency($shipment);
        $displayAmount = convert_usd_to_display_currency($amount, $currency);
        return currency_symbol_for($currency) . number_format($displayAmount, 2) . ($includeCode ? ' ' . $currency : '');
    }

    function current_branch_currency_symbol() {
        return currency_symbol_for(shipment_display_currency());
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
