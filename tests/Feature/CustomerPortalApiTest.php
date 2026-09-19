<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Transxn;
use App\Mail\OTPMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Modules\Cargo\Entities\Branch;
use Modules\Cargo\Entities\Client;
use Modules\Cargo\Entities\Country;
use Modules\Cargo\Entities\Package;
use Modules\Cargo\Entities\Shipment;
use Modules\Cargo\Entities\ShipmentSetting;
use Modules\Cargo\Entities\State;
use Modules\Cargo\Services\MobilePricingSettings;
use Modules\CustomerPortalApi\Models\PortalPaymentIntent;
use Tests\TestCase;

class CustomerPortalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_usd_quote_can_be_verified_with_the_original_mobile_request()
    {
        list($customer, $client) = $this->createCustomer('scheduled-quote@example.test');
        config(['customerportalapi.booking_pricing.currency' => 'USD']);
        ShipmentSetting::create(['key' => 'mobile_pricing_currency', 'value' => 'USD']);
        ShipmentSetting::create(['key' => 'mobile_pricing_local_base_fee', 'value' => '12']);
        ShipmentSetting::create(['key' => 'mobile_pricing_local_per_km', 'value' => '0']);
        $payload = [
            'service' => 'local', 'bookingType' => 'local_delivery',
            'pickup' => ['city' => 'Lusaka'], 'destination' => ['city' => 'Lusaka'],
            'schedule' => 'scheduled', 'scheduledAt' => now()->addDay()->toIso8601String(),
            'cargo' => ['items' => [], 'fragile' => false, 'packageType' => 'standard'],
        ];
        $response = $this->actingAs($customer, 'web')
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-Token', 'test-csrf-token')
            ->postJson('/api/v1/bookings/quote', $payload)
            ->assertCreated()->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.formattedTotal', 'USD 12.00');
        $pricing = [
            'request' => $payload,
            'quotePayload' => $response->json('data.quotePayload'),
            'quoteSignature' => $response->json('data.quoteSignature'),
            'quoteSource' => 'server',
        ];
        $signer = app(\Modules\CustomerPortalApi\Services\Pricing\MobileBookingQuoteSigner::class);
        $quote = $signer->requireValidQuote($pricing, $client->id, 'local');
        $this->assertSame('USD', $quote->currency);
        $pricing['request']['scheduledAt'] = now()->addDays(2)->toIso8601String();
        $this->expectException(\InvalidArgumentException::class);
        $signer->requireValidQuote($pricing, $client->id, 'local');
    }

    public function test_mobile_quotes_do_not_relabel_existing_non_usd_rates()
    {
        list($customer) = $this->createCustomer('currency-quote@example.test');
        ShipmentSetting::create(['key' => 'mobile_pricing_currency', 'value' => 'ZMW']);
        $this->actingAs($customer, 'web')
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-Token', 'test-csrf-token')
            ->postJson('/api/v1/bookings/quote', [
                'service' => 'local', 'bookingType' => 'local_delivery',
                'pickup' => ['city' => 'Lusaka'], 'destination' => ['city' => 'Lusaka'],
                'cargo' => ['fragile' => false],
            ])->assertStatus(503)->assertJsonPath('error.code', 'PRICING_NOT_CONFIGURED');
    }


    public function test_mobile_telemetry_accepts_allowlisted_redacted_events()
    {
        $this->postJson('/api/v1/telemetry/events', [
            'event' => 'app_opened',
            'appVersion' => '1.0.0',
            'platform' => 'android',
            'properties' => ['screen' => 'home'],
        ])->assertStatus(202);
    }

    public function test_mobile_telemetry_rejects_sensitive_property_names()
    {
        $this->postJson('/api/v1/telemetry/events', [
            'event' => 'api_error_occurred',
            'properties' => ['email' => 'customer@example.test'],
        ])->assertStatus(422)->assertJsonPath('error.code', 'SENSITIVE_TELEMETRY_REJECTED');
    }

    public function test_local_uat_payment_settles_once_and_reuses_the_intent()
    {
        list($user, $client) = $this->createCustomer('payment@example.test');
        $shipment = $this->createShipment($client, 'PAYMENT-123');
        $invoice = Transxn::create([
            'shipment_id' => $shipment->id,
            'receipt_number' => 'INV-UAT-1',
            'total' => 125,
            'currency' => 'ZMW',
            'status' => 'pending',
        ]);
        config(['customerportalapi.payment_provider' => 'local-uat']);

        $this->actingAs($user, 'web')->getJson('/api/v1/invoices/' . $invoice->id . '/document')
            ->assertOk()
            ->assertJsonPath('data.filename', 'new-worldcargo-invoice-inv-uat-1.html')
            ->assertJsonPath('data.mimeType', 'text/html;charset=utf-8');

        $request = fn () => $this->actingAs($user, 'web')
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-Token', 'test-csrf-token')
            ->postJson('/api/v1/payments/intents', ['invoiceId' => $invoice->id, 'method' => 'mobile-money']);

        $first = $request();
        $first->assertCreated()->assertJsonPath('data.status', 'succeeded');
        $this->actingAs($user, 'web')->getJson('/api/v1/invoices/' . $invoice->id)
            ->assertOk()->assertJsonPath('data.total.currency', 'ZMW');
        $request()->assertStatus(422)->assertJsonPath('error.code', 'INVOICE_NOT_PAYABLE');
        $this->assertSame('completed', $invoice->fresh()->status);
        $this->assertSame(1, PortalPaymentIntent::where('invoice_id', $invoice->id)->count());
        $this->actingAs($user, 'web')->getJson('/api/v1/invoices/' . $invoice->id . '/receipt-document')
            ->assertOk()
            ->assertJsonPath('data.filename', 'new-worldcargo-receipt-inv-uat-1.html');
    }

    public function test_customer_can_login_and_receive_the_portal_user_contract()
    {
        list($user) = $this->createCustomer('one@example.test');

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $user->id)
            ->assertJsonPath('data.email', $user->email)
            ->assertHeader('X-Request-ID');
        $this->assertArrayNotHasKey('remember_token', $response->json('data'));
    }

    public function test_customer_can_reset_password_with_a_single_use_otp()
    {
        Mail::fake();
        list($user) = $this->createCustomer('otp-reset@example.test');

        $this->postJson('/api/v1/auth/password/forgot', [
            'identifier' => $user->email,
        ])->assertOk();

        $code = (string) $user->fresh()->otp;
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $payload = [
            'identifier' => $user->email,
            'code' => $code,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ];
        $this->postJson('/api/v1/auth/password/reset', $payload)->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-123', $user->password));
        $this->assertNull($user->otp);
        $this->assertNull($user->otp_expires_at);
        $this->postJson('/api/v1/auth/password/reset', $payload)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'OTP_INVALID');
    }

    public function test_otp_email_renders_for_logged_out_password_recovery()
    {
        $html = (new OTPMail('482913', 'George Munganga'))->render();

        $this->assertStringContainsString('Hello, George Munganga', $html);
        $this->assertStringContainsString('482913', $html);
    }

    public function test_native_mobile_session_can_read_write_and_logout_without_browser_cookies()
    {
        list($user) = $this->createCustomer('native@example.test');

        $login = $this->withHeader('X-NWC-Mobile-Client', '1')->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'password',
        ]);

        $login->assertOk()
            ->assertJsonStructure(['meta' => ['mobileSession' => ['token', 'csrfToken', 'expiresAt']]]);
        $token = $login->json('meta.mobileSession.token');
        $csrf = $login->json('meta.mobileSession.csrfToken');
        $headers = ['X-NWC-Mobile-Client' => '1', 'Authorization' => 'Bearer ' . $token];

        $this->withHeaders($headers)->getJson('/api/v1/profile')
            ->assertOk()->assertJsonPath('data.email', $user->email);
        $this->withHeaders($headers)->patchJson('/api/v1/profile', ['firstName' => 'Native'])
            ->assertStatus(419)->assertJsonPath('error.code', 'CSRF_TOKEN_MISMATCH');
        $this->withHeaders($headers + ['X-CSRF-Token' => $csrf])
            ->patchJson('/api/v1/profile', ['firstName' => 'Native'])
            ->assertOk()->assertJsonPath('data.firstName', 'Native');
        $this->withHeaders($headers + ['X-CSRF-Token' => $csrf])
            ->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->withHeaders($headers)->getJson('/api/v1/profile')
            ->assertStatus(401)->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_native_mobile_can_upload_and_apply_a_profile_photo()
    {
        Storage::fake(config('filesystems.portal_disk', config('filesystems.default', 'local')));
        list($user) = $this->createCustomer('native-upload@example.test');
        $login = $this->withHeader('X-NWC-Mobile-Client', '1')->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'password',
        ]);
        $token = $login->json('meta.mobileSession.token');
        $csrf = $login->json('meta.mobileSession.csrfToken');
        $headers = ['X-NWC-Mobile-Client' => '1', 'Authorization' => 'Bearer ' . $token, 'X-CSRF-Token' => $csrf];

        $intent = $this->withHeaders($headers)->postJson('/api/v1/files/upload-intents', [
            'fileName' => 'avatar.jpg',
            'contentType' => 'image/jpeg',
            'sizeBytes' => 5,
            'purpose' => 'profile-photo',
        ]);
        $intent->assertCreated()->assertJsonPath('data.requiresPortalAuth', true);
        $fileId = $intent->json('data.fileId');

        $this->call('PUT', '/api/v1/files/' . $fileId . '/content', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_X_NWC_MOBILE_CLIENT' => '1',
            'HTTP_X_CSRF_TOKEN' => $csrf,
            'CONTENT_TYPE' => 'image/jpeg',
        ], 'photo')->assertNoContent();
        $this->withHeaders($headers)->postJson('/api/v1/files/' . $fileId . '/complete')
            ->assertStatus(202)->assertJsonPath('data.fileId', $fileId);
        $profile = $this->withHeaders($headers)->patchJson('/api/v1/profile', ['avatarFileId' => $fileId]);
        $profile->assertOk();
        $this->assertStringEndsWith('/api/v1/files/' . $fileId . '/download', $profile->json('data.avatar'));
    }

    public function test_customer_can_only_list_owned_shipments()
    {
        list($userA, $clientA) = $this->createCustomer('a@example.test');
        list(, $clientB) = $this->createCustomer('b@example.test');

        $this->createShipment($clientA, 'OWNED-A');
        $this->createShipment($clientB, 'PRIVATE-B');

        $response = $this->actingAs($userA, 'web')->getJson('/api/v1/shipments');

        $response->assertOk()
            ->assertJsonFragment(['trackingNumber' => 'OWNED-A'])
            ->assertJsonMissing(['trackingNumber' => 'PRIVATE-B']);
    }

    public function test_customer_cannot_read_another_customers_shipment_by_id()
    {
        list($userA) = $this->createCustomer('a2@example.test');
        list(, $clientB) = $this->createCustomer('b2@example.test');
        $shipment = $this->createShipment($clientB, 'PRIVATE-B2');

        $response = $this->actingAs($userA, 'web')->getJson('/api/v1/shipments/' . $shipment->id);

        $response->assertNotFound();
    }

    public function test_public_tracking_does_not_expose_private_customer_fields()
    {
        list(, $client) = $this->createCustomer('public@example.test');
        $this->createShipment($client, 'PUBLIC-123', [
            'amount_to_be_collected' => 120,
        ]);

        $response = $this->getJson('/api/v1/public/tracking/PUBLIC-123');

        $response->assertOk()
            ->assertJsonPath('data.trackingNumber', 'PUBLIC-123');
        $this->assertArrayNotHasKey('customerId', $response->json('data'));
        $this->assertArrayNotHasKey('price', $response->json('data'));
    }

    public function test_reference_data_exposes_configured_branch_geography_for_mobile_route_guards()
    {
        $branch = $this->createBranch();
        $country = Country::where('name', 'Zambia')->firstOrFail();
        $state = State::where('name', 'Lusaka')->firstOrFail();
        $branch->update(['country_id' => $country->id, 'state_id' => $state->id]);

        $this->getJson('/api/v1/reference-data')->assertOk()
            ->assertJsonPath('data.offices.0.country', 'Zambia')
            ->assertJsonPath('data.offices.0.countryCode', 'ZM')
            ->assertJsonPath('data.offices.0.city', 'Lusaka');
    }

    public function test_saved_places_preserve_map_coordinates()
    {
        list($user) = $this->createCustomer('places@example.test');
        $this->createBranch();

        $response = $this->actingAs($user, 'web')
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-Token', 'test-csrf-token')
            ->postJson('/api/v1/saved-places', [
                'label' => 'Home',
                'detail' => 'Roma, Lusaka',
                'city' => 'Lusaka',
                'country' => 'Zambia',
                'latitude' => -15.3665,
                'longitude' => 28.3206,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.city', 'Lusaka')
            ->assertJsonPath('data.country', 'Zambia')
            ->assertJsonPath('data.lat', -15.3665)
            ->assertJsonPath('data.lng', 28.3206);
    }

    public function test_unsafe_authenticated_requests_require_the_portal_csrf_header()
    {
        list($user) = $this->createCustomer('csrf@example.test');

        $response = $this->actingAs($user, 'web')->postJson('/api/v1/auth/logout');

        $response->assertStatus(419)
            ->assertJsonPath('error.code', 'CSRF_TOKEN_MISMATCH');
    }

    public function test_mobile_pricing_uses_enabled_branch_route_rates_and_rejects_reverse_route()
    {
        list($customer) = $this->createCustomer('route-pricing@example.test');
        $origin = $this->createBranch();
        $destinationUser = User::create([
            'name' => 'Kitwe Branch',
            'email' => 'kitwe-branch@example.test',
            'password' => Hash::make('password'),
            'role' => 3,
            'verified' => true,
        ]);
        $destination = Branch::create([
            'code' => 2,
            'user_id' => $destinationUser->id,
            'name' => 'Kitwe Branch',
            'email' => 'kitwe@example.test',
            'address' => 'Kitwe, Zambia',
            'is_archived' => 0,
        ]);
        $pricing = app(MobilePricingSettings::class);
        foreach ([
            'enabled' => 1,
            'base_fee' => 100,
            'per_km' => 0,
            'per_kg' => 5,
            'fragile_fee' => 7,
            'container_fee' => 20,
        ] as $field => $value) {
            ShipmentSetting::create([
                'key' => $pricing->routeKey('intercity', $origin->id, $destination->id, $field),
                'value' => $value,
            ]);
        }

        $payload = [
            'service' => 'intercity',
            'bookingType' => 'city_to_city',
            'pickup' => ['city' => 'Lusaka', 'branchId' => (string) $origin->id],
            'destination' => ['city' => 'Kitwe', 'branchId' => (string) $destination->id],
            'cargo' => ['items' => [], 'totalWeight' => 2, 'fragile' => true, 'packageType' => 'container'],
        ];
        $request = fn (array $body) => $this->actingAs($customer, 'web')
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-Token', 'test-csrf-token')
            ->postJson('/api/v1/bookings/quote', $body);

        $request($payload)->assertCreated()
            ->assertJsonPath('data.total', 137)
            ->assertJsonPath('data.breakdown.pricingStatus', 'priced');

        $payload['pickup']['branchId'] = (string) $destination->id;
        $payload['destination']['branchId'] = (string) $origin->id;
        $request($payload)->assertStatus(422)->assertJsonPath('error.code', 'UNSUPPORTED_ROUTE');
    }

    public function test_admin_can_store_mobile_pricing_without_schema_changes()
    {
        $admin = User::create([
            'name' => 'Pricing Admin',
            'email' => 'pricing-admin@example.test',
            'password' => Hash::make('password'),
            'role' => 1,
            'verified' => true,
        ]);
        $origin = $this->createBranch();
        $destinationUser = User::create([
            'name' => 'Destination Branch',
            'email' => 'destination-branch@example.test',
            'password' => Hash::make('password'),
            'role' => 3,
            'verified' => true,
        ]);
        $destination = Branch::create([
            'code' => 2,
            'user_id' => $destinationUser->id,
            'name' => 'Destination Branch',
            'email' => 'destination@example.test',
            'is_archived' => 0,
        ]);

        $this->actingAs($admin)->post(route('shipments.settings.fees.mobile-pricing.store'), [
            'currency' => 'USD',
            'pricing' => ['local_base_fee' => 55, 'local_per_km' => 6, 'local_per_kg' => 2],
            'intercity_routes' => [
                $origin->id => [
                    $destination->id => ['enabled' => 1, 'base_fee' => 100, 'per_km' => 2, 'per_kg' => 5],
                ],
            ],
        ])->assertRedirect();

        $pricing = app(MobilePricingSettings::class);
        $this->assertDatabaseHas('shipment_settings', ['key' => 'mobile_pricing_local_base_fee', 'value' => '55']);
        $this->assertDatabaseHas('shipment_settings', [
            'key' => $pricing->routeKey('intercity', $origin->id, $destination->id, 'enabled'),
            'value' => '1',
        ]);
    }

    public function test_customer_draft_submit_creates_pending_shipment_with_cargo_rows()
    {
        list($user, $client) = $this->createCustomer('draft-submit@example.test');
        $branch = $this->createBranch();
        Package::create(['name' => 'General cargo', 'cost' => 0]);
        config([
            'customerportalapi.booking_pricing.services.local.base_fee' => 55,
            'customerportalapi.booking_pricing.services.local.per_km' => 6,
            'customerportalapi.booking_pricing.services.local.per_kg' => 0,
        ]);

        $quoteRequest = [
            'service' => 'local',
            'bookingType' => 'local_delivery',
            'pickup' => ['city' => 'Lusaka', 'area' => 'Roma', 'latitude' => -15.3665, 'longitude' => 28.3206],
            'destination' => ['city' => 'Lusaka', 'area' => 'Longacres', 'latitude' => -15.4162, 'longitude' => 28.3074],
            'vehicleType' => 'scooter',
            'cargo' => ['items' => [], 'totalWeight' => 4, 'fragile' => false],
        ];
        $quoteResponse = $this->actingAs($user, 'web')
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-Token', 'test-csrf-token')
            ->postJson('/api/v1/bookings/quote', $quoteRequest);
        $quoteResponse->assertCreated()->assertJsonPath('data.source', 'server');

        $draftResponse = $this->actingAs($user, 'web')
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-Token', 'test-csrf-token')
            ->postJson('/api/v1/shipment-drafts', [
                'payload' => [
                    'service' => 'local',
                    'form' => [
                        'pickup' => 'Roma, Lusaka',
                        'destination' => 'Longacres, Lusaka',
                        'pickupBranchId' => (string) $branch->id,
                        'recipient' => 'George Munganga',
                        'phone' => '+260971000000',
                    ],
                    'cargoRows' => [
                        ['name' => 'Shoes', 'quantity' => 2, 'weight' => 3.5, 'amount' => '$42.50'],
                        ['name' => 'Phone case', 'quantity' => 1, 'weight' => 0.5, 'amount' => '7.50'],
                    ],
                    'pricing' => [
                        'request' => $quoteRequest,
                        'quotePayload' => $quoteResponse->json('data.quotePayload'),
                        'quoteSignature' => $quoteResponse->json('data.quoteSignature'),
                        'quoteSource' => 'server',
                    ],
                ],
            ]);

        $draftResponse->assertCreated();

        $submitResponse = $this->actingAs($user, 'web')
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-Token', 'test-csrf-token')
            ->postJson('/api/v1/shipment-drafts/' . $draftResponse->json('data.id') . '/submit');

        $submitResponse->assertCreated()
            ->assertJsonPath('data.customerId', (string) $client->id)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.packageName', 'Shoes');

        $shipment = Shipment::where('client_id', $client->id)->where('order_id', 'like', 'PORTAL-%')->firstOrFail();
        $this->assertSame($branch->id, (int) $shipment->branch_id);
        $this->assertSame(Shipment::REQUESTED_STATUS, (int) $shipment->status_id);
        $this->assertEquals(4.0, (float) $shipment->total_weight);
        $this->assertEquals((float) $quoteResponse->json('data.total'), (float) $shipment->shipping_cost);
        $this->assertEquals((float) $quoteResponse->json('data.total'), (float) $shipment->amount_to_be_collected);
        $this->assertCount(2, $shipment->packageShipments);
    }

    private function createCustomer($email)
    {
        $user = User::create([
            'name' => 'Portal Customer',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 4,
            'verified' => true,
            'responsible_mobile' => '+260970000000',
        ]);

        $client = Client::create([
            'code' => 0,
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'responsible_name' => $user->name,
            'responsible_mobile' => $user->responsible_mobile,
            'is_archived' => 0,
        ]);
        $client->code = $client->id;
        $client->save();

        return [$user, $client];
    }

    private function createShipment($client, $code, array $overrides = [])
    {
        return Shipment::create(array_merge([
            'client_id' => $client->id,
            'code' => $code,
            'status_id' => Shipment::SAVED_STATUS,
            'type' => Shipment::DROPOFF,
            'shipping_date' => now()->toDateString(),
            'client_phone' => $client->responsible_mobile,
            'from_country_id' => 1,
            'from_state_id' => 1,
            'to_country_id' => 1,
            'to_state_id' => 1,
        ], $overrides));
    }

    private function createBranch()
    {
        $country = Country::create([
            'name' => 'Zambia',
            'iso3' => 'ZMB',
            'iso2' => 'ZM',
            'latitude' => 0,
            'longitude' => 0,
            'covered' => 1,
        ]);
        State::create([
            'name' => 'Lusaka',
            'country_id' => $country->id,
            'covered' => 1,
        ]);
        $branchUser = User::create([
            'name' => 'Lusaka Branch',
            'email' => 'branch@example.test',
            'password' => Hash::make('password'),
            'role' => 3,
            'verified' => true,
        ]);

        return Branch::create([
            'code' => 1,
            'user_id' => $branchUser->id,
            'name' => 'Lusaka Branch',
            'email' => 'lusaka@example.test',
            'address' => 'Lusaka, Zambia',
            'is_archived' => 0,
        ]);
    }
}
