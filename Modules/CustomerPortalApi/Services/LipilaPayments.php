<?php

namespace Modules\CustomerPortalApi\Services;

use App\Models\NwcReceipt;
use App\Models\ShipmentPaymentReceipt;
use App\Models\Transxn;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Cargo\Entities\Shipment;
use Modules\CustomerPortalApi\Models\PortalPaymentIntent;

class LipilaPayments
{
    public function __construct(private LipilaGateway $gateway) {}

    public function create(Transxn $invoice, int $clientId, array $input): PortalPaymentIntent
    {
        $created = false;
        $intent = DB::transaction(function () use ($invoice, $clientId, $input, &$created) {
            $shipment = Shipment::whereKey($invoice->shipment_id)->lockForUpdate()->firstOrFail();
            $invoice = Transxn::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $shipment->client_id === $clientId, 404);
            $existing = PortalPaymentIntent::where('invoice_id', $invoice->id)->where('status', '!=', 'failed')->latest('id')->first();
            if ($existing) return $existing;
            $latest = Transxn::where('shipment_id', $shipment->id)->where('status', '!=', 'voided_duplicate')->latest('id')->first();
            $summary = app(ShipmentPaymentSummary::class)->forShipment($shipment);
            if ($shipment->paid || $latest?->id !== $invoice->id || !in_array($invoice->status, ['pending', 'unpaid'], true)
                || $summary['paid']['amountMinor'] > 0 || $summary['remaining']['amountMinor'] < 1) {
                throw ValidationException::withMessages(['invoiceId' => 'This bill is not available for online payment. Please contact your branch.']);
            }
            $currency = strtoupper((string) $invoice->currency);
            if (!in_array($currency, ['USD', 'ZMW'], true) || ($input['method'] === 'mobile-money' && $currency !== 'ZMW')) {
                throw ValidationException::withMessages(['method' => 'Please use a card for this currency, or contact your branch.']);
            }
            $created = true;
            return PortalPaymentIntent::create([
                'intent_id' => (string) Str::uuid(), 'client_id' => $clientId, 'invoice_id' => $invoice->id,
                'provider' => 'lipila', 'method' => $input['method'], 'status' => 'processing',
                'currency' => $currency, 'amount_minor' => $summary['remaining']['amountMinor'], 'revision' => 1,
            ]);
        });
        if (!$created) return $intent;
        $payload = [
            'referenceId' => $intent->intent_id, 'amount' => $intent->amount_minor / 100,
            'currency' => $intent->currency, 'accountNumber' => $input['phone'],
            'narration' => 'New World Cargo invoice ' . $invoice->receipt_number,
        ];
        try {
            $result = $this->gateway->collect($payload, $input['method'] === 'card' ? $input['billing'] + ['phoneNumber' => $input['phone']] : null);
            DB::transaction(function () use ($intent, $result) {
                $locked = PortalPaymentIntent::whereKey($intent->id)->lockForUpdate()->firstOrFail();
                if ($locked->status !== 'processing') return;
                $data = $result['data'];
                if ($result['accepted'] && ($data['referenceId'] ?? null) === $locked->intent_id) {
                    $locked->provider_reference = isset($data['identifier']) ? substr((string) $data['identifier'], 0, 255) : null;
                    $locked->checkout_url = $this->gateway->checkoutUrl($data['cardRedirectionUrl'] ?? null);
                    $locked->status = $locked->checkout_url ? 'requires_action' : 'processing';
                    $locked->increment('revision');
                    $locked->save();
                }
            });
            // Only the authenticated status endpoint can settle or release an attempt.
            $this->refresh($intent);
        } catch (\Throwable $e) {
            Log::warning('Lipila collection outcome requires reconciliation.', ['intent_id' => $intent->intent_id, 'exception' => get_class($e)]);
        }
        return $intent->fresh();
    }

    public function refresh(PortalPaymentIntent $intent): PortalPaymentIntent
    {
        if ($intent->provider !== 'lipila' || in_array($intent->status, ['succeeded', 'failed', 'review'], true)) return $intent;
        $lock = Cache::lock('lipila-status-' . $intent->intent_id, 30);
        if (!$lock->get()) return $intent->fresh();
        try {
            $intent->refresh();
            if ($intent->last_checked_at && strtotime($intent->last_checked_at) > time() - 5) return $intent;
            $intent->update(['last_checked_at' => now()]);
            $data = $this->gateway->status($intent->intent_id);
            if (!$data) return $intent;
            $this->applyStatus($intent, $data);
        } finally {
            $lock->release();
        }
        return $intent->fresh();
    }

    private function applyStatus(PortalPaymentIntent $intent, array $data): void
    {
        if (($data['referenceId'] ?? null) !== $intent->intent_id || ($data['type'] ?? null) !== 'Collection') return;
        $status = $data['status'] ?? '';
        if (!in_array($status, ['Successful', 'Failed'], true)) return;
        DB::transaction(function () use ($intent, $data, $status) {
            $invoice = Transxn::findOrFail($intent->invoice_id);
            $shipment = Shipment::whereKey($invoice->shipment_id)->lockForUpdate()->firstOrFail();
            $invoice = Transxn::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $intent = PortalPaymentIntent::whereKey($intent->id)->lockForUpdate()->firstOrFail();
            if (in_array($intent->status, ['succeeded', 'review'], true)) return;
            if (($data['currency'] ?? null) !== $intent->currency || !is_numeric($data['amount'] ?? null)
                || (int) round((float) $data['amount'] * 100) !== (int) $intent->amount_minor) {
                $this->review($intent, 'Provider amount or currency differs from the payment request.');
                return;
            }
            if ($status === 'Failed') {
                $intent->update(['status' => 'failed', 'revision' => $intent->revision + 1]);
                return;
            }
            $summary = app(ShipmentPaymentSummary::class)->forShipment($shipment);
            if ($shipment->paid || (int) $shipment->client_id !== (int) $intent->client_id
                || (string) $summary['invoiceId'] !== (string) $invoice->id || $summary['remaining']['amountMinor'] !== (int) $intent->amount_minor
                || $summary['remaining']['currency'] !== $intent->currency || !in_array($invoice->status, ['pending', 'unpaid'], true)) {
                $this->review($intent, 'The bill changed while the payment was in progress.');
                return;
            }
            $number = $invoice->receipt_number ?: 'REC-ONLINE-' . $invoice->id;
            $receipt = ShipmentPaymentReceipt::create([
                'shipment_id' => $shipment->id, 'collection_branch_id' => $invoice->collection_branch_id ?: $shipment->branch_id,
                'method_of_payment' => 'Lipila ' . ($intent->method === 'card' ? 'card' : 'mobile money'),
                'amount' => $intent->amount_minor / 100, 'currency' => $intent->currency, 'status' => 'active',
                'receipt_number' => $number . '-LIPILA-' . $intent->id, 'cashier_name' => 'Online payment', 'refunded' => false,
            ]);
            NwcReceipt::updateOrCreate(['shipment_id' => $shipment->id], [
                'receipt_number' => $number, 'payment_currency' => $intent->currency,
                'bill_usd' => $intent->currency === 'USD' ? $invoice->total : null,
                'bill_kwacha' => $intent->currency === 'ZMW' ? $invoice->total : null,
                'method_of_payment' => $receipt->method_of_payment, 'cashier_name' => 'Online payment',
                'collection_branch_id' => $receipt->collection_branch_id,
                'discount_type' => $invoice->discount_type, 'discount_value' => $invoice->discount_value ?: 0,
            ]);
            $invoice->update(['status' => 'completed', 'receipt_number' => $number]);
            $shipment->update(['paid' => 1]);
            $intent->update(['status' => 'succeeded', 'settled_at' => now(), 'revision' => $intent->revision + 1]);
            app(AuditLogService::class)->createLog('lipila_payment_confirmed', $receipt, null, [], [
                'intent_id' => $intent->intent_id, 'amount' => $receipt->amount, 'currency' => $intent->currency,
            ], 'Online payment confirmed by Lipila.');
        });
    }

    private function review(PortalPaymentIntent $intent, string $reason): void
    {
        $intent->update(['status' => 'review', 'revision' => $intent->revision + 1]);
        app(AuditLogService::class)->createLog('lipila_payment_review', $intent, null, [], [], $reason);
        Log::error('Lipila payment needs financial review.', ['intent_id' => $intent->intent_id, 'reason' => $reason]);
    }
}
