<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Cargo\Entities\Client;
use Modules\CustomerPortalApi\Services\Portal\PortalEmailRequirement;
use Tests\TestCase;

class PortalEmailRequirementTest extends TestCase
{
    use RefreshDatabase;

    private function customer(string $email): User
    {
        $user = User::create(['name' => 'Customer', 'email' => $email, 'responsible_mobile' => '+260970000000',
            'password' => Hash::make('Password123'), 'role' => 4, 'verified' => true]);
        Client::create(['code' => $user->id, 'user_id' => $user->id, 'name' => $user->name, 'email' => $email, 'is_archived' => 0]);
        return $user;
    }

    public function test_email_policy_distinguishes_missing_placeholders_and_real_domains(): void
    {
        $policy = new PortalEmailRequirement();
        foreach (['', ' ', 'not-an-email', 'imported+260970000000@newworldcargo.invalid', 'nobody@invalid'] as $email) {
            $this->assertTrue($policy->needsRecovery(new User(['email' => $email])));
        }
        foreach (['peter@mail.com', 'peter@gmail.com', 'STAFF@NEWWORLDCARGO.COM'] as $email) {
            $this->assertFalse($policy->needsRecovery(new User(['email' => $email])));
        }
    }

    public function test_phone_login_prompts_for_email_only_after_correct_password(): void
    {
        $user = $this->customer('imported+test@newworldcargo.invalid');
        $this->postJson('/api/v1/auth/login', ['identifier' => '0970000000', 'password' => 'wrong'])->assertStatus(401)->assertJsonPath('error.code', 'UNAUTHENTICATED');
        $this->postJson('/api/v1/auth/login', ['identifier' => '0970000000', 'password' => 'Password123'])->assertStatus(403)->assertJsonPath('error.code', 'EMAIL_REQUIRED');
        $this->assertGuest();
        $this->assertSame('imported+test@newworldcargo.invalid', $user->fresh()->email);
    }

    public function test_existing_session_cannot_bypass_requirement_for_reads_or_writes(): void
    {
        $user = $this->customer('');
        $this->actingAs($user)->withSession(['_token' => 'csrf'])->withHeader('X-CSRF-Token', 'csrf');
        foreach (['/api/v1/session', '/api/v1/shipments', '/api/v1/invoices'] as $path) {
            $this->getJson($path)->assertStatus(403)->assertJsonPath('error.code', 'EMAIL_REQUIRED');
        }
        $this->postJson('/api/v1/shipment-drafts', [])->assertStatus(403)->assertJsonPath('error.code', 'EMAIL_REQUIRED');
        $this->postJson('/api/v1/auth/logout')->assertNoContent();
    }

    public function test_valid_email_login_is_unchanged_and_shared_phone_is_not_disclosed(): void
    {
        $user = $this->customer('customer@mail.com');
        $this->postJson('/api/v1/auth/login', ['identifier' => '0970000000', 'password' => 'Password123'])->assertOk();
        auth()->logout();
        User::create(['name' => 'Another', 'email' => 'another@newworldcargo.invalid', 'password' => Hash::make('Password123'), 'role' => 4, 'responsible_mobile' => '0970000000']);
        $this->postJson('/api/v1/auth/login', ['identifier' => '0970000000', 'password' => 'Password123'])->assertStatus(401)->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }
}
