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
}
