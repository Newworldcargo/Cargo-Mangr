<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Cargo\Entities\Client;
use Modules\Cargo\Entities\Shipment;
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
            ->assertJsonMissingPath('data.remember_token')
            ->assertHeader('X-Request-ID');
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
            ->assertJsonMissingPath('data.customerId')
            ->assertJsonMissingPath('data.price')
            ->assertJsonPath('data.trackingNumber', 'PUBLIC-123');
    }

    public function test_unsafe_authenticated_requests_require_the_portal_csrf_header()
    {
        list($user) = $this->createCustomer('csrf@example.test');

        $response = $this->actingAs($user, 'web')->postJson('/api/v1/auth/logout');

        $response->assertStatus(419)
            ->assertJsonPath('error.code', 'CSRF_TOKEN_MISMATCH');
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
}
