<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\CustomerPortalApi\Models\PortalPaymentIntent;
use Modules\CustomerPortalApi\Services\LipilaGateway;
use Modules\CustomerPortalApi\Services\LipilaPayments;

class ReconcileLipilaPayments extends Command
{
    protected $signature = 'payments:reconcile-lipila';
    protected $description = 'Check pending Lipila payments without issuing new charges';

    public function handle(LipilaGateway $gateway, LipilaPayments $payments)
    {
        if (!$gateway->configured()) return 0;
        $started = microtime(true);
        $intents = PortalPaymentIntent::where('provider', 'lipila')->whereIn('status', ['processing', 'requires_action'])
            ->orderBy('last_checked_at')->limit(50)->get();
        foreach ($intents as $intent) {
            if (microtime(true) - $started > 40) break;
            try { $payments->refresh($intent); }
            catch (\Throwable $e) { Log::warning('Lipila reconciliation deferred.', ['intent_id' => $intent->intent_id, 'exception' => get_class($e)]); }
        }
        return 0;
    }
}
