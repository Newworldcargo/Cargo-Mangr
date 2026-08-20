<?php

namespace Modules\Rates\Services;

use App\Models\CurrencyExchangeRate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Central exchange-rate syncing service for the Rates module.
 *
 * All modules in the system consume rates from the canonical
 * currency_exchange_rates table (via HandlesCurrencyExchange, app/helpers,
 * and TransxnController), so this service is the single writer.
 *
 * Sources are registry-driven and keyless:
 *  - floatrates     : https://www.floatrates.com/daily/usd.xml (primary, twice-daily)
 *  - openexchangerates : https://open.er-api.com/v6/latest/USD (fallback, same data family)
 *
 * A new source can be added by registering a fetcher in $sources.
 */
class RatesService
{
    /**
     * Registry of rate sources. Each entry has a fetcher callable that
     * returns an associative array of symbol => rate (base USD) or null.
     */
    protected array $sources = [
        'floatrates' => [
            'url' => 'https://www.floatrates.com/daily/usd.xml',
            'timeout' => 15,
        ],
        'openexchangerates' => [
            'url' => 'https://open.er-api.com/v6/latest/USD',
            'timeout' => 10,
        ],
    ];

    /**
     * Currency pairs the application stores (base always USD).
     * Extend this list as new branch currencies are configured.
     */
    protected array $targetCurrencies = [
        'ZMW', 'AED', 'AUD', 'CAD', 'CHF', 'CNY', 'EUR', 'GBP',
        'HKD', 'IDR', 'INR', 'KES', 'MWK', 'NGN', 'TZS', 'UGX',
        'ZAR', 'ZWD',
    ];

    /**
     * Sync rates into the currency_exchange_rates table.
     *
     * @param string|null $forceSource Force one source (floatrates|openexchangerates)
     * @return array{source: ?string, updated: int, skipped: int, errors: array}
     */
    public function sync(?string $forceSource = null): array
    {
        $order = $forceSource ? [$forceSource] : array_keys($this->sources);
        $rates = null;
        $usedSource = null;

        foreach ($order as $source) {
            if (!isset($this->sources[$source])) {
                continue;
            }
            $rates = $this->fetch($source);
            if ($rates) {
                $usedSource = $source;
                break;
            }
        }

        if (!$rates) {
            return ['source' => null, 'updated' => 0, 'skipped' => 0, 'errors' => ['All rate sources failed']];
        }

        $updated = 0;
        $skipped = 0;
        foreach ($this->targetCurrencies as $target) {
            if (!isset($rates[$target])) {
                continue;
            }
            $rate = (float) $rates[$target];
            if ($rate <= 0) {
                continue;
            }

            $existing = CurrencyExchangeRate::where('from_currency', 'USD')
                ->where('to_currency', $target)
                ->first();
            // The USD->ZMW row (id 1) carries the manually locked bank rate
            // (Access Bank Zambia retail sell, ~19.07). Automated market feeds
            // must never overwrite it; keep $skipped honest by counting it.
            if ($existing && (int) $existing->id === 1) {
                $skipped++;
                continue;
            }
            if ($existing && (float) $existing->exchange_rate === $rate) {
                $skipped++;
                continue;
            }

            if ($existing) {
                $existing->update(['exchange_rate' => $rate]);
            } else {
                CurrencyExchangeRate::create([
                    'from_currency' => 'USD',
                    'to_currency' => $target,
                    'exchange_rate' => $rate,
                ]);
            }
            $updated++;
        }

        Cache::put('rates:last_sync_source', $usedSource, 3600 * 24);
        Cache::put('rates:last_sync_at', now()->toDateTimeString(), 3600 * 24);

        return ['source' => $usedSource, 'updated' => $updated, 'skipped' => $skipped, 'errors' => []];
    }

    /**
     * Fetch rates from a single source.
     *
     * @return array|null symbol => rate (base USD), or null on failure
     */
    protected function fetch(string $source): ?array
    {
        $config = $this->sources[$source];

        try {
            $response = Http::timeout($config['timeout'])->get($config['url']);

            if (!$response->successful()) {
                Log::channel('daily')->info("rates:sync source {$source} HTTP {$response->status()}");
                return null;
            }

            return match ($source) {
                'floatrates' => $this->parseFloatRatesXml($response->body()),
                'openexchangerates' => $this->parseOpenErApi($response->json()),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::channel('daily')->info("rates:sync source {$source} failed: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Parse the FloatRates daily XML (USD base) into symbol => rate pairs.
     */
    protected function parseFloatRatesXml(string $xml): ?array
    {
        try {
            $parsed = new \SimpleXMLElement($xml);
        } catch (\Throwable $e) {
            return null;
        }

        $rates = [];
        foreach ($parsed->item ?? [] as $item) {
            // FloatRates items store the currency code as direct text:
            // <targetCurrency>AED</targetCurrency>, not nested elements.
            $code = trim((string) ($item->targetCurrency ?? ''));
            $rate = trim((string) ($item->exchangeRate ?? ''));
            if ($code && $rate && is_numeric($rate)) {
                $rates[$code] = (float) $rate;
            }
        }

        return $rates ?: null;
    }

    /**
     * Parse the open.er-api.com JSON response.
     */
    protected function parseOpenErApi(?array $json): ?array
    {
        if (!$json || ($json['result'] ?? '') !== 'success' || empty($json['rates'])) {
            return null;
        }

        return array_map('floatval', $json['rates']);
    }

    /**
     * Available sources.
     */
    public function availableSources(): array
    {
        return array_keys($this->sources);
    }
}
