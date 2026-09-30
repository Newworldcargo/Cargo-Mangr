<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Transxn;
use App\Models\ShipmentPaymentReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Cargo\Entities\Client;
use Modules\Cargo\Entities\Shipment;
use Tests\TestCase;

class ShipmentPaymentDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private function shipment(): Shipment
    {
        $user = User::create(['name' => 'Customer', 'email' => 'payments@example.test', 'password' => bcrypt('test'), 'role' => 4, 'verified' => true]);
        $client = Client::create(['user_id' => $user->id, 'code' => 1, 'name' => 'Customer', 'email' => $user->email, 'responsible_mobile' => '000']);
        $this->actingAs($user, 'web');
        return Shipment::create(['client_id' => $client->id, 'code' => 'PAY-TEST', 'status_id' => Shipment::PENDING_STATUS, 'type' => Shipment::DROPOFF, 'shipping_date' => now()->toDateString()]);
    }

    public function test_partial_installments_have_individual_downloads_and_remaining_balance(): void
    {
        $shipment = $this->shipment();
        Transxn::create(['shipment_id' => $shipment->id, 'receipt_number' => 'R-1', 'total' => 100, 'currency' => 'USD', 'status' => 'partially_paid']);
        $receipt = ShipmentPaymentReceipt::create(['shipment_id' => $shipment->id, 'receipt_number' => 'R-1-1', 'amount' => 30, 'currency' => 'USD', 'status' => 'active', 'method_of_payment' => 'cash_payment']);
        ShipmentPaymentReceipt::create(['shipment_id' => $shipment->id, 'receipt_number' => 'R-1-2', 'amount' => 20, 'currency' => 'USD', 'status' => 'active', 'method_of_payment' => 'cash_payment']);
        ShipmentPaymentReceipt::create(['shipment_id' => $shipment->id, 'receipt_number' => 'R-1-3', 'amount' => 50, 'currency' => 'USD', 'status' => 'voided_duplicate', 'method_of_payment' => 'cash_payment']);
        $this->getJson('/api/v1/shipments/' . $shipment->id . '/payments')->assertOk()
            ->assertJsonPath('data.status', 'Partially paid')->assertJsonPath('data.paid.amountMinor', 5000)
            ->assertJsonPath('data.remaining.amountMinor', 5000)->assertJsonCount(2, 'data.receipts');
        $this->getJson('/api/v1/shipments/' . $shipment->id . '/receipts/payment-' . $receipt->id)->assertOk()
            ->assertJsonPath('data.filename', 'new-world-cargo-receipt-payment-' . $receipt->id . '.html')
            ->assertSee('USD 30.00');
    }

    public function test_settled_legacy_transaction_has_receipt_without_installment_rows(): void
    {
        $shipment = $this->shipment();
        Transxn::create(['shipment_id' => $shipment->id, 'receipt_number' => 'R-2', 'total' => 100, 'currency' => 'ZMW', 'status' => 'completed']);
        $this->getJson('/api/v1/shipments/' . $shipment->id . '/payments')->assertOk()
            ->assertJsonPath('data.remaining.amountMinor', 0)->assertJsonPath('data.paid.amountMinor', 10000)->assertJsonCount(1, 'data.receipts');
    }

    public function test_currency_amounts_are_not_added_together_and_unpaid_has_no_receipt(): void
    {
        $shipment = $this->shipment();
        Transxn::create(['shipment_id' => $shipment->id, 'receipt_number' => 'R-3', 'total' => 100, 'currency' => 'USD', 'status' => 'pending']);
        $this->getJson('/api/v1/shipments/' . $shipment->id . '/payments')->assertOk()->assertJsonCount(0, 'data.receipts');
        ShipmentPaymentReceipt::create(['shipment_id' => $shipment->id, 'receipt_number' => 'R-3-1', 'amount' => 100, 'currency' => 'ZMW', 'status' => 'active', 'method_of_payment' => 'cash_payment']);
        $this->getJson('/api/v1/shipments/' . $shipment->id . '/payments')->assertOk()
            ->assertJsonPath('data.paid.amountMinor', 0)->assertJsonPath('data.remaining.amountMinor', 10000)
            ->assertJsonPath('data.receipts.0.amount.currency', 'ZMW');
    }

    public function test_other_customers_and_unknown_receipts_are_denied(): void
    {
        $shipment = $this->shipment();
        $this->getJson('/api/v1/shipments/' . $shipment->id . '/receipts/payment-999')->assertNotFound();
        $shipment->update(['client_id' => 999]);
        $this->getJson('/api/v1/shipments/' . $shipment->id . '/payments')->assertNotFound();
        $this->getJson('/api/v1/shipments/' . $shipment->id . '/receipts/payment-999')->assertNotFound();
    }

    private function enableCheckout(): void
    {
        $this->withSession(['_token' => 'checkout-test-token'])->withHeader('X-CSRF-Token', 'checkout-test-token');
        config(['customerportalapi.payment_provider' => 'lipila', 'lipila.enabled' => true,
            'lipila.secret_key' => 'test', 'lipila.webhook_secret' => base64_encode(str_repeat('x', 32))]);
        $this->mock(\Modules\Cargo\Services\BranchAccessService::class, function ($mock) {
            $mock->shouldReceive('currencyFor')->andReturn('ZMW');
            $mock->shouldReceive('branchIdFor')->andReturn(null);
        });
        \App\Models\CurrencyExchangeRate::create(['from_currency' => 'USD', 'to_currency' => 'ZMW', 'exchange_rate' => 19.82]);
    }

    public function test_customer_prepares_missing_invoice_from_stored_price_without_charging(): void
    {
        $this->enableCheckout(); $shipment = $this->shipment(); $shipment->update(['amount_to_be_collected' => 2]);
        \Illuminate\Support\Facades\Http::fake();
        $url = '/api/v1/shipments/' . $shipment->id . '/payments';
        $this->getJson($url)->assertOk()->assertJsonPath('data.canPrepareCheckout', true)->assertJsonPath('data.remaining.amountMinor', 3964)->assertJsonPath('data.invoiceId', null);
        $this->assertDatabaseCount('transxns', 0);
        $first = $this->postJson($url . '/checkout', ['total' => 1, 'currency' => 'USD'])->assertOk()->assertJsonPath('data.checkoutMessage', null)->assertJsonPath('data.total.amountMinor', 3964);
        $this->postJson($url . '/checkout')->assertOk()->assertJsonPath('data.invoiceId', $first->json('data.invoiceId'));
        $this->assertDatabaseCount('transxns', 1);
        $this->assertDatabaseCount('shipment_payment_receipts', 0);
        $this->assertDatabaseCount('customer_portal_payment_intents', 0);
        $this->assertFalse((bool) $shipment->fresh()->paid);
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_customer_bill_includes_existing_charges_without_scheduling_duplicate_lines(): void
    {
        $this->enableCheckout(); $shipment = $this->shipment(); $shipment->update(['amount_to_be_collected' => 2]);
        \App\Models\ShipmentChargeLine::create(['shipment_id' => $shipment->id, 'description' => 'Handling', 'amount' => 10, 'currency' => 'ZMW']);
        $this->postJson('/api/v1/shipments/' . $shipment->id . '/payments/checkout')->assertOk()->assertJsonPath('data.total.amountMinor', 4964);
        $this->assertSame([], Transxn::first()->online_payment_details['charges']);
        $this->assertDatabaseCount('shipment_charge_lines', 1);
    }

    public function test_checkout_does_not_invent_unpriced_bills_or_overwrite_legacy_payments(): void
    {
        $this->enableCheckout(); $shipment = $this->shipment();
        $url = '/api/v1/shipments/' . $shipment->id . '/payments/checkout';
        $this->postJson($url)->assertStatus(422);
        $shipment->update(['amount_to_be_collected' => 2]);
        \App\Models\NwcReceipt::create(['shipment_id' => $shipment->id, 'receipt_number' => 'OLD', 'bill_kwacha' => 39.64]);
        $this->postJson($url)->assertStatus(422);
        $this->assertDatabaseCount('transxns', 0);
    }

    public function test_prepare_checkout_rejects_other_customer_and_paid_shipment(): void
    {
        $this->enableCheckout(); $shipment = $this->shipment(); $shipment->update(['amount_to_be_collected' => 2, 'paid' => 1]);
        $url = '/api/v1/shipments/' . $shipment->id . '/payments/checkout';
        $this->postJson($url)->assertStatus(409);
        $shipment->update(['paid' => 0, 'client_id' => 999]);
        $this->postJson($url)->assertNotFound();
        $this->assertDatabaseCount('transxns', 0);
    }

    public function test_prepare_checkout_keeps_existing_invoice_currency_and_amount(): void
    {
        $this->enableCheckout(); $shipment = $this->shipment(); $shipment->update(['amount_to_be_collected' => 2]);
        $invoice = Transxn::create(['shipment_id' => $shipment->id, 'receipt_number' => 'EXISTING', 'currency' => 'USD', 'total' => 7, 'status' => 'pending']);
        $this->postJson('/api/v1/shipments/' . $shipment->id . '/payments/checkout')->assertOk()->assertJsonPath('data.invoiceId', (string) $invoice->id)
            ->assertJsonPath('data.total.currency', 'USD')->assertJsonPath('data.total.amountMinor', 700);
        $this->assertDatabaseCount('transxns', 1);
    }

    public function test_checkout_needs_exchange_rate_and_rolls_back_if_audit_fails(): void
    {
        $this->enableCheckout(); $shipment = $this->shipment(); $shipment->update(['amount_to_be_collected' => 2]);
        \App\Models\CurrencyExchangeRate::query()->delete();
        $url = '/api/v1/shipments/' . $shipment->id . '/payments/checkout';
        $this->postJson($url)->assertStatus(422);
        $this->assertDatabaseCount('transxns', 0);
        \App\Models\CurrencyExchangeRate::create(['from_currency' => 'USD', 'to_currency' => 'ZMW', 'exchange_rate' => 19.82]);
        $this->mock(\App\Services\AuditLogService::class, function ($mock) { $mock->shouldReceive('createLog')->andThrow(new \RuntimeException('Audit unavailable')); });
        $this->postJson($url)->assertStatus(500);
        $this->assertDatabaseCount('transxns', 0);
    }
}
