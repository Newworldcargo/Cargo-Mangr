<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Cargo\Entities\Client;
use Modules\CustomerPortalApi\Services\Portal\RegistrationPhone;
use Tests\TestCase;

class RegistrationPhoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_formats_resolve_to_one_registration_identity(): void
    {
        foreach (['0970000000', '970000000', '+260 970 000 000', '260970000000', '00260970000000'] as $value) {
            $this->assertSame('260970000000', RegistrationPhone::normalise($value));
        }
        foreach (['33', 'hello', '0970000000 ext 2'] as $value) $this->assertNull(RegistrationPhone::normalise($value));
    }

    public function test_signup_rejects_primary_and_secondary_numbers_without_creating_accounts(): void
    {
        Mail::fake();
        User::create(['name' => 'Existing', 'email' => 'existing@example.test', 'password' => bcrypt('test'),
            'role' => 4, 'responsible_mobile' => '+260970000000', 'secondary_mobile' => '0960000000']);
        foreach (['0970000000', '+260 970 000 000', '+260960000000'] as $index => $phone) {
            $this->withSession(['_token' => 'test-csrf-token'])->withHeader('X-CSRF-Token', 'test-csrf-token')
                ->postJson('/api/v1/auth/register', ['firstName' => 'New customer', 'email' => "new{$index}@example.test", 'phone' => $phone, 'password' => 'test-password-123'])
                ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
            $this->assertDatabaseMissing('users', ['email' => "new{$index}@example.test"]);
        }
        Mail::assertNothingSent();
    }

    public function test_profile_only_number_is_reserved_and_unused_number_is_available(): void
    {
        $user = User::create(['name' => 'Existing', 'email' => 'existing@example.test', 'password' => bcrypt('test'), 'role' => 4]);
        Client::create(['code' => 1, 'user_id' => $user->id, 'name' => 'Existing', 'email' => $user->email, 'responsible_mobile' => '0970000000']);
        $this->assertTrue(app(RegistrationPhone::class)->exists('260970000000'));
        $this->assertFalse(app(RegistrationPhone::class)->exists('260970000001'));
    }

    public function test_unused_phone_can_still_register_with_required_email(): void
    {
        Mail::fake();
        $this->withSession(['_token' => 'test-csrf-token'])->withHeader('X-CSRF-Token', 'test-csrf-token')
            ->postJson('/api/v1/auth/register', ['firstName' => 'New customer', 'email' => 'new@example.test',
                'phone' => '+260970000001', 'password' => 'test-password-123'])->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'new@example.test', 'responsible_mobile' => '+260970000001', 'verified' => false]);
        $this->assertDatabaseHas('clients', ['email' => 'new@example.test', 'responsible_mobile' => '+260970000001']);
    }
}
