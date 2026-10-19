<?php

namespace Modules\Billing\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class QuotationLedger
{
    public function save(array $input, int $actor, ?int $id = null, int $revision = 1): int
    {
        [$record, $items] = app(InvoiceLedger::class)->prepareDraft($input, $actor);
        $fingerprint = hash('sha256', json_encode([$record, $items], JSON_THROW_ON_ERROR));
        return DB::transaction(function () use ($input, $actor, $id, $revision, $record, $items, $fingerprint) {
            if ($id) {
                $old = DB::table('billing_quotations')->where('id', $id)->lockForUpdate()->first();
                if (!$old || $old->status !== 'draft') throw new DomainException('Only draft quotations can be edited.');
                if ((int) $old->revision !== $revision) throw new DomainException('This quotation has changed. Reload it before editing.');
                unset($record['created_by']);
                DB::table('billing_quotations')->where('id', $id)->update($record + [
                    'input' => json_encode($input, JSON_THROW_ON_ERROR), 'items' => json_encode($items, JSON_THROW_ON_ERROR),
                    'revision' => $revision + 1, 'updated_at' => now(),
                ]);
                $this->event($id, $actor, 'draft_updated', ['before' => (array) $old, 'after' => $record, 'items' => $items]);
                return $id;
            }
            $existing = DB::table('billing_quotations')->where('request_key', $input['request_key'])->first();
            if ($existing) {
                if (!hash_equals($existing->fingerprint, $fingerprint)) throw new DomainException('This request was already used with different details.');
                return (int) $existing->id;
            }
            $id = DB::table('billing_quotations')->insertGetId($record + [
                'request_key' => $input['request_key'], 'fingerprint' => $fingerprint,
                'input' => json_encode($input, JSON_THROW_ON_ERROR), 'items' => json_encode($items, JSON_THROW_ON_ERROR),
                'status' => 'draft', 'revision' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->event($id, $actor, 'draft_created', ['total_minor' => $record['total_minor']]);
            return $id;
        }, 3);
    }

    public function transition(int $id, string $action, int $actor, string $note = ''): ?int
    {
        return DB::transaction(function () use ($id, $action, $actor, $note) {
            $quote = DB::table('billing_quotations')->where('id', $id)->lockForUpdate()->first();
            if (!$quote) throw new DomainException('Quotation not found.');
            if ($action === 'convert' && $quote->converted_invoice_id) return (int) $quote->converted_invoice_id;
            $allowed = ['issue' => ['draft'], 'accept' => ['issued'], 'decline' => ['issued'], 'convert' => ['accepted']];
            if (!isset($allowed[$action]) || !in_array($quote->status, $allowed[$action], true)) throw new DomainException('This action is not available for this quotation.');
            if (in_array($action, ['accept', 'convert'], true) && $quote->due_date < now()->toDateString()) throw new DomainException('This quotation has expired. Create a new quotation.');
            if (in_array($action, ['accept', 'decline'], true) && strlen(trim($note)) < 5) throw new DomainException('Record how and when the customer confirmed their decision.');
            $changes = ['updated_at' => now()];
            if ($action === 'issue') {
                $changes += ['status' => 'issued', 'number' => sprintf('NWC-QT-%s-%06d', substr($quote->issue_date, 0, 4), $id)];
            } elseif ($action === 'convert') {
                $input = json_decode($quote->input, true, 512, JSON_THROW_ON_ERROR);
                $input['request_key'] = (string) Str::uuid();
                $input['issue_date'] = now()->toDateString();
                $input['due_date'] = now()->addDays(14)->toDateString();
                $invoiceId = app(InvoiceLedger::class)->createDraft($input, $actor);
                $changes += ['status' => 'converted', 'converted_invoice_id' => $invoiceId];
            } else {
                $changes['status'] = $action === 'accept' ? 'accepted' : 'declined';
            }
            DB::table('billing_quotations')->where('id', $id)->update($changes);
            $this->event($id, $actor, $action, ['note' => $note, 'invoice_id' => $invoiceId ?? null]);
            return $invoiceId ?? null;
        }, 3);
    }

    private function event(int $id, int $actor, string $event, array $details): void
    {
        DB::table('billing_quotation_events')->insert([
            'quotation_id' => $id, 'actor_id' => $actor, 'event' => $event,
            'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }
}
