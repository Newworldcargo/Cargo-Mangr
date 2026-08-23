<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SyncExchangeRatesCommand
 *
 * Automatically refreshes the currency_exchange_rates table from free,
 * keyless public rate feeds so nobody has to check the Bank of Zambia
 * website manually every day.
 *
 * Primary source: FloatRates daily XML feed (free, no API key,
 * updated twice a day, mid-market rates).
 * Fallback source: open.er-api.com free endpoint (exchangerate-api.com
 * free tier, no API key, updated daily).
 *
 * Usage:
 *   php artisan rates:sync                 # sync from primary, fallback on failure
 *   php artisan rates:sync --source=floatrates
 *   php artisan rates:sync --source=openexchangerates
 */
class SyncExchangeRatesCommand extends Command
{
    protected $signature = 'rates:sync {--source= : force a specific source (floatrates|openexchangerates)}';

    protected $description = 'Sync currency_exchange_rates from free public exchange-rate feeds';

    /** Base currency all feeds publish rates against (USD). */
    private const BASE_CURRENCY = 'USD';

    /** Feeds in preference order. */
    private const SOURCES = [
        'floatrates' => 'https://www.floatrates.com/daily/usd.xml',
        'openexchangerates' => 'https://open.er-api.com/v6/latest/USD',
    ];

    public function handle(): int
    {
        $source = $this->option('source');

        if ($source && !isset(self::SOURCES[$source])) {
            $this->error("Unknown source: {$source}. Use floatrates or openexchangerates.");
            return 1;
        }

        $sources = $source
            ? [$source => self::SOURCES[$source]]
            : self::SOURCES;

        foreach ($sources as $name => $url) {
            $rates = $this->fetchRates($name, $url);
            if ($rates === null) {
                $this->warn("Source {$name} ({$url}) failed, trying next.");
                continue;
            }

            $updated = 0;
            foreach ($rates as $targetCurrency => $rate) {
                $row = DB::table('currency_exchange_rates')
                    ->where('from_currency', self::BASE_CURRENCY)
                    ->where('to_currency', $targetCurrency)
                    ->first();

                if ($row) {
                    DB::table('currency_exchange_rates')
                        ->where('id', $row->id)
                        ->update(['exchange_rate' => $rate, 'updated_at' => now()]);
                } else {
                    DB::table('currency_exchange_rates')->insert([
                        'from_currency' => self::BASE_CURRENCY,
                        'to_currency' => $targetCurrency,
                        'exchange_rate' => $rate,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $updated++;
            }

            $this->info("Rates synced from {$name}: {$updated} currency pair(s) updated.");
            Log::info("rates:sync from {$name}: {$updated} currency pair(s) updated");
            return 0;
        }

        $this->error('All exchange-rate sources failed. Rates left untouched.');
        Log::error('rates:sync failed: all sources unreachable');
        return 1;
    }

    /**
     * Fetch parsed per-target rates (target => rate per 1 USD) from a source.
     * Returns null when the source could not be parsed.
     */
    private function fetchRates(string $name, string $url): ?array
    {
        try {
            $response = Http::timeout(15)->withoutVerifying()->get($url);
        } catch (\Throwable $e) {
            Log::warning("rates:sync {$name} request failed: " . $e->getMessage());
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        if ($name === 'floatrates') {
            return $this->parseFloatRatesXml($response->body());
        }

        return $this->parseOpenExchangeRatesJson($response->json());
    }

    private function parseFloatRatesXml(string $xml): ?array
    {
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        if (!@$doc->loadXML($xml)) {
            return null;
        }

        $rates = [];
        foreach ($doc->getElementsByTagName('item') as $item) {
            $target = $this->xmlNodeValue($item, 'targetCurrency');
            $rate = $this->xmlNodeValue($item, 'exchangeRate');
            if ($target && $rate !== null) {
                $rates[strtoupper($target)] = round((float) $rate, 6);
            }
        }

        return $rates ?: null;
    }

    private function xmlNodeValue(\DOMElement $item, string $tag): ?string
    {
        $nodes = $item->getElementsByTagName($tag);
        return $nodes->length > 0 ? $nodes->item(0)->nodeValue : null;
    }

    private function parseOpenExchangeRatesJson(?array $json): ?array
    {
        if (!is_array($json) || ($json['result'] ?? '') !== 'success') {
            return null;
        }

        $rates = [];
        foreach (($json['rates'] ?? []) as $code => $rate) {
            $rates[strtoupper($code)] = round((float) $rate, 6);
        }

        return $rates ?: null;
    }
}
