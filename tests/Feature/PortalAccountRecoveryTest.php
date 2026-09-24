<?php

namespace Tests\Feature;

use App\Mail\OTPMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, Crypt, DB, Hash, Mail};
use Modules\Cargo\Entities\Client;
use Modules\CustomerPortalApi\Services\Portal\PortalAccountIdentifier;
use Tests\TestCase;

class PortalAccountRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function customer(string $email, ?string $phone = null): User
    {
        $user = User::create(['name' => 'Test Customer', 'email' => $email, 'password' => Hash::make('Password123'), 'role' => 4, 'verified' => true]);
        Client::create(['code' => $user->id, 'user_id' => $user->id, 'name' => $user->name, 'email' => $email, 'responsible_mobile' => $phone, 'is_archived' => 0]);
        return $user;
    }

    private function start(array $overrides = [])
    {
        return $this->withSession(['_token' => 'test-csrf'])->withHeader('X-CSRF-Token', 'test-csrf')
            ->postJson('/api/v1/auth/account-recovery', array_merge(['name' => 'Test Claimant', 'email' => 'claimant@example.test',
                'phone' => '0970000000', 'shipmentReference' => 'OLD123', 'detail' => 'I shipped at the branch and never signed in.'], $overrides));
    }

    public function test_phone_login_normalizes_profile_only_contacts_and_rejects_shared_numbers(): void
    {
        $user = $this->customer('first@example.test', '+260970000000');
        $matcher = new PortalAccountIdentifier();
        $this->assertSame($user->id, $matcher->find('0970000000')->id);
        $this->assertSame($user->id, $matcher->find('FIRST@example.test')->id);
        $other = $this->customer('second@example.test', '970000000');
        $this->assertNull($matcher->find('+260970000000'));
        $this->postJson('/api/v1/auth/login', ['identifier' => '0970000000', 'password' => 'Password123'])->assertStatus(401);
        $this->assertSame($other->id, $matcher->find('second@example.test')->id);
    }

    public function test_claim_only_creates_encrypted_review_after_email_proof_without_granting_access(): void
    {
        Mail::fake();
        $user = $this->customer('old@example.test', '0970000000');
        $before = $user->fresh()->getRawOriginal();
        $response = $this->start()->assertOk();
        $reference = $response->json('data.reference');
        $this->assertDatabaseCount('customer_account_recovery_requests', 0);
        $code = (string) Mail::sent(OTPMail::class)->last()->otp;
        $this->postJson('/api/v1/auth/account-recovery/confirm', ['reference' => $reference, 'code' => $code])
            ->assertCreated()->assertJsonPath('data.status', 'pending_review');
        $row = DB::table('customer_account_recovery_requests')->first();
        $this->assertStringNotContainsString('claimant@example.test', $row->encrypted_details);
        $this->assertSame('claimant@example.test', json_decode(Crypt::decryptString($row->encrypted_details), true)['email']);
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($before, $user->fresh()->getRawOriginal());
        $this->assertGuest();
        $this->postJson('/api/v1/auth/account-recovery/confirm', ['reference' => $reference, 'code' => $code])->assertCreated();
        $this->assertDatabaseCount('customer_account_recovery_requests', 1);
    }

    public function test_claim_attempts_and_expiry_are_enforced(): void
    {
        Mail::fake();
        $reference = $this->start()->assertOk()->json('data.reference');
        $code = (string) Mail::sent(OTPMail::class)->last()->otp;
        for ($i = 0; $i < 5; $i++) $this->postJson('/api/v1/auth/account-recovery/confirm', ['reference' => $reference, 'code' => '000000'])->assertStatus(422);
        $this->postJson('/api/v1/auth/account-recovery/confirm', ['reference' => $reference, 'code' => $code])->assertStatus(429);
        $this->assertDatabaseCount('customer_account_recovery_requests', 0);
        $this->travel(11)->minutes();
        $this->postJson('/api/v1/auth/account-recovery/confirm', ['reference' => $reference, 'code' => $code])->assertStatus(422);
    }

    public function test_unverified_account_cannot_access_shipments_but_can_sign_in_to_verify(): void
    {
        $user = $this->customer('pending@example.test');
        $user->update(['verified' => false]);
        $this->postJson('/api/v1/auth/login', ['identifier' => $user->email, 'password' => 'Password123'])->assertOk()->assertJsonPath('data.verified', false);
        $this->getJson('/api/v1/shipments')->assertStatus(403)->assertJsonPath('error.code', 'CONTACT_UNVERIFIED');
        $this->getJson('/api/v1/session')->assertOk();
    }

    public function test_verification_code_cannot_reset_password(): void
    {
        $user = $this->customer('purpose@example.test');
        $user->forceFill(['otp' => '123456', 'otp_expires_at' => now()->addMinutes(10)])->save();
        $this->postJson('/api/v1/auth/password/reset', ['identifier' => $user->email, 'code' => '123456', 'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123'])
            ->assertStatus(422)->assertJsonPath('error.code', 'OTP_INVALID');
        $this->assertTrue(Hash::check('Password123', $user->fresh()->password));
    }

    public function test_unknown_and_existing_accounts_get_same_claim_start_response_shape(): void
    {
        Mail::fake();
        $this->customer('existing@example.test', '0970000000');
        $one = $this->start()->assertOk()->json('data');
        $two = $this->start(['phone' => '0960000000', 'email' => 'another@example.test'])->assertOk()->json('data');
        $this->assertSame(array_keys($one), array_keys($two));
        $this->assertArrayNotHasKey('userId', $one);
    }

    public function test_legacy_claim_no_longer_updates_customer_and_review_requires_permission(): void
    {
        $user = $this->customer('old@example.test');
        $this->post('/clients/process-claim', ['claim_account' => 1])->assertRedirect('https://app.newworldcargo.com/recover-account');
        $this->actingAs($user)->get('/customer-account-recovery')->assertForbidden();
        $this->assertTrue(Hash::check('Password123', $user->fresh()->password));
    }

    public function test_review_can_be_saved_by_permission_without_changing_any_customer(): void
    {
        $user = $this->customer('reviewer@example.test');
        $group = DB::table('permission_groups')->insertGetId(['name' => 'Recovery tests', 'created_at' => now(), 'updated_at' => now()]);
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'manage-clients', 'guard_name' => 'web'], ['permission_group_id' => $group]);
        $user->givePermissionTo($permission);
        $reference = (string) \Illuminate\Support\Str::uuid();
        DB::table('customer_account_recovery_requests')->insert(['reference' => $reference,
            'encrypted_details' => Crypt::encryptString(json_encode(['name' => 'Claimant', 'email' => 'claim@example.test', 'phone' => '260970000000', 'detail' => 'Please review my historical account.'])),
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->post('/customer-account-recovery/'.$reference, ['status' => 'contacted', 'note' => 'Customer contacted; ownership not yet confirmed.'])->assertRedirect();
        $this->assertDatabaseHas('customer_account_recovery_requests', ['reference' => $reference, 'status' => 'contacted', 'reviewed_by' => $user->id]);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('audit_logs', ['event' => 'account_recovery_reviewed']);
    }

    public function test_password_reset_code_is_blocked_after_five_wrong_attempts(): void
    {
        Mail::fake();
        $user = $this->customer('reset-attempts@example.test');
        $this->postJson('/api/v1/auth/password/forgot', ['identifier' => $user->email])->assertOk();
        $code = (string) Mail::sent(OTPMail::class)->last()->otp;
        $body = ['identifier' => $user->email, 'code' => '000000', 'password' => 'Changed123', 'password_confirmation' => 'Changed123'];
        for ($i = 0; $i < 5; $i++) $this->postJson('/api/v1/auth/password/reset', $body)->assertStatus(422);
        $body['code'] = $code;
        $this->postJson('/api/v1/auth/password/reset', $body)->assertStatus(422);
        $this->assertTrue(Hash::check('Password123', $user->fresh()->password));
    }

    public function test_multiple_active_profiles_do_not_choose_the_first_history(): void
    {
        $user = $this->customer('multiple@example.test');
        Client::where('user_id', $user->id)->first()->replicate()->save();
        $this->postJson('/api/v1/auth/login', ['identifier' => $user->email, 'password' => 'Password123'])->assertStatus(403);
        $this->assertGuest();
    }
}
