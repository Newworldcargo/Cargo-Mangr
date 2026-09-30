<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Transxn;
use App\Models\ShipmentPaymentReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Modules\Cargo\Entities\Client;
use Modules\Cargo\Entities\Shipment;
use Modules\CustomerPortalApi\Models\PortalPaymentIntent;
use Modules\CustomerPortalApi\Services\LipilaGateway;
use Modules\CustomerPortalApi\Services\LipilaPayments;
use Tests\TestCase;

class LipilaPaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['lipila.enabled' => true, 'lipila.secret_key' => 'test-key', 'lipila.webhook_secret' => base64_encode(str_repeat('x', 32)), 'customerportalapi.payment_provider' => 'lipila']);
    }

    private function invoice(string $currency = 'ZMW'): Transxn
    {
        $user = User::create(['name' => 'Customer', 'email' => 'lipila@example.test', 'password' => bcrypt('test'), 'role' => 4, 'verified' => true]);
        $client = Client::create(['user_id' => $user->id, 'code' => 1, 'name' => 'Customer', 'email' => $user->email, 'responsible_mobile' => '000']);
        $this->actingAs($user, 'web');
        $shipment = Shipment::create(['client_id' => $client->id, 'code' => 'LIP-TEST', 'status_id' => Shipment::PENDING_STATUS, 'type' => Shipment::DROPOFF, 'shipping_date' => now()->toDateString()]);
        return Transxn::create(['shipment_id' => $shipment->id, 'receipt_number' => 'REC-LIP', 'total' => 100, 'currency' => $currency, 'status' => 'pending']);
    }

    private function start(Transxn $invoice): PortalPaymentIntent
    {
        return app(LipilaPayments::class)->create($invoice, (int) $invoice->shipment->client_id, ['method' => 'mobile-money', 'phone' => '260972827372']);
    }

    private function pending(): void
    {
        Http::fake(fn ($request) => Http::response(['referenceId' => $request['referenceId'], 'status' => 'Pending', 'identifier' => 'TEST-ID']));
    }

    private function complete(PortalPaymentIntent $intent, array $overrides = []): void
    {
        $intent->update(['last_checked_at' => null]);
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*' => Http::response(array_merge(['referenceId' => $intent->intent_id, 'status' => 'Successful', 'type' => 'Collection', 'currency' => 'ZMW', 'amount' => 100], $overrides))]);
        app(LipilaPayments::class)->refresh($intent);
    }

    public function test_pending_is_not_paid_and_repeat_attempts_do_not_collect_twice(): void
    {
        $invoice = $this->invoice(); $this->pending();
        $intent = $this->start($invoice);
        $second = $this->start($invoice);
        $this->assertSame($intent->id, $second->id);
        $this->assertSame('processing', $intent->status);
        $this->assertSame('pending', $invoice->fresh()->status);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['amount'] === 100 && $request['currency'] === 'ZMW' && $request['accountNumber'] === '260972827372');
    }

    public function test_confirmed_payment_records_receipt_and_settles_only_once(): void
    {
        $invoice = $this->invoice(); $this->pending(); $intent = $this->start($invoice);
        $this->complete($intent); $this->complete($intent->fresh());
        $this->assertSame('succeeded', $intent->fresh()->status);
        $this->assertSame('completed', $invoice->fresh()->status);
        $this->assertEquals(1, $invoice->shipment->fresh()->paid);
        $this->assertSame(1, ShipmentPaymentReceipt::count());
        $this->assertDatabaseHas('shipment_payment_receipts', ['amount' => 100, 'currency' => 'ZMW']);
        $this->getJson('/api/v1/shipments/' . $invoice->shipment_id . '/payments')->assertOk()->assertJsonPath('data.remaining.amountMinor', 0)->assertJsonCount(1, 'data.receipts');
    }

    public function test_wrong_amount_currency_or_concurrent_cash_payment_goes_to_review(): void
    {
        $invoice = $this->invoice(); $this->pending(); $intent = $this->start($invoice);
        $this->complete($intent, ['amount' => 99]);
        $this->assertSame('review', $intent->fresh()->status);
        $this->assertSame('pending', $invoice->fresh()->status);
        $this->assertSame(0, ShipmentPaymentReceipt::count());
    }

    public function test_changed_bill_is_not_overwritten_by_confirmation(): void
    {
        $invoice = $this->invoice(); $this->pending(); $intent = $this->start($invoice);
        $invoice->update(['status' => 'completed']);
        $this->complete($intent);
        $this->assertSame('review', $intent->fresh()->status);
        $this->assertSame(0, ShipmentPaymentReceipt::count());
    }

    public function test_unknown_response_does_not_allow_another_collection(): void
    {
        $invoice = $this->invoice(); Http::fake(['*' => Http::response([], 500)]);
        $intent = $this->start($invoice); $this->start($invoice);
        $this->assertSame('processing', $intent->status); Http::assertSentCount(2);
    }

    public function test_usd_mobile_money_and_part_paid_invoices_are_blocked(): void
    {
        $invoice = $this->invoice('USD'); Http::fake();
        try { $this->start($invoice); $this->fail('USD mobile money should be rejected.'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('method', $e->errors()); }
        $invoice->update(['currency' => 'ZMW', 'status' => 'partially_paid']);
        try { $this->start($invoice); $this->fail('Partial invoice should be rejected.'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('invoiceId', $e->errors()); }
        Http::assertNothingSent();
    }

    public function test_signature_requires_raw_body_correct_secret_and_recent_timestamp(): void
    {
        $body = '{"referenceId":"test"}'; $time = (string) time();
        $signature = 'v1,' . base64_encode(hash_hmac('sha256', 'event.' . $time . '.' . $body, str_repeat('x', 32), true));
        $request = Request::create('/', 'POST', [], [], [], [], $body);
        $request->headers->add(['webhook-id' => 'event', 'webhook-timestamp' => $time, 'webhook-signature' => 'v1,invalid ' . $signature]);
        $this->assertTrue(app(LipilaGateway::class)->verifyWebhook($request));
        $request->headers->set('webhook-timestamp', (string) (time() - 301));
        $this->assertFalse(app(LipilaGateway::class)->verifyWebhook($request));
        $this->postJson('/api/v1/payments/lipila/webhook', ['referenceId' => 'test'])->assertUnauthorized();
    }

    public function test_checkout_redirects_are_restricted_to_hosted_provider(): void
    {
        $gateway = app(LipilaGateway::class);
        $this->assertNull($gateway->checkoutUrl('https://checkout.primenetpay.com.evil.test/path'));
        $this->assertNull($gateway->checkoutUrl('javascript:alert(1)'));
        $this->assertSame('https://checkout.primenetpay.com/path', $gateway->checkoutUrl('https://checkout.primenetpay.com/path'));
    }

    public function test_card_uses_hosted_checkout_and_invoice_currency(): void
    {
        $invoice = $this->invoice('USD');
        Http::fake(fn ($request) => Http::response([
            'referenceId' => $request['collectionRequest']['referenceId'] ?? $request['referenceId'],
            'status' => 'Pending', 'cardRedirectionUrl' => 'https://checkout.primenetpay.com/test',
        ]));
        $intent = app(LipilaPayments::class)->create($invoice, (int) $invoice->shipment->client_id, [
            'method' => 'card', 'phone' => '263771234567',
            'billing' => ['firstName' => 'Test', 'lastName' => 'Customer', 'email' => 'test@example.test', 'city' => 'Harare', 'country' => 'ZW', 'address' => '1 Test Street', 'zip' => '00000'],
        ]);
        $this->assertSame('requires_action', $intent->status);
        $this->assertSame('https://checkout.primenetpay.com/test', $intent->checkout_url);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request['collectionRequest']['currency'] === 'USD'
            && $request['customerInfo']['phoneNumber'] === '263771234567'
            && $request['collectionRequest']['amount'] === 100);
    }

    public function test_confirmed_failure_allows_a_new_attempt_but_does_not_create_receipts(): void
    {
        $invoice = $this->invoice(); $this->pending(); $intent = $this->start($invoice);
        $this->complete($intent, ['status' => 'Failed']);
        $this->assertSame('failed', $intent->fresh()->status);
        Http::swap(new \Illuminate\Http\Client\Factory()); $this->pending();
        $next = $this->start($invoice);
        $this->assertNotSame($intent->intent_id, $next->intent_id);
        $this->assertSame(0, ShipmentPaymentReceipt::count());
    }

    public function test_wrong_currency_and_unrelated_reference_cannot_settle(): void
    {
        $invoice = $this->invoice(); $this->pending(); $intent = $this->start($invoice);
        $this->complete($intent, ['referenceId' => 'another-reference']);
        $this->assertSame('processing', $intent->fresh()->status);
        $this->complete($intent, ['currency' => 'USD']);
        $this->assertSame('review', $intent->fresh()->status);
        $this->assertSame('pending', $invoice->fresh()->status);
    }

    public function test_disabled_provider_does_not_start_a_collection(): void
    {
        $invoice = $this->invoice(); Http::fake(); config(['lipila.enabled' => false]);
        $this->withoutMiddleware(\Modules\CustomerPortalApi\Http\Middleware\PortalCsrfMiddleware::class)
            ->postJson('/api/v1/payments/intents', ['invoiceId' => $invoice->id, 'method' => 'mobile-money', 'phone' => '0972827372'])
            ->assertStatus(503)->assertJsonPath('error.code', 'PAYMENTS_UNAVAILABLE');
        Http::assertNothingSent();
    }

    public function test_api_normalizes_phone_and_resumes_existing_intent(): void
    {
        $invoice = $this->invoice(); $this->pending();
        $this->withoutMiddleware(\Modules\CustomerPortalApi\Http\Middleware\PortalCsrfMiddleware::class)
            ->postJson('/api/v1/payments/intents', ['invoiceId' => $invoice->id, 'method' => 'mobile-money', 'phone' => '0972827372'])
            ->assertCreated()->assertJsonPath('data.provider', 'lipila');
        $this->getJson('/api/v1/payments/invoices/' . $invoice->id . '/intent')->assertOk()->assertJsonPath('data.status', 'processing');
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['accountNumber'] === '260972827372');
    }

    public function test_another_customer_cannot_read_the_payment(): void
    {
        $invoice = $this->invoice(); $this->pending(); $intent = $this->start($invoice);
        $intent->update(['client_id' => 9999]);
        $this->getJson('/api/v1/payments/intents/' . $intent->intent_id)->assertNotFound();
    }
}
