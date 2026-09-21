<?php

namespace Modules\CustomerPortalApi\Services;

use App\Models\NwcReceipt;
use App\Models\ShipmentPaymentReceipt;
use App\Models\Transxn;
use Modules\Cargo\Entities\Shipment;
use Modules\Cargo\Services\BranchAccessService;

class ShipmentPaymentSummary
{
    public function forShipment(Shipment $shipment): array
    {
        $transactions = Transxn::where('shipment_id', $shipment->id)->where('status', '!=', 'voided_duplicate')->orderByDesc('id')->get();
        $invoice = $transactions->first();
        $currency = strtoupper($invoice?->currency ?: app(BranchAccessService::class)->currencyFor(null, $shipment->branch));
        $total = $invoice ? (float) $invoice->total : convert_currency((float) $shipment->amount_to_be_collected, 'USD', $currency);
        $payments = ShipmentPaymentReceipt::where('shipment_id', $shipment->id)
            ->whereIn('status', ['active', 'completed'])->orderBy('created_at')->orderBy('id')->get();
        $receipts = [];
        foreach ($payments as $payment) {
            $receipts[] = $this->receipt('payment-' . $payment->id, $payment->receipt_number,
                (float) $payment->amount, strtoupper($payment->currency ?: $currency),
                $payment->created_at, $payment->method_of_payment, (bool) $payment->refunded);
        }
        foreach ($transactions as $transaction) {
            $hasPayments = $payments->contains(fn ($payment) => $this->matches($payment->receipt_number, $transaction->receipt_number));
            if (!$hasPayments && $transaction->isCompleted()) {
                $receipts[] = $this->receipt('transaction-' . $transaction->id, $transaction->receipt_number,
                    (float) $transaction->total, strtoupper($transaction->currency ?: $currency),
                    $transaction->created_at, 'Recorded payment', false);
            }
        }
        // Older receipts can predate both the transaction and installment ledgers.
        if (!$receipts && $shipment->paid && !$invoice) {
            foreach (NwcReceipt::where('shipment_id', $shipment->id)->get() as $legacy) {
                $legacyCurrency = strtoupper($legacy->payment_currency ?: $currency);
                if (!in_array($legacyCurrency, ['USD', 'ZMW'], true)) continue;
                $receipts[] = $this->receipt('legacy-' . $legacy->id, $legacy->receipt_number,
                    (float) ($legacyCurrency === 'USD' ? $legacy->bill_usd : $legacy->bill_kwacha),
                    $legacyCurrency, $legacy->created_at, $legacy->method_of_payment, false);
            }
        }
        $paid = collect($receipts)->filter(fn ($receipt) => !$receipt['refunded'] && $receipt['amount']['currency'] === $currency
            && (!$invoice || $this->matches($receipt['number'], $invoice->receipt_number)))->sum('amount.amountMinor');
        $totalMinor = max(0, (int) round($total * 100));
        $settled = $invoice ? $invoice->isCompleted() || $invoice->isRefunded() : (bool) $shipment->paid;
        $remaining = $settled ? 0 : max(0, $totalMinor - $paid);
        $providerReady = (bool) config('customerportalapi.payment_provider') && (bool) config('customerportalapi.payment_webhook_url');
        $checkoutMessage = !$providerReady ? 'Online payments are currently unavailable. Please contact your branch to arrange payment.'
            : (!$invoice ? 'Please contact your branch to confirm your bill before paying online.'
                : ($invoice->status === 'partially_paid' ? 'Please contact your branch to pay the remaining balance.' : null));

        return [
            'total' => ['currency' => $currency, 'amountMinor' => $totalMinor],
            'paid' => ['currency' => $currency, 'amountMinor' => (int) $paid],
            'remaining' => ['currency' => $currency, 'amountMinor' => $remaining],
            'status' => $invoice?->isRefunded() ? 'Refunded' : ($settled ? 'Paid' : ($paid > 0 ? 'Partially paid' : 'Unpaid')),
            'invoiceId' => $invoice ? (string) $invoice->id : null,
            'checkoutMessage' => $checkoutMessage,
            'receipts' => $receipts,
        ];
    }

    private function matches(?string $number, ?string $parent): bool
    {
        return $parent && ($number === $parent || str_starts_with((string) $number, $parent . '-'));
    }

    private function receipt(string $id, ?string $number, float $amount, string $currency, $date, ?string $method, bool $refunded): array
    {
        return ['id' => $id, 'number' => $number ?: $id, 'amount' => ['currency' => $currency, 'amountMinor' => max(0, (int) round($amount * 100))],
            'paidAt' => $date?->toIso8601String(), 'dateLabel' => $date?->format('d M Y, H:i'),
            'method' => ucwords(str_replace('_', ' ', $method ?: 'Recorded payment')), 'refunded' => $refunded];
    }
}
