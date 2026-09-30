<?php

namespace Modules\CustomerPortalApi\Services;

use App\Models\Transxn;
use Illuminate\Validation\ValidationException;
use Modules\CustomerPortalApi\Models\PortalPaymentIntent;

class PaymentAttemptGuard
{
    public function unresolved(int $shipmentId): ?PortalPaymentIntent
    {
        return PortalPaymentIntent::where(function ($query) use ($shipmentId) {
            $query->where('shipment_id', $shipmentId)->orWhereIn('invoice_id', Transxn::where('shipment_id', $shipmentId)->select('id'));
        })->whereNotIn('status', ['failed', 'succeeded', 'completed', 'confirmed'])
            ->where(function ($query) {
                $query->whereNull('superseded_by')->orWhereNotIn('status', ['processing', 'requires_action']);
            })->orderByRaw("CASE WHEN status = 'review' THEN 0 ELSE 1 END")->latest('id')->first();
    }

    // Call while holding the shipment row lock shared by online and cashier payments.
    public function blocksCash(int $shipmentId): bool
    {
        return PortalPaymentIntent::where(function ($query) use ($shipmentId) {
            $query->where('shipment_id', $shipmentId)->orWhereIn('invoice_id', Transxn::where('shipment_id', $shipmentId)->select('id'));
        })->whereNotIn('status', ['failed', 'succeeded', 'completed', 'confirmed'])
            ->where(function ($query) {
                $query->whereNull('cash_override_at')->orWhereNotIn('status', ['processing', 'requires_action']);
            })->exists();
    }

    public function assertNoUnresolvedPayment(int $shipmentId): void
    {
        if ($this->unresolved($shipmentId)) {
            throw ValidationException::withMessages(['payment' => 'An online payment is still being checked for this shipment. Confirm its outcome before recording another payment.']);
        }
    }

    public static function minorUnits($amount): ?int
    {
        if (!is_string($amount) && !is_int($amount) && !is_float($amount)) return null;
        if (!preg_match('/^([0-9]{1,13})(?:\.([0-9]{1,2}))?$/D', (string) $amount, $parts)) return null;
        return ((int) $parts[1]) * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
    }
}
