<?php

namespace Tests\Unit;

use DomainException;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Support\Facades\Schema;
use Modules\Billing\Services\InvoiceAmounts;
use Modules\Billing\Services\InvoiceLedger;
use PHPUnit\Framework\TestCase;

class BillingLedgerTest extends TestCase
{
    private $db;
    private InvoiceLedger $ledger;
    private $previousFacadeApplication;

    protected function setUp(): void
    {
        parent::setUp();
        // No application bootstrap or production connection is used by these tests.
        $capsule = new Manager();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]);
        $this->db = $capsule->getConnection();
        $this->previousFacadeApplication = Schema::getFacadeApplication();
        $capsule->getContainer()->instance('db', $capsule->getDatabaseManager());
        Schema::setFacadeApplication($capsule->getContainer());
        require_once __DIR__ . '/../../Modules/Billing/Database/Migrations/2026_10_19_000001_create_billing_ledger.php';
        (new \CreateBillingLedger())->up();
        require_once __DIR__ . '/../../Modules/Billing/Database/Migrations/2026_10_19_000002_add_sales_documents.php';
        (new \AddSalesDocuments())->up();
        require_once __DIR__ . '/../../Modules/Billing/Database/Migrations/2026_10_20_000001_add_sales_shipment_details.php';
        (new \AddSalesShipmentDetails())->up();
        $this->ledger = new InvoiceLedger($this->db);
    }

    protected function tearDown(): void
    {
        Schema::setFacadeApplication($this->previousFacadeApplication);
        $this->db->disconnect();
        parent::tearDown();
    }

    private function draft(array $overrides = []): array
    {
        return array_replace([
            'request_key' => '00000000-0000-4000-8000-000000000001',
            'client_id' => 7, 'branch_id' => 2, 'currency' => 'ZMW',
            'issue_date' => '2026-01-01', 'due_date' => '2026-01-31',
            'customer_snapshot' => ['name' => 'Test Customer'],
            'issuer_snapshot' => ['name' => 'New World Cargo'],
            'items' => [['description' => 'Delivery', 'quantity' => '1', 'unit_price' => '100']],
        ], $overrides);
    }

    private function payment(array $overrides = []): array
    {
        return array_replace([
            'request_key' => '00000000-0000-4000-8000-000000000002',
            'amount' => '40.00', 'currency' => 'ZMW', 'method' => 'cash', 'paid_on' => '2026-01-02',
        ], $overrides);
    }

    public function test_draft_issue_installments_and_receipt_balances(): void
    {
        $id = $this->ledger->createDraft($this->draft(), 3);
        $this->assertSame('draft', $this->db->table('billing_invoices')->find($id)->status);
        $issued = $this->ledger->issue($id, 4);
        $this->assertSame('NWC-INV-2026-000001', $issued->number);
        $this->assertSame($issued->number, $this->ledger->issue($id, 4)->number);
        $first = $this->ledger->recordPayment($id, $this->payment(), 5);
        $this->assertSame(6000, (int) $first->balance_after_minor);
        $this->assertSame('partially_paid', $this->db->table('billing_invoices')->find($id)->status);
        $second = $this->ledger->recordPayment($id, $this->payment([
            'amount' => '60', 'request_key' => '00000000-0000-4000-8000-000000000003', 'paid_on' => '2026-01-03',
        ]), 5);
        $this->assertSame(0, (int) $second->balance_after_minor);
        $this->assertNotSame($first->receipt_number, $second->receipt_number);
        $invoice = $this->db->table('billing_invoices')->find($id);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(10000, (int) $invoice->paid_minor);
        $this->assertSame(4, $this->db->table('billing_events')->count());
        $this->assertSame('2026-01-02', $first->paid_on);
        $this->assertSame('2026-01-03', $second->paid_on);
    }

    public function test_replayed_requests_do_not_duplicate_records(): void
    {
        $id = $this->ledger->createDraft($this->draft(), 3);
        $this->assertSame($id, $this->ledger->createDraft($this->draft(), 3));
        $this->ledger->issue($id, 3);
        $payment = $this->ledger->recordPayment($id, $this->payment(['amount' => '100']), 3);
        $replay = $this->ledger->recordPayment($id, $this->payment(['amount' => '100']), 3);
        $this->assertSame($payment->id, $replay->id);
        $this->assertSame(1, $this->db->table('billing_payments')->count());
        $this->assertSame(3, $this->db->table('billing_events')->count());
    }

    public function test_changed_payment_cannot_reuse_a_request_key(): void
    {
        $id = $this->ledger->createDraft($this->draft(), 3);
        $this->ledger->issue($id, 3);
        $this->ledger->recordPayment($id, $this->payment(), 3);
        $this->expectException(DomainException::class);
        $this->ledger->recordPayment($id, $this->payment(['amount' => '50']), 3);
    }

    public function test_changed_draft_cannot_reuse_a_request_key(): void
    {
        $this->ledger->createDraft($this->draft(), 3);
        $this->expectException(DomainException::class);
        $this->ledger->createDraft($this->draft(['client_id' => 9]), 3);
    }

    /** @dataProvider invalidPayments */
    public function test_invalid_payments_leave_the_ledger_unchanged(array $input): void
    {
        $id = $this->ledger->createDraft($this->draft(), 3);
        $this->ledger->issue($id, 3);
        try {
            $this->ledger->recordPayment($id, $this->payment($input), 3);
            $this->fail('Invalid payment accepted.');
        } catch (DomainException $error) {
            $this->assertSame(0, $this->db->table('billing_payments')->count());
            $this->assertSame(0, (int) $this->db->table('billing_invoices')->find($id)->paid_minor);
            $this->assertSame(2, $this->db->table('billing_events')->count());
        }
    }

    public static function invalidPayments(): array
    {
        return [
            'overpayment' => [['amount' => '100.01']], 'zero' => [['amount' => '0']],
            'negative' => [['amount' => '-1']], 'precision' => [['amount' => '1.001']],
            'floating point' => [['amount' => 0.1]], 'wrong currency' => [['currency' => 'USD']],
            'future date' => [['paid_on' => '2099-01-01']], 'impossible date' => [['paid_on' => '2026-02-30']],
            'before invoice' => [['paid_on' => '2025-12-31']], 'missing reference' => [['method' => 'bank_transfer']],
            'unverified online' => [['method' => 'mobile_money']], 'invalid key' => [['request_key' => 'invalid']],
        ];
    }

    public function test_drafts_cannot_receive_payments(): void
    {
        $id = $this->ledger->createDraft($this->draft(), 3);
        $this->expectException(DomainException::class);
        $this->ledger->recordPayment($id, $this->payment(), 3);
    }

    public function test_audit_failure_rolls_back_payment_and_balance(): void
    {
        $id = $this->ledger->createDraft($this->draft(), 3);
        $this->ledger->issue($id, 3);
        $this->db->statement("CREATE TRIGGER reject_payment_audit BEFORE INSERT ON billing_events WHEN NEW.event = 'payment_recorded' BEGIN SELECT RAISE(ABORT, 'test audit failure'); END");
        try {
            $this->ledger->recordPayment($id, $this->payment(), 3);
            $this->fail('Audit failure should roll back.');
        } catch (\Illuminate\Database\QueryException $error) {
            $this->assertSame(0, $this->db->table('billing_payments')->count());
            $this->assertSame(0, (int) $this->db->table('billing_invoices')->find($id)->paid_minor);
            $this->assertSame('issued', $this->db->table('billing_invoices')->find($id)->status);
        }
    }

    public function test_totals_use_integer_rounding_and_tax_after_discount(): void
    {
        $result = InvoiceAmounts::calculate([
            ['description' => 'Delivery', 'quantity' => '1.25', 'unit_price' => '10.01', 'discount' => '1.00', 'tax_percent' => '16'],
        ]);
        $this->assertSame(1251, $result['subtotal_minor']);
        $this->assertSame(100, $result['discount_minor']);
        $this->assertSame(184, $result['tax_minor']);
        $this->assertSame(1335, $result['total_minor']);
    }

    public function test_separate_invoices_have_distinct_numbers_and_unchanged_snapshots(): void
    {
        $id = $this->ledger->createDraft($this->draft(), 3);
        $first = $this->ledger->issue($id, 3);
        $secondId = $this->ledger->createDraft($this->draft([
            'request_key' => '00000000-0000-4000-8000-000000000004',
            'customer_snapshot' => ['name' => 'Changed Customer'],
        ]), 3);
        $second = $this->ledger->issue($secondId, 3);
        $this->assertNotSame($first->number, $second->number);
        $this->assertSame('Test Customer', json_decode($this->db->table('billing_invoices')->find($id)->customer_snapshot, true)['name']);
    }

    public function test_overdue_is_derived_without_replacing_partial_payment_status(): void
    {
        $id = $this->ledger->createDraft($this->draft(), 3);
        $this->assertFalse($this->ledger->summary($id, '2026-02-01')['overdue']);
        $this->ledger->issue($id, 3);
        $this->assertFalse($this->ledger->summary($id, '2026-01-31')['overdue']);
        $this->assertTrue($this->ledger->summary($id, '2026-02-01')['overdue']);
        $this->ledger->recordPayment($id, $this->payment(), 3);
        $summary = $this->ledger->summary($id, '2026-02-01');
        $this->assertTrue($summary['overdue']);
        $this->assertSame('partially_paid', $summary['status']);
        $this->assertSame(6000, $summary['balance_minor']);
        $this->ledger->recordPayment($id, $this->payment([
            'amount' => '60', 'request_key' => '00000000-0000-4000-8000-000000000003',
        ]), 3);
        $this->assertFalse($this->ledger->summary($id, '2026-02-01')['overdue']);
    }

    /** @dataProvider invalidItems */
    public function test_invalid_line_items_are_rejected(array $items): void
    {
        $this->expectException(DomainException::class);
        InvoiceAmounts::calculate($items);
    }

    public static function invalidItems(): array
    {
        $item = ['description' => 'Delivery', 'quantity' => '1', 'unit_price' => '100'];
        return [
            'empty' => [[]], 'too many' => [array_fill(0, 101, $item)],
            'missing description' => [[array_replace($item, ['description' => ''])]],
            'zero quantity' => [[array_replace($item, ['quantity' => '0'])]],
            'excess discount' => [[array_replace($item, ['discount' => '101'])]],
            'zero total' => [[array_replace($item, ['discount' => '100'])]],
            'invalid tax' => [[array_replace($item, ['tax_percent' => '100.01'])]],
            'overflow' => [[array_replace($item, ['quantity' => '10000', 'unit_price' => '999999999'])]],
        ];
    }
}
