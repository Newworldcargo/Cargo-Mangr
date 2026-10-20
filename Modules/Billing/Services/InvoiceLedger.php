<?php

namespace Modules\Billing\Services;

use DateTimeImmutable;
use DomainException;
use Illuminate\Database\ConnectionInterface;

/** Internal ledger. HTTP entry points must authorize the actor, branch and customer before calling it. */
final class InvoiceLedger
{
    private ConnectionInterface $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    public function createDraft(array $input, int $actorId): int
    {
        [$record, $items] = $this->prepareDraft($input, $actorId);
        $fingerprint = hash('sha256', $this->json([$record, $items]));
        return $this->insertDraft($input, $record, $items, $fingerprint, $actorId);
    }

    public function prepareDraft(array $input, int $actorId): array
    {
        $this->actor($actorId);
        $this->key($input['request_key'] ?? '');
        foreach (['client_id', 'branch_id'] as $field) {
            if (!isset($input[$field]) || !ctype_digit((string) $input[$field]) || (int) $input[$field] < 1) {
                throw new DomainException('Select a valid customer and branch.');
            }
        }
        $this->currency($input['currency'] ?? '');
        $this->date($input['issue_date'] ?? '');
        $this->date($input['due_date'] ?? '');
        if ($input['due_date'] < $input['issue_date']) {
            throw new DomainException('The due date cannot be before the issue date.');
        }
        foreach (['customer_snapshot', 'issuer_snapshot'] as $field) {
            if (!is_array($input[$field] ?? null) || trim((string) ($input[$field]['name'] ?? '')) === '') {
                throw new DomainException('Customer and company details are required.');
            }
        }
        $amounts = InvoiceAmounts::calculate($input['items'] ?? []);
        $items = $amounts['items'];
        unset($amounts['items']);
        $record = array_merge($amounts, [
            'client_id' => (int) $input['client_id'], 'branch_id' => (int) $input['branch_id'],
            'currency' => $input['currency'], 'issue_date' => $input['issue_date'], 'due_date' => $input['due_date'],
            'customer_snapshot' => $this->json($input['customer_snapshot']),
            'issuer_snapshot' => $this->json($input['issuer_snapshot']),
            'terms' => (string) ($input['terms'] ?? ''), 'created_by' => $actorId,
        ]);
        if (array_key_exists('shipment_details', $input)) {
            $record['shipment_details'] = $input['shipment_details'] ? $this->json($input['shipment_details']) : null;
        }
        return [$record, $items];
    }

    private function insertDraft(array $input, array $record, array $items, string $fingerprint, int $actorId): int
    {
        return $this->db->transaction(function () use ($input, $record, $items, $fingerprint, $actorId) {
            $existing = $this->db->table('billing_invoices')->where('request_key', $input['request_key'])->first();
            if ($existing) {
                $this->sameRequest($existing->request_fingerprint, $fingerprint);
                return (int) $existing->id;
            }
            $id = $this->db->table('billing_invoices')->insertGetId(array_merge($record, [
                'request_key' => $input['request_key'], 'request_fingerprint' => $fingerprint,
                'status' => 'draft', 'paid_minor' => 0, 'created_at' => $this->now(), 'updated_at' => $this->now(),
            ]));
            foreach ($items as $item) {
                $this->db->table('billing_invoice_items')->insert(array_merge($item, ['invoice_id' => $id]));
            }
            $this->event($id, $actorId, 'draft_created', ['total_minor' => $record['total_minor'], 'currency' => $record['currency']]);
            return (int) $id;
        }, 3);
    }

    public function updateDraft(int $id, array $input, int $actorId, int $revision): void
    {
        [$record, $items] = $this->prepareDraft($input, $actorId);
        unset($record['created_by']);
        $this->db->transaction(function () use ($id, $record, $items, $actorId, $revision) {
            $invoice = $this->locked($id);
            if ($invoice->status !== 'draft') throw new DomainException('Issued invoices cannot be edited.');
            if ((int) $invoice->revision !== $revision) throw new DomainException('This draft has changed. Reload it before editing.');
            $this->db->table('billing_invoices')->where('id', $id)->update($record + ['revision' => $revision + 1, 'updated_at' => $this->now()]);
            $this->db->table('billing_invoice_items')->where('invoice_id', $id)->delete();
            foreach ($items as $item) $this->db->table('billing_invoice_items')->insert($item + ['invoice_id' => $id]);
            $this->event($id, $actorId, 'draft_updated', ['before' => (array) $invoice, 'after' => $record, 'items' => $items]);
        }, 3);
    }

    public function void(int $id, int $actorId, string $reason): void
    {
        $this->actor($actorId);
        if (strlen(trim($reason)) < 5 || strlen($reason) > 1000) throw new DomainException('Enter a reason of 5 to 1000 characters.');
        $this->db->transaction(function () use ($id, $actorId, $reason) {
            $invoice = $this->locked($id);
            if ($invoice->status === 'void') return;
            if ((int) $invoice->paid_minor !== 0) throw new DomainException('An invoice with payments cannot be voided.');
            $this->db->table('billing_invoices')->where('id', $id)->update(['status' => 'void', 'updated_at' => $this->now()]);
            $this->event($id, $actorId, 'voided', ['reason' => trim($reason)]);
        }, 3);
    }

    public function issue(int $invoiceId, int $actorId): object
    {
        $this->actor($actorId);
        return $this->db->transaction(function () use ($invoiceId, $actorId) {
            $invoice = $this->locked($invoiceId);
            if ($invoice->issued_at && in_array($invoice->status, ['issued', 'partially_paid', 'paid'], true)) {
                return $invoice;
            }
            if ($invoice->status !== 'draft') {
                throw new DomainException('Only a draft invoice can be issued.');
            }
            $year = (int) substr($invoice->issue_date, 0, 4);
            $this->db->table('billing_sequences')->insertOrIgnore(['year' => $year, 'value' => 0]);
            $sequence = $this->db->table('billing_sequences')->where('year', $year)->lockForUpdate()->first();
            $value = (int) $sequence->value + 1;
            $this->db->table('billing_sequences')->where('year', $year)->update(['value' => $value]);
            $number = sprintf('NWC-INV-%04d-%06d', $year, $value);
            $this->db->table('billing_invoices')->where('id', $invoiceId)->update([
                'number' => $number, 'status' => 'issued', 'issued_at' => $this->now(),
                'issued_by' => $actorId, 'updated_at' => $this->now(),
            ]);
            $this->event($invoiceId, $actorId, 'issued', ['number' => $number]);
            return $this->locked($invoiceId);
        }, 3);
    }

    public function recordPayment(int $invoiceId, array $input, int $actorId): object
    {
        $this->actor($actorId);
        $this->key($input['request_key'] ?? '');
        $this->currency($input['currency'] ?? '');
        $amount = InvoiceAmounts::decimal($input['amount'] ?? '');
        $this->date($input['paid_on'] ?? '');
        if ($amount === 0 || $input['paid_on'] > now()->toDateString()) {
            throw new DomainException('Enter a received payment amount and a date no later than today.');
        }
        // Online settlement must use a verified gateway adapter, not a manual method flag.
        if (!in_array($input['method'] ?? '', ['cash', 'bank_transfer'], true)) {
            throw new DomainException('Select a supported received-payment method.');
        }
        $reference = trim((string) ($input['reference'] ?? ''));
        if (strlen($reference) > 120 || ($input['method'] !== 'cash' && $reference === '')) {
            throw new DomainException('A payment reference is required for non-cash payments.');
        }
        $record = [
            'invoice_id' => $invoiceId, 'amount_minor' => $amount, 'currency' => $input['currency'],
            'method' => $input['method'], 'reference' => $reference, 'paid_on' => $input['paid_on'], 'recorded_by' => $actorId,
        ];
        $fingerprint = hash('sha256', $this->json($record));
        return $this->db->transaction(function () use ($invoiceId, $input, $actorId, $record, $fingerprint, $amount) {
            $invoice = $this->locked($invoiceId);
            $existing = $this->db->table('billing_payments')->where('request_key', $input['request_key'])->first();
            if ($existing) {
                $this->sameRequest($existing->request_fingerprint, $fingerprint);
                return $existing;
            }
            if (!in_array($invoice->status, ['issued', 'partially_paid'], true)) {
                throw new DomainException('Payments can only be recorded against an issued, unpaid invoice.');
            }
            if ($invoice->currency !== $record['currency']) {
                throw new DomainException('Payment currency must match the invoice currency.');
            }
            if ($record['paid_on'] < $invoice->issue_date) {
                throw new DomainException('Payment date cannot precede the invoice date.');
            }
            if ($amount > (int) $invoice->total_minor - (int) $invoice->paid_minor) {
                throw new DomainException('The payment exceeds the outstanding balance.');
            }
            $paid = (int) $invoice->paid_minor + $amount;
            $balance = (int) $invoice->total_minor - $paid;
            $installment = $this->db->table('billing_payments')->where('invoice_id', $invoiceId)->count() + 1;
            $id = $this->db->table('billing_payments')->insertGetId(array_merge($record, [
                'request_key' => $input['request_key'], 'request_fingerprint' => $fingerprint,
                'receipt_number' => $invoice->number . '-R' . $installment, 'balance_after_minor' => $balance,
                'created_at' => $this->now(), 'updated_at' => $this->now(),
            ]));
            $this->db->table('billing_invoices')->where('id', $invoiceId)->update([
                'paid_minor' => $paid, 'status' => $balance === 0 ? 'paid' : 'partially_paid', 'updated_at' => $this->now(),
            ]);
            $this->event($invoiceId, $actorId, 'payment_recorded', [
                'payment_id' => $id, 'amount_minor' => $amount, 'balance_minor' => $balance, 'currency' => $record['currency'],
            ]);
            return $this->db->table('billing_payments')->where('id', $id)->first();
        }, 3);
    }

    public function summary(int $invoiceId, string $asOfDate): array
    {
        $this->date($asOfDate);
        $invoice = $this->db->table('billing_invoices')->where('id', $invoiceId)->first();
        if (!$invoice) throw new DomainException('Invoice not found.');
        $balance = (int) $invoice->total_minor - (int) $invoice->paid_minor;
        return [
            'status' => $invoice->status,
            'currency' => $invoice->currency,
            'total_minor' => (int) $invoice->total_minor,
            'paid_minor' => (int) $invoice->paid_minor,
            'balance_minor' => $balance,
            'overdue' => in_array($invoice->status, ['issued', 'partially_paid'], true)
                && $balance > 0 && $invoice->due_date < $asOfDate,
        ];
    }

    private function locked(int $id): object
    {
        $invoice = $this->db->table('billing_invoices')->where('id', $id)->lockForUpdate()->first();
        if (!$invoice) throw new DomainException('Invoice not found.');
        return $invoice;
    }

    private function event(int $id, int $actor, string $event, array $details): void
    {
        $this->db->table('billing_events')->insert([
            'invoice_id' => $id, 'actor_id' => $actor, 'event' => $event,
            'details' => $this->json($details), 'created_at' => $this->now(),
        ]);
    }

    private function sameRequest(string $existing, string $current): void
    {
        if (!hash_equals($existing, $current)) throw new DomainException('This request was already used with different details.');
    }

    private function actor(int $id): void
    {
        if ($id < 1) throw new DomainException('An authenticated staff member is required.');
    }

    private function key(string $key): void
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $key)) {
            throw new DomainException('A valid request identifier is required.');
        }
    }

    private function currency(string $currency): void
    {
        if (!in_array($currency, ['ZMW', 'USD'], true)) throw new DomainException('Unsupported invoice currency.');
    }

    private function date(string $date): void
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date || $date < '2000-01-01' || $date > '2099-12-31') {
            throw new DomainException('Enter a valid date.');
        }
    }

    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function now(): string
    {
        return now()->toDateTimeString();
    }
}
