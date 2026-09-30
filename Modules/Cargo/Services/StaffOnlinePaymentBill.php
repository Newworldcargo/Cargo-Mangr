<?php

namespace Modules\Cargo\Services;

use App\Models\Transxn;
use App\Models\ShipmentPaymentReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Cargo\Entities\Shipment;
use Modules\CustomerPortalApi\Services\PaymentAttemptGuard;

class StaffOnlinePaymentBill
{
    public function prepare(int $shipmentId, $user, array $input): Transxn
    {
        return DB::transaction(function () use ($shipmentId, $user, $input) {
            $shipment = Shipment::with('branch')->whereKey($shipmentId)->lockForUpdate()->firstOrFail();
            abort_unless(app(ShipmentOperationAccessService::class)->canOperate($user, $shipment, 'confirm-shipment-payment'), 403);
            if ($shipment->paid || !$shipment->client_id) $this->reject('This shipment is already paid or has no assigned customer.');
            $pending = app(PaymentAttemptGuard::class)->unresolved($shipment->id);
            if ($pending) return Transxn::findOrFail($pending->invoice_id);
            $existing = Transxn::where('shipment_id', $shipment->id)->where('status', '!=', 'voided_duplicate')->latest('id')->first();
            if ($existing) {
                if (!in_array($existing->status, ['pending', 'unpaid'], true)) $this->reject('Please use the existing offline workflow for this bill.');
                if (PaymentAttemptGuard::minorUnits($input['final_total']) !== PaymentAttemptGuard::minorUnits($existing->total)) {
                    $this->reject('The confirmed bill is ' . $existing->currency . ' ' . number_format($existing->total, 2) . '. Refresh the payment details before continuing.');
                }
                return $existing;
            }
            if (ShipmentPaymentReceipt::where('shipment_id', $shipment->id)->whereIn('status', ['active', 'completed'])->where('refunded', false)->exists()) {
                $this->reject('A payment is already recorded. Please confirm the remaining bill with your branch.');
            }
            $currency = app(BranchAccessService::class)->currencyFor($user, $shipment->branch);
            if ($currency !== 'ZMW') $this->reject('Mobile-money collection is available for ZMW bills only. Use offline payment for this currency.');
            $hasRate = \App\Models\CurrencyExchangeRate::where('exchange_rate', '>', 0)->where(function ($query) {
                $query->where(function ($pair) { $pair->where('from_currency', 'USD')->where('to_currency', 'ZMW'); })
                    ->orWhere(function ($pair) { $pair->where('from_currency', 'ZMW')->where('to_currency', 'USD'); });
            })->exists();
            if (!$hasRate) $this->reject('Please ask your branch to confirm the currency conversion before collecting payment.');
            $base = round((float) convert_currency((float) $shipment->amount_to_be_collected, 'USD', 'ZMW'), 2);
            $baseMinor = PaymentAttemptGuard::minorUnits(number_format($base, 2, '.', ''));
            if (!$baseMinor) $this->reject('The bill amount is not ready. Please check the shipment pricing.');
            $charges = []; $total = $baseMinor;
            foreach ($input['charges'] ?? [] as $index => $charge) {
                $minor = PaymentAttemptGuard::minorUnits($charge['amount']);
                if (!$minor || trim($charge['description']) === '') $this->reject('Enter a name and positive amount for each extra charge.');
                $charges[] = ['description' => trim($charge['description']), 'amount_minor' => $minor, 'sort_order' => $index];
                $total += $minor;
            }
            $discountType = $input['discount_type'] ?? null;
            $discountValue = $input['discount_value'] ?? 0;
            $discountMinor = PaymentAttemptGuard::minorUnits($discountValue);
            if ($discountMinor === null) $this->reject('Check the discount amount.');
            if ($discountType === 'percent') {
                if ($discountMinor > 10000) $this->reject('The discount cannot exceed 100%.');
                $total = (int) round($total * (10000 - $discountMinor) / 10000);
            } elseif ($discountType === 'fixed') $total -= $discountMinor;
            elseif ($discountMinor > 0) $this->reject('Choose a discount type.');
            if ($total < 1 || $total !== PaymentAttemptGuard::minorUnits($input['final_total'])) $this->reject('The payment total changed. Check the bill and try again.');
            $invoice = Transxn::create([
                'shipment_id' => $shipment->id, 'cashier_user_id' => $user->id,
                'collection_branch_id' => app(BranchAccessService::class)->branchIdFor($user),
                'receipt_number' => 'REC-ONLINE-' . strtoupper(Str::random(16)),
                'discount_type' => $discountType ?: null, 'discount_value' => $discountValue,
                'total' => $total / 100, 'currency' => $currency, 'status' => 'pending',
            ]);
            $invoice->online_payment_details = ['base_minor' => $baseMinor, 'charges' => $charges];
            $invoice->save();
            return $invoice;
        });
    }

    private function reject(string $message): void
    {
        throw ValidationException::withMessages(['payment' => $message]);
    }
}
