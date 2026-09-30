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

    public function test_staff_collection_records_cashier_and_extra_charges_only_once_after_confirmation(): void
    {
        $invoice = $this->invoice();
        $invoice->online_payment_details = ['base_minor' => 9000, 'charges' => [['description' => 'Handling', 'amount_minor' => 1000, 'sort_order' => 0]]];
        $invoice->save();
        $this->pending();
        $actor = auth()->id();
        $this->mock(\Modules\Cargo\Services\ShipmentOperationAccessService::class, function ($mock) { $mock->shouldReceive('canOperate')->andReturn(true); });
        $intent = app(LipilaPayments::class)->create($invoice, (int) $invoice->shipment->client_id, ['method' => 'mobile-money', 'phone' => '260972827372'], $actor);
        $this->assertDatabaseCount('shipment_charge_lines', 0);
        $this->complete($intent); $this->complete($intent->fresh());
        $this->assertDatabaseCount('shipment_charge_lines', 1);
        $this->assertDatabaseHas('shipment_charge_lines', ['amount' => 10, 'currency' => 'ZMW']);
        $this->assertDatabaseHas('shipment_payment_receipts', ['user_id' => $actor, 'amount' => 100]);
        $this->assertEquals(90, \App\Models\NwcReceipt::where('shipment_id', $invoice->shipment_id)->firstOrFail()->bill_kwacha);
    }

    public function test_staff_endpoint_respects_permission_and_disabled_collection(): void
    {
        $invoice = $this->invoice();
        $this->mock(\Modules\Cargo\Services\ShipmentOperationAccessService::class, function ($mock) {
            $mock->shouldReceive('canOperate')->andReturn(false);
        });
        $this->postJson('/shipment-online-payment/' . $invoice->shipment_id, [])->assertForbidden();
        $this->getJson('/shipment-online-payment/' . $invoice->shipment_id)->assertForbidden();
        $this->mock(\Modules\Cargo\Services\ShipmentOperationAccessService::class, function ($mock) {
            $mock->shouldReceive('canOperate')->andReturn(true);
        });
        config(['lipila.enabled' => false]);
        $this->postJson('/shipment-online-payment/' . $invoice->shipment_id, [])->assertStatus(503);
        $this->assertDatabaseCount('customer_portal_payment_intents', 0);
    }

    public function test_staff_collection_requires_network_and_exactly_ten_local_digits(): void
    {
        $invoice = $this->invoice();
        $this->pending();
        $this->mock(\Modules\Cargo\Services\ShipmentOperationAccessService::class, function ($mock) { $mock->shouldReceive('canOperate')->andReturn(true); });
        $url = '/shipment-online-payment/' . $invoice->shipment_id;
        $payload = ['network' => 'mtn', 'phone' => '0972827372', 'idempotencyKey' => (string) \Illuminate\Support\Str::uuid(), 'final_total' => '100.00'];
        foreach (['097282737', '09728273721', '260972827372', '0972 827372', '0612345678', '097282737a'] as $phone) {
            $this->postJson($url, array_merge($payload, ['phone' => $phone]))->assertStatus(422)->assertJsonValidationErrors('phone');
        }
        $this->postJson($url, array_merge($payload, ['network' => null]))->assertStatus(422)->assertJsonValidationErrors('network');
        $this->postJson($url, array_merge($payload, ['network' => 'unknown']))->assertStatus(422)->assertJsonValidationErrors('network');
        $this->assertDatabaseCount('customer_portal_payment_intents', 0);
        Http::assertNothingSent();
    }

    public function test_staff_endpoint_resumes_existing_attempt_without_second_prompt(): void
    {
        $invoice = $this->invoice(); $this->pending();
        $this->mock(\Modules\Cargo\Services\ShipmentOperationAccessService::class, function ($mock) {
            $mock->shouldReceive('canOperate')->andReturn(true);
        });
        $payload = ['phone' => '0972827372', 'network' => 'mtn', 'idempotencyKey' => (string) \Illuminate\Support\Str::uuid(), 'final_total' => '100.00'];
        $url = '/shipment-online-payment/' . $invoice->shipment_id;
        $this->postJson($url, $payload)->assertCreated();
        $this->postJson($url, $payload)->assertCreated();
        $this->getJson($url)->assertOk()->assertJsonPath('canPrompt', false)->assertJsonPath('paid', false);
        $this->assertDatabaseCount('customer_portal_payment_intents', 1);
        $this->assertSame('mtn', PortalPaymentIntent::first()->billing_snapshot['network']);
        $this->assertCount(1, Http::recorded(fn ($request) => $request->method() === 'POST'));
    }

    public function test_staff_prepares_authoritative_bill_with_discount_and_fees_without_marking_paid(): void
    {
        $invoice = $this->invoice(); $shipment = $invoice->shipment;
        $invoice->delete(); $shipment->update(['amount_to_be_collected' => 5]);
        \App\Models\CurrencyExchangeRate::create(['from_currency' => 'USD', 'to_currency' => 'ZMW', 'exchange_rate' => 20]);
        $this->mock(\Modules\Cargo\Services\ShipmentOperationAccessService::class, function ($mock) { $mock->shouldReceive('canOperate')->andReturn(true); });
        $this->mock(\Modules\Cargo\Services\BranchAccessService::class, function ($mock) {
            $mock->shouldReceive('currencyFor')->andReturn('ZMW');
            $mock->shouldReceive('branchIdFor')->andReturn(null);
        });
        $bill = app(\Modules\Cargo\Services\StaffOnlinePaymentBill::class)->prepare($shipment->id, auth()->user(), [
            'final_total' => '108.00', 'discount_type' => 'percent', 'discount_value' => 10,
            'charges' => [['description' => 'Handling', 'amount' => '20.00']],
        ]);
        $this->assertEquals(108, $bill->total);
        $this->assertEquals(10000, $bill->online_payment_details['base_minor']);
        $this->assertSame('pending', $bill->status);
        $this->assertFalse((bool) $shipment->fresh()->paid);
        $this->assertDatabaseCount('shipment_payment_receipts', 0);
        $this->assertDatabaseCount('shipment_charge_lines', 0);
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

    public function test_same_key_replays_failure_without_charging_again_and_rejects_changed_details(): void
    {
        $invoice = $this->invoice(); $this->pending();
        $input = ['method' => 'mobile-money', 'phone' => '260970000001', 'idempotencyKey' => (string) \Illuminate\Support\Str::uuid()];
        $service = app(LipilaPayments::class);
        $intent = $service->create($invoice, $invoice->shipment->client_id, $input);
        $this->complete($intent, ['status' => 'Failed']);
        Http::swap(new \Illuminate\Http\Client\Factory()); Http::fake();
        $this->assertSame($intent->id, $service->create($invoice, $invoice->shipment->client_id, $input)->id);
        try { $service->create($invoice, $invoice->shipment->client_id, array_replace($input, ['phone' => '260970000002'])); $this->fail('Changed details must be rejected.'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('idempotencyKey', $e->errors()); }
        Http::assertNothingSent();
    }

    public function test_superseding_invoice_cannot_bypass_a_pending_attempt(): void
    {
        $invoice = $this->invoice(); $this->pending(); $this->start($invoice);
        $other = Transxn::create(['shipment_id' => $invoice->shipment_id, 'receipt_number' => 'REC-NEW', 'total' => 100, 'currency' => 'ZMW', 'status' => 'pending']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->start($other);
    }

    public function test_cashier_guard_blocks_unresolved_attempt(): void
    {
        $invoice = $this->invoice(); $this->pending(); $this->start($invoice);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\Modules\CustomerPortalApi\Services\PaymentAttemptGuard::class)->assertNoUnresolvedPayment($invoice->shipment_id);
    }

    public function test_reconciliation_continues_when_new_collections_are_disabled(): void
    {
        $invoice = $this->invoice(); $this->pending(); $intent = $this->start($invoice);
        config(['lipila.enabled' => false]); $this->complete($intent);
        $this->assertSame('succeeded', $intent->fresh()->status);
        $this->assertSame('completed', $invoice->fresh()->status);
    }

    public function test_new_owner_cannot_resume_old_owners_checkout(): void
    {
        $invoice = $this->invoice(); $this->pending(); $intent = $this->start($invoice);
        $invoice->shipment->update(['client_id' => 999]);
        $this->getJson('/api/v1/payments/intents/' . $intent->intent_id)->assertNotFound();
        $this->complete($intent);
        $this->assertSame('review', $intent->fresh()->status);
        $this->assertSame(0, ShipmentPaymentReceipt::count());
    }

    public function test_late_success_after_failure_requires_review_without_settlement(): void
    {
        $invoice = $this->invoice(); $this->pending(); $intent = $this->start($invoice);
        $this->complete($intent, ['status' => 'Failed']);
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*' => Http::response(['referenceId' => $intent->intent_id, 'status' => 'Successful', 'type' => 'Collection', 'currency' => 'ZMW', 'amount' => 100])]);
        app(LipilaPayments::class)->refresh($intent->fresh(), true);
        $this->assertSame('review', $intent->fresh()->status);
        $this->assertNotNull($intent->fresh()->review_reason);
        $this->assertSame(0, ShipmentPaymentReceipt::count());
    }

    public function test_callback_replay_records_one_event_and_one_receipt(): void
    {
        $invoice = $this->invoice(); $this->pending(); $intent = $this->start($invoice);
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*' => Http::response(['referenceId' => $intent->intent_id, 'status' => 'Successful', 'type' => 'Collection', 'currency' => 'ZMW', 'amount' => 100])]);
        $this->signedCallback(['referenceId' => $intent->intent_id])->assertOk();
        $this->signedCallback(['referenceId' => $intent->intent_id])->assertOk();
        $this->signedCallback(['referenceId' => $intent->intent_id, 'changed' => true])->assertStatus(409);
        $this->assertSame(1, ShipmentPaymentReceipt::count());
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('payment_webhook_events')->count());
        Http::assertSentCount(1);
    }

    public function test_callback_503_is_retryable_and_does_not_acknowledge_unverified_success(): void
    {
        $invoice = $this->invoice(); $this->pending(); $intent = $this->start($invoice);
        $this->signedCallback(['referenceId' => $intent->intent_id, 'status' => 'Successful'])->assertStatus(503);
        $this->assertNull(\Illuminate\Support\Facades\DB::table('payment_webhook_events')->value('processed_at'));
        $this->assertSame('pending', $invoice->fresh()->status);
    }

    private function signedCallback(array $data)
    {
        $body = json_encode($data); $timestamp = (string) time();
        $sig = 'v1,' . base64_encode(hash_hmac('sha256', 'test-event.' . $timestamp . '.' . $body, str_repeat('x', 32), true));
        return $this->call('POST', '/api/v1/payments/lipila/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_WEBHOOK_ID' => 'test-event', 'HTTP_WEBHOOK_TIMESTAMP' => $timestamp, 'HTTP_WEBHOOK_SIGNATURE' => $sig,
        ], $body);
    }

    /** @dataProvider invalidAmounts */
    public function test_money_parser_rejects_ambiguous_values($value): void
    {
        $this->assertNull(\Modules\CustomerPortalApi\Services\PaymentAttemptGuard::minorUnits($value));
    }

    public static function invalidAmounts(): array
    {
        return [[-1], ['1e2'], ['100.001'], [100.001], ['100,00'], [' 100'], [null], [true], [[]], ['NaN'], ['Infinity'], ['99999999999999999']];
    }

    public function test_extra_precision_provider_amount_does_not_round_into_success(): void
    {
        $invoice = $this->invoice(); $this->pending(); $intent = $this->start($invoice);
        $this->complete($intent, ['amount' => 100.001]);
        $this->assertSame('review', $intent->fresh()->status);
    }

    public function test_non_object_provider_response_leaves_attempt_unresolved(): void
    {
        $invoice = $this->invoice(); Http::fake(['*' => Http::response('"Successful"', 200)]);
        $intent = $this->start($invoice);
        $this->assertSame('processing', $intent->status);
        $this->assertSame('pending', $invoice->fresh()->status);
    }

    public function test_changing_environment_does_not_query_the_wrong_wallet(): void
    {
        $invoice = $this->invoice(); $this->pending(); $intent = $this->start($invoice);
        Http::swap(new \Illuminate\Http\Client\Factory()); Http::fake();
        config(['lipila.base_url' => 'https://api.lipila.dev']);
        $intent->update(['provider_environment' => 'https://api.lipila.io']);
        app(LipilaPayments::class)->refresh($intent);
        Http::assertNothingSent();
    }

    public function test_foreign_currency_receipts_block_new_collection_until_reviewed(): void
    {
        $invoice = $this->invoice(); $this->pending();
        ShipmentPaymentReceipt::create(['shipment_id' => $invoice->shipment_id, 'amount' => 10, 'currency' => 'USD', 'status' => 'active', 'refunded' => false, 'method_of_payment' => 'cash']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->start($invoice);
    }

    public function test_unconfigured_bridge_cannot_claim_payment_success(): void
    {
        $invoice = $this->invoice(); Http::fake(); config(['customerportalapi.payment_provider' => 'arbitrary-bridge']);
        $this->withoutMiddleware(\Modules\CustomerPortalApi\Http\Middleware\PortalCsrfMiddleware::class)
            ->postJson('/api/v1/payments/intents', ['invoiceId' => $invoice->id, 'method' => 'mobile-money'])->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_cashier_endpoint_refuses_payment_during_online_attempt(): void
    {
        $invoice = $this->invoice(); $this->pending(); $this->start($invoice);
        $this->mock(\Modules\Cargo\Services\ShipmentOperationAccessService::class, function ($mock) {
            $mock->shouldReceive('canOperate')->once()->andReturn(true);
        });
        $request = Request::create('/', 'POST', ['shipment_id' => $invoice->shipment_id, 'final_total' => 100,
            'method_of_payment' => ['cash'], 'payment_amount' => [100]]);
        $response = app(\Modules\Cargo\Http\Controllers\ShipmentController::class)->markAsPaid($request, app(\App\Services\AuditLogService::class));
        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('ONLINE_PAYMENT_PENDING', $response->getData(true)['error']);
        $this->assertSame(0, ShipmentPaymentReceipt::count());
        $this->assertSame(0, \Illuminate\Support\Facades\DB::transactionLevel());
    }

    public function test_audit_failure_rolls_back_receipt_invoice_and_shipment_together(): void
    {
        $invoice = $this->invoice(); $this->pending(); $intent = $this->start($invoice);
        $this->mock(\App\Services\AuditLogService::class, function ($mock) {
            $mock->shouldReceive('createLog')->andThrow(new \RuntimeException('Simulated audit outage'));
        });
        try { $this->complete($intent); $this->fail('Expected audit exception.'); }
        catch (\RuntimeException $e) { $this->assertSame('Simulated audit outage', $e->getMessage()); }
        $this->assertSame('pending', $invoice->fresh()->status);
        $this->assertSame('processing', $intent->fresh()->status);
        $this->assertSame(0, ShipmentPaymentReceipt::count());
        $this->assertEquals(0, $invoice->shipment->fresh()->paid);
    }

    public function test_client_amount_currency_and_success_flags_are_ignored(): void
    {
        $invoice = $this->invoice(); $this->pending();
        $this->withoutMiddleware(\Modules\CustomerPortalApi\Http\Middleware\PortalCsrfMiddleware::class)
            ->postJson('/api/v1/payments/intents', ['invoiceId' => $invoice->id, 'method' => 'mobile-money', 'phone' => '0970000001',
                'amountMinor' => 1, 'currency' => 'USD', 'status' => 'succeeded', 'paid' => true])
            ->assertCreated()->assertJsonPath('data.amount.amountMinor', 10000)->assertJsonPath('data.amount.currency', 'ZMW')
            ->assertJsonPath('data.status', 'processing');
        $this->assertSame('pending', $invoice->fresh()->status);
    }

    public function test_array_phone_payload_returns_validation_error_without_charge(): void
    {
        $invoice = $this->invoice(); Http::fake();
        $this->withoutMiddleware(\Modules\CustomerPortalApi\Http\Middleware\PortalCsrfMiddleware::class)
            ->postJson('/api/v1/payments/intents', ['invoiceId' => $invoice->id, 'method' => 'mobile-money', 'phone' => ['0970000001']])->assertStatus(422);
        Http::assertNothingSent();
    }
}
