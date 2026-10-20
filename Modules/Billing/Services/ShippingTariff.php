<?php

namespace Modules\Billing\Services;

use DomainException;
use Illuminate\Support\Facades\DB;

final class ShippingTariff
{
    public function forService(int $serviceId): ?object
    {
        return DB::table('billing_shipping_tariffs')->where('service_id', $serviceId)->first();
    }

    public function calculate(object $tariff, string $quantity, string $currency, string $date, ?string $volume = null): array
    {
        $config = json_decode($tariff->configuration, true, 512, JSON_THROW_ON_ERROR);
        if (!$config['active'] || $date < $config['effective_from'] ||
            (!empty($config['effective_until']) && $date > $config['effective_until'])) {
            throw new DomainException('This shipping rate is not available for the selected date.');
        }
        $measured = InvoiceAmounts::quantity($quantity);
        if ($measured <= 0) throw new DomainException('Enter a cargo quantity greater than zero.');
        if (!empty($config['volume_below'])) {
            if ($volume === null || $volume === '') throw new DomainException('Enter the cargo volume to check this service.');
            $cbm = InvoiceAmounts::quantity($volume);
            if ($cbm <= 0 || $cbm >= InvoiceAmounts::quantity($config['volume_below'])) {
                throw new DomainException('The cargo volume is outside this service limit.');
            }
        }
        $chargeable = max($measured, InvoiceAmounts::quantity($config['minimum']));
        $increment = InvoiceAmounts::quantity($config['rounding']);
        if ($increment > 0) $chargeable = intdiv($chargeable + $increment - 1, $increment) * $increment;
        if ($chargeable > 10000000) throw new DomainException('The chargeable quantity exceeds the supported limit.');
        $tier = null;
        foreach ($config['tiers'] as $candidate) {
            if (InvoiceAmounts::quantity($candidate['from']) <= $chargeable) $tier = $candidate;
        }
        if (!$tier) throw new DomainException('No shipping rate covers this quantity.');
        $rateMinor = InvoiceAmounts::decimal($tier['rate']);
        $sourceMinor = intdiv($chargeable * $rateMinor + 500, 1000);
        if ($sourceMinor <= 0 || $sourceMinor > InvoiceAmounts::MAX_MINOR) throw new DomainException('The calculated freight amount is outside the supported limit.');
        $fx = $this->exchangeRate($config['currency'], $currency);
        $totalMinor = (int) round($sourceMinor * $fx, 0, PHP_ROUND_HALF_UP);
        if ($totalMinor <= 0 || $totalMinor > InvoiceAmounts::MAX_MINOR) throw new DomainException('The converted freight amount is outside the supported limit.');
        return [
            'tariff_id'=>$tariff->id, 'revision'=>$tariff->revision, 'configuration'=>$config,
            'measured_quantity'=>$quantity, 'volume'=>$volume, 'chargeable_quantity'=>$chargeable / 1000,
            'unit'=>$config['unit'], 'tier'=>$tier, 'source_currency'=>$config['currency'],
            'source_minor'=>$sourceMinor, 'currency'=>$currency, 'exchange_rate'=>$fx, 'amount_minor'=>$totalMinor,
            'pricing_date'=>$date,
        ];
    }

    private function exchangeRate(string $from, string $to): float
    {
        if ($from === $to) return 1.0;
        $direct = DB::table('currency_exchange_rates')->where('from_currency',$from)->where('to_currency',$to)->value('exchange_rate');
        if (is_numeric($direct) && $direct > 0) return (float) $direct;
        $inverse = DB::table('currency_exchange_rates')->where('from_currency',$to)->where('to_currency',$from)->value('exchange_rate');
        if (is_numeric($inverse) && $inverse > 0) return 1 / (float) $inverse;
        throw new DomainException('A currency conversion rate is required before this price can be calculated.');
    }
}
