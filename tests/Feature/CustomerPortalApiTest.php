<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Cargo\Entities\Branch;
use Modules\Cargo\Entities\Client;
use Modules\Cargo\Entities\Country;
use Modules\Cargo\Entities\Package;
use Modules\Cargo\Entities\Shipment;
use Modules\Cargo\Entities\State;
use Tests\TestCase;

class CustomerPortalApiTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_unsafe_authenticated_requests_require_the_portal_csrf_header()
    {
        list($user) = $this->createCustomer('csrf@example.test');

        $response = $this->actingAs($user, 'web')->postJson('/api/v1/auth/logout');

        $response->assertStatus(419)
            ->assertJsonPath('error.code', 'CSRF_TOKEN_MISMATCH');
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
                    'service' => 'import',
                    'form' => [
                        'pickup' => 'Guangzhou, China',
                        'destination' => 'Lusaka',
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
