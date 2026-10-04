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
    public const CUSTOMER_RETRY_SECONDS = 300;

    public function __construct(private LipilaGateway $gateway) {}

    public function create(Transxn $invoice, int $clientId, array $input, ?int $initiatedBy = null): PortalPaymentIntent
    {
        $input = $this->validateInput($input);
        if (!$this->gateway->ready()) throw ValidationException::withMessages(['payment' => 'Online payments are currently unavailable.']);
        $requestKey = $input['idempotencyKey'] ?? null;
        if ($initiatedBy === null && isset($input['restartIntentId'])
            && !($requestKey && PortalPaymentIntent::where('client_id', $clientId)->where('request_key', $requestKey)->exists())) {
            $previous = PortalPaymentIntent::where('intent_id', $input['restartIntentId'])
                ->where('client_id', $clientId)->where('invoice_id', $invoice->id)->first();
            abort_unless($previous && (int) $invoice->shipment->client_id === $clientId, 404);
            if (!$this->customerCanRestart($previous)) {
                throw ValidationException::withMessages(['payment' => 'Check the payment status before trying again.']);
            }
            // Never release an unknown outcome merely because the browser timer elapsed.
            $data = $this->gateway->status($previous->intent_id);
            if (!$data || ($data['referenceId'] ?? null) !== $previous->intent_id
                || ($data['type'] ?? null) !== 'Collection' || ($data['currency'] ?? null) !== $previous->currency
                || PaymentAttemptGuard::minorUnits($data['amount'] ?? null) !== (int) $previous->amount_minor
                || !in_array($data['status'] ?? '', ['Pending', 'Failed', 'Successful'], true)) {
                throw ValidationException::withMessages(['payment' => 'We could not check the previous payment. Please try again shortly.']);
            }
            $this->applyStatus($previous, $data);
            $previous->refresh();
            if ($previous->status !== 'processing') return $previous;
        }
        $fingerprint = hash('sha256', json_encode([$invoice->id, $input['method'], $input['phone'], $input['billing'] ?? null]));
        if (isset($input['network'])) $fingerprint = hash('sha256', $fingerprint . ':' . $input['network']);
        $created = false;
        $intent = DB::transaction(function () use ($invoice, $clientId, $input, $requestKey, $fingerprint, $initiatedBy, &$created) {
            \Modules\Cargo\Entities\Client::whereKey($clientId)->lockForUpdate()->firstOrFail();
            $shipment = Shipment::whereKey($invoice->shipment_id)->lockForUpdate()->firstOrFail();
            $invoice = Transxn::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($initiatedBy !== null) {
                $staff = \App\Models\User::findOrFail($initiatedBy);
                abort_unless(app(\Modules\Cargo\Services\ShipmentOperationAccessService::class)->canOperate($staff, $shipment, 'confirm-shipment-payment'), 403);
            }
            abort_unless((int) $shipment->client_id === $clientId, 404);
            if ((int) $invoice->shipment_id !== (int) $shipment->id) abort(409, 'The bill changed. Please refresh.');
            if ($requestKey) {
                $replay = PortalPaymentIntent::where('client_id', $clientId)->where('request_key', $requestKey)->first();
                if ($replay) {
                    if (!hash_equals((string) $replay->request_fingerprint, $fingerprint)) throw ValidationException::withMessages(['idempotencyKey' => 'This payment request was already used with different details.']);
                    return $replay;
                }
            }
            $existing = app(PaymentAttemptGuard::class)->unresolved($shipment->id);
            $restarting = isset($input['restartIntentId']);
            if ($restarting) {
                if (!$existing || $existing->intent_id !== $input['restartIntentId']
                    || ($initiatedBy ? !$existing->cash_override_at : !$this->customerCanRestart($existing))
                    || $existing->superseded_by || $existing->provider !== 'lipila'
                    || $existing->method !== 'mobile-money' || $input['method'] !== 'mobile-money'
                    || !in_array($existing->status, ['processing', 'requires_action'], true)
                    || (int) $existing->client_id !== $clientId || (int) $existing->invoice_id !== (int) $invoice->id) {
                    throw ValidationException::withMessages(['payment' => 'The previous payment changed. Check its status before starting again.']);
                }
            }
            if ($existing) {
                if ((int) $existing->client_id !== $clientId || (int) $existing->invoice_id !== (int) $invoice->id) {
                    throw ValidationException::withMessages(['invoiceId' => 'Another payment for this shipment is still being checked. Please contact your branch.']);
                }
                if (!$restarting) return $existing;
            }
            $latest = Transxn::where('shipment_id', $shipment->id)->where('status', '!=', 'voided_duplicate')->latest('id')->first();
            $summary = app(ShipmentPaymentSummary::class)->forShipment($shipment);
            if (ShipmentPaymentReceipt::where('shipment_id', $shipment->id)->whereIn('status', ['active', 'completed'])->where('refunded', false)->exists()) {
                throw ValidationException::withMessages(['invoiceId' => 'This shipment has a recorded payment. Please contact your branch to confirm the remaining bill.']);
            }
            if ($shipment->paid || $latest?->id !== $invoice->id || !in_array($invoice->status, ['pending', 'unpaid'], true)
                || $summary['paid']['amountMinor'] > 0 || $summary['remaining']['amountMinor'] < 1) {
                throw ValidationException::withMessages(['invoiceId' => 'This bill is not available for online payment. Please contact your branch.']);
            }
            $currency = strtoupper((string) $invoice->currency);
            $amountMinor = PaymentAttemptGuard::minorUnits($invoice->total);
            if (!$amountMinor || $amountMinor !== $summary['remaining']['amountMinor']) throw ValidationException::withMessages(['invoiceId' => 'Please contact your branch to confirm the bill.']);
            if (!in_array($currency, ['USD', 'ZMW'], true) || ($input['method'] === 'mobile-money' && $currency !== 'ZMW')) {
                throw ValidationException::withMessages(['method' => 'Please use a card for this currency, or contact your branch.']);
            }
            $created = true;
            $newIntent = PortalPaymentIntent::create([
                'intent_id' => (string) Str::uuid(), 'client_id' => $clientId, 'invoice_id' => $invoice->id,
                'provider' => 'lipila', 'method' => $input['method'], 'status' => 'processing',
                'currency' => $currency, 'amount_minor' => $amountMinor, 'revision' => 1,
                'shipment_id' => $shipment->id, 'request_key' => $requestKey, 'request_fingerprint' => $fingerprint,
                'provider_environment' => config('lipila.base_url'),
                'initiated_by' => $initiatedBy, 'billing_snapshot' => isset($input['network']) ? array_merge($invoice->online_payment_details ?? [], ['network' => $input['network']]) : $invoice->online_payment_details,
            ]);
            if ($restarting) {
                $existing->update(['superseded_by' => $newIntent->id]);
                app(AuditLogService::class)->createLog('online_payment_restarted', $newIntent, null, [], [
                    'previous_intent_id' => $existing->intent_id, 'intent_id' => $newIntent->intent_id,
                    'staff_id' => $initiatedBy, 'shipment_id' => $shipment->id,
                    'client_id' => $clientId,
                ], ($initiatedBy ? 'Staff' : 'Customer') . ' confirmed no payment received and requested a new prompt. Previous request remains monitored.');
            }
            return $newIntent;
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

    public function refresh(PortalPaymentIntent $intent, bool $notification = false): PortalPaymentIntent
    {
        if ($intent->provider !== 'lipila' || in_array($intent->status, ['succeeded', 'review'], true) || (!$notification && $intent->status === 'failed')) return $intent;
        if ($intent->provider_environment && $intent->provider_environment !== config('lipila.base_url')) {
            if ($notification) throw new \RuntimeException('Payment belongs to another provider environment.');
            return $intent;
        }
        $lock = Cache::lock('lipila-status-' . $intent->intent_id, 30);
        if (!$lock->get()) {
            if ($notification) throw new \RuntimeException('Payment confirmation is already being checked.');
            return $intent->fresh();
        }
        try {
            $intent->refresh();
            if (in_array($intent->status, ['succeeded', 'review'], true)) return $intent;
            if (!$notification && $intent->last_checked_at && strtotime($intent->last_checked_at) > time() - 5) return $intent;
            $intent->update(['last_checked_at' => now()]);
            $data = $this->gateway->status($intent->intent_id);
            if ($notification && (!$data || ($data['referenceId'] ?? null) !== $intent->intent_id
                || ($data['type'] ?? null) !== 'Collection' || !in_array($data['status'] ?? '', ['Successful', 'Failed'], true))) {
                throw new \RuntimeException('Final payment status is not yet available.');
            }
            if (!$data) return $intent;
            $this->applyStatus($intent, $data);
        } finally {
            $lock->release();
        }
        return $intent->fresh();
    }

    public function customerCanRestart(PortalPaymentIntent $intent): bool
    {
        return $intent->provider === 'lipila' && $intent->method === 'mobile-money'
            && !$intent->initiated_by && !$intent->cash_override_at && !$intent->superseded_by && $intent->status === 'processing'
            && (!$intent->provider_environment || $intent->provider_environment === config('lipila.base_url'))
            && $intent->created_at && $intent->created_at->copy()->addSeconds(self::CUSTOMER_RETRY_SECONDS)->isPast();
    }

    private function applyStatus(PortalPaymentIntent $intent, array $data): void
    {
        if (($data['referenceId'] ?? null) !== $intent->intent_id || ($data['type'] ?? null) !== 'Collection') return;
        $status = $data['status'] ?? '';
        if (!in_array($status, ['Successful', 'Failed'], true)) return;
        DB::transaction(function () use ($intent, $data, $status) {
            $invoice = Transxn::findOrFail($intent->invoice_id);
            $shipment = Shipment::whereKey($intent->shipment_id ?: $invoice->shipment_id)->lockForUpdate()->firstOrFail();
            $invoice = Transxn::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $intent = PortalPaymentIntent::whereKey($intent->id)->lockForUpdate()->firstOrFail();
            if (in_array($intent->status, ['succeeded', 'review'], true)) return;
            if (($data['currency'] ?? null) !== $intent->currency
                || PaymentAttemptGuard::minorUnits($data['amount'] ?? null) !== (int) $intent->amount_minor) {
                $this->review($intent, 'Provider amount or currency differs from the payment request.');
                return;
            }
            if (is_string($data['identifier'] ?? null) && strlen($data['identifier']) <= 255) {
                $intent->provider_reference = $data['identifier'];
            }
            if ($status === 'Failed') {
                $intent->update(['status' => 'failed', 'revision' => $intent->revision + 1]);
                return;
            }
            if ($intent->status === 'failed') {
                $this->review($intent, 'Lipila reported success after a confirmed failure. Reconcile before accepting further payment.');
                return;
            }
            $summary = app(ShipmentPaymentSummary::class)->forShipment($shipment);
            if ($intent->cash_override_at && ShipmentPaymentReceipt::where('shipment_id', $shipment->id)->whereIn('status', ['active', 'completed'])->where('refunded', false)->exists()) {
                $this->review($intent, 'Online payment succeeded after staff recorded payment under a cash override. Reconcile the additional funds; do not mark a second payment against the bill.');
                return;
            }
            if ((int) $invoice->shipment_id !== (int) $shipment->id || $shipment->paid || (int) $shipment->client_id !== (int) $intent->client_id
                || (string) $summary['invoiceId'] !== (string) $invoice->id || $summary['remaining']['amountMinor'] !== (int) $intent->amount_minor
                || $summary['remaining']['currency'] !== $intent->currency || !in_array($invoice->status, ['pending', 'unpaid'], true)) {
                $this->review($intent, 'The bill changed while the payment was in progress.');
                return;
            }
            $number = $invoice->receipt_number ?: 'REC-ONLINE-' . $invoice->id;
            $cashier = $intent->initiated_by ? \App\Models\User::find($intent->initiated_by) : null;
            $cashierName = $cashier ? $cashier->name : 'Online payment';
            $receipt = ShipmentPaymentReceipt::create([
                'shipment_id' => $shipment->id, 'collection_branch_id' => $invoice->collection_branch_id ?: $shipment->branch_id,
                'method_of_payment' => 'Lipila ' . ($intent->method === 'card' ? 'card' : 'mobile money'),
                'amount' => $intent->amount_minor / 100, 'currency' => $intent->currency, 'status' => 'active',
                'receipt_number' => $number . '-LIPILA-' . $intent->id, 'cashier_name' => $cashierName, 'user_id' => $cashier?->id, 'refunded' => false,
            ]);
            NwcReceipt::updateOrCreate(['shipment_id' => $shipment->id], [
                'receipt_number' => $number, 'payment_currency' => $intent->currency,
                'bill_usd' => $intent->currency === 'USD' ? $invoice->total : null,
                'bill_kwacha' => $intent->currency === 'ZMW' ? (isset($intent->billing_snapshot['base_minor']) ? $intent->billing_snapshot['base_minor'] / 100 : $invoice->total) : null,
                'method_of_payment' => $receipt->method_of_payment, 'cashier_name' => $cashierName, 'user_id' => $cashier?->id,
                'collection_branch_id' => $receipt->collection_branch_id,
                'discount_type' => $invoice->discount_type, 'discount_value' => $invoice->discount_value ?: 0,
            ]);
            foreach (($intent->billing_snapshot['charges'] ?? []) as $charge) {
                \App\Models\ShipmentChargeLine::create([
                    'shipment_id' => $shipment->id, 'description' => $charge['description'],
                    'amount' => $charge['amount_minor'] / 100, 'currency' => $intent->currency, 'sort_order' => $charge['sort_order'],
                ]);
            }
            $invoice->update(['status' => 'completed', 'receipt_number' => $number]);
            $shipment->update(['paid' => 1]);
            $intent->update(['status' => 'succeeded', 'settled_at' => now(), 'receipt_id' => $receipt->id, 'checkout_url' => null, 'revision' => $intent->revision + 1]);
            app(AuditLogService::class)->createLog('lipila_payment_confirmed', $receipt, null, [], [
                'intent_id' => $intent->intent_id, 'amount' => $receipt->amount, 'currency' => $intent->currency,
                'provider_reference' => $intent->provider_reference,
            ], 'Online payment confirmed by Lipila.');
        });
    }

    private function review(PortalPaymentIntent $intent, string $reason): void
    {
        $intent->update(['status' => 'review', 'review_reason' => $reason, 'checkout_url' => null, 'revision' => $intent->revision + 1]);
        app(AuditLogService::class)->createLog('lipila_payment_review', $intent, null, [], [], $reason);
        Log::error('Lipila payment needs financial review.', ['intent_id' => $intent->intent_id, 'reason' => $reason]);
    }

    private function validateInput(array $input): array
    {
        $rules = ['method' => ['required', 'in:mobile-money,card'], 'phone' => ['required', 'string', 'regex:/^260[79][0-9]{8}$/'], 'idempotencyKey' => ['nullable', 'uuid']];
        $rules['network'] = ['sometimes', 'in:mtn,airtel,zamtel'];
        $rules['restartIntentId'] = ['sometimes', 'required', 'uuid'];
        $rules['restartAcknowledged'] = [isset($input['restartIntentId']) ? 'required' : 'sometimes', 'accepted'];
        if (($input['method'] ?? '') === 'card') {
            $rules['phone'] = ['required', 'string', 'regex:/^[1-9][0-9]{7,14}$/'];
            foreach (['firstName', 'lastName', 'city', 'address', 'zip'] as $field) $rules['billing.' . $field] = ['required', 'string', 'max:150'];
            $rules['billing.country'] = ['required', 'regex:/^[A-Z]{2}$/'];
            $rules['billing.email'] = ['required', 'email', 'max:150'];
        }
        return \Illuminate\Support\Facades\Validator::make($input, $rules)->validate();
    }
}
