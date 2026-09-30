<?php

namespace Modules\CustomerPortalApi\Services;

use App\Models\CurrencyExchangeRate;
use App\Models\NwcReceipt;
use App\Models\ShipmentChargeLine;
use App\Models\ShipmentPaymentReceipt;
use App\Models\Transxn;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Cargo\Entities\Client;
use Modules\Cargo\Entities\Shipment;
use Modules\Cargo\Services\BranchAccessService;

class CustomerShipmentBill
{
    public function quote(Shipment $shipment): ?array
    {
        if ($shipment->paid || in_array((int) $shipment->status_id, [Shipment::SAVED_STATUS, Shipment::CLOSED_STATUS], true)
            || (float) $shipment->amount_to_be_collected <= 0
            || NwcReceipt::where('shipment_id', $shipment->id)->exists()
            || ShipmentPaymentReceipt::where('shipment_id', $shipment->id)->where('status', '!=', 'voided_duplicate')->exists()) return null;
        $currency = app(BranchAccessService::class)->currencyFor(null, $shipment->branch);
        if (!in_array($currency, ['USD', 'ZMW'], true)) return null;
        $convert = function ($amount, $from) use ($currency) {
            if (!in_array($from, ['USD', 'ZMW'], true) || (float) $amount < 0) return null;
            if ($from !== $currency && !CurrencyExchangeRate::where('exchange_rate', '>', 0)->where(function ($query) use ($from, $currency) {
                $query->where(function ($pair) use ($from, $currency) { $pair->where('from_currency', $from)->where('to_currency', $currency); })
                    ->orWhere(function ($pair) use ($from, $currency) { $pair->where('from_currency', $currency)->where('to_currency', $from); });
            })->exists()) return null;
            return PaymentAttemptGuard::minorUnits(number_format((float) convert_currency((float) $amount, $from, $currency), 2, '.', ''));
        };
        $base = $convert($shipment->amount_to_be_collected, 'USD');
        if (!$base) return null;
        $total = $base;
        foreach (ShipmentChargeLine::where('shipment_id', $shipment->id)->get() as $charge) {
            $minor = $convert($charge->amount, strtoupper((string) $charge->currency));
            if ($minor === null) return null;
            $total += $minor;
        }
        return ['currency' => $currency, 'amountMinor' => $total, 'baseMinor' => $base];
    }

    public function prepare(int $shipmentId, int $clientId): Transxn
    {
        return DB::transaction(function () use ($shipmentId, $clientId) {
            Client::whereKey($clientId)->lockForUpdate()->firstOrFail();
            $shipment = Shipment::where('client_id', $clientId)->whereKey($shipmentId)->lockForUpdate()->firstOrFail();
            abort_if((bool) $shipment->paid, 409, 'This shipment is already paid.');
            $invoice = Transxn::where('shipment_id', $shipmentId)->where('status', '!=', 'voided_duplicate')->latest('id')->first();
            if ($invoice) return $invoice;
            if (app(PaymentAttemptGuard::class)->unresolved($shipmentId)) {
                throw ValidationException::withMessages(['payment' => 'A previous payment is still being checked. Please contact your branch.']);
            }
            $quote = $this->quote($shipment);
            if (!$quote) throw ValidationException::withMessages(['payment' => 'We could not prepare this bill. Please contact your branch to check its charges.']);
            $invoice = Transxn::create([
                'shipment_id' => $shipmentId, 'collection_branch_id' => $shipment->branch_id,
                'receipt_number' => 'REC-ONLINE-' . strtoupper(Str::random(16)),
                'total' => $quote['amountMinor'] / 100, 'currency' => $quote['currency'], 'status' => 'pending',
                'discount_value' => 0,
            ]);
            // These charge lines already exist; settlement must not create them again.
            $invoice->online_payment_details = ['base_minor' => $quote['baseMinor'], 'charges' => [], 'source' => 'customer-shipment'];
            $invoice->save();
            app(AuditLogService::class)->createLog('customer_checkout_bill_prepared', $invoice, null, [], [
                'shipment_id' => $shipmentId, 'client_id' => $clientId, 'amount_minor' => $quote['amountMinor'], 'currency' => $quote['currency'],
            ], 'Customer opened checkout for the stored shipment charges. No payment recorded.');
            return $invoice;
        });
    }
}
