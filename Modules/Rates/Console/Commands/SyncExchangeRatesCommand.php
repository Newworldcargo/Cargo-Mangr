<?php

namespace Modules\Rates\Console\Commands;

use Illuminate\Console\Command;
use Modules\Rates\Services\RatesService;

/**
 * Exchange-rate sync command for the Rates module.
 *
 * Runs from the Laravel scheduler:
 *  - php artisan rates:sync                 (daily baseline, 02:00)
 *  - php artisan rates:sync --mode=intraday (frequent, within trading hours)
 *
 * Both write to the same currency_exchange_rates table that every other
 * module reads from (HandlesCurrencyExchange trait, app/helpers,
 * TransxnController, the currency settings modal).
 */
class SyncExchangeRatesCommand extends Command
{
    protected $signature = 'rates:sync
                            {--source= : force a specific source (floatrates|openexchangerates)}
                            {--mode=daily : sync mode (daily|intraday)}';

    protected $description = 'Sync exchange rates from free public feeds into currency_exchange_rates';

    public function handle(RatesService $ratesService): int
    {
        $source = $this->option('source') ?: null;
        if ($source && !in_array($source, $ratesService->availableSources(), true)) {
            $this->error("Unknown source: {$source}. Use " . implode('|', $ratesService->availableSources()));
            return 1;
        }

        $result = $ratesService->sync($source);

        if ($result['errors']) {
            $this->error('rates:sync failed — ' . implode(', ', $result['errors']));
            return 1;
        }

        $this->info(sprintf(
            'Rates synced from %s: %d pair(s) updated, %d unchanged.',
            $result['source'] ?? 'no source',
            $result['updated'],
            $result['skipped'],
        ));

        return 0;
    }
}
