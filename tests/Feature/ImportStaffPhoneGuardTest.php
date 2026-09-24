<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ImportStaffPhoneGuard;
use App\Services\ConsignmentCustomerMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Cargo\Entities\Client;
use Tests\TestCase;

class ImportStaffPhoneGuardTest extends TestCase
{
    use RefreshDatabase;

    private function user(int $role, array $extra = []): User
    {
        return User::create(array_merge(['name' => 'Test Person', 'email' => uniqid().'@example.test',
            'password' => bcrypt('test'), 'role' => $role], $extra));
    }

    private function assertBlocked(array $phones): void
    {
        try {
            (new ImportStaffPhoneGuard())->assertAllowed($phones);
            $this->fail('Staff number was accepted');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('staff phone number', $exception->errors()['customer'][0]);
        }
    }

    public function test_staff_admin_legacy_staff_and_branch_numbers_are_blocked(): void
    {
        foreach ([0, 1, 2, 3] as $role) {
            $user = $this->user($role, ['responsible_mobile' => '+26097000000'.$role]);
            $this->assertBlocked(['097000000'.$role]);
            $this->assertBlocked(['0026097000000'.$role]);
        }
    }

    public function test_secondary_staff_number_is_blocked_in_either_import_contact(): void
    {
        $this->user(0, ['secondary_mobile' => '+260 960 000 000']);
        $this->assertBlocked(['960000000']);
        $this->assertBlocked(['0970000000', '0960000000']);
    }

    public function test_staff_profile_number_is_blocked_even_with_customer_role(): void
    {
        $user = $this->user(4);
        DB::table('staffs')->insert(['code' => 1, 'user_id' => $user->id, 'responsible_mobile' => '0970000000', 'is_archived' => 0]);
        $this->assertBlocked(['+260970000000']);
    }

    public function test_staff_customer_profile_number_is_also_blocked(): void
    {
        $user = $this->user(1);
        Client::create(['code' => 1, 'user_id' => $user->id, 'name' => $user->name,
            'email' => $user->email, 'secondary_mobile' => '0970000000', 'is_archived' => 0]);
        $this->assertBlocked(['+260970000000']);
    }

    public function test_genuine_customer_and_different_country_number_remain_allowed(): void
    {
        $this->user(4, ['responsible_mobile' => '+260960000000']);
        $this->user(0, ['responsible_mobile' => '+263970000000']);
        (new ImportStaffPhoneGuard())->assertAllowed(['0960000000', '0970000000', null, '']);
        $this->addToAssertionCount(1);
    }

    public function test_staff_account_with_matching_customer_name_is_reused(): void
    {
        $user = $this->user(0, ['responsible_mobile' => '+260970000000']);
        $client = Client::create(['code' => 1, 'user_id' => $user->id, 'name' => $user->name,
            'email' => $user->email, 'responsible_mobile' => '+260970000000', 'is_archived' => 0]);
        $this->assertSame($client->id, (new ConsignmentCustomerMatcher())->find('0970000000', $user->name)->id);
        (new ImportStaffPhoneGuard())->assertAllowed(['0970000000'], $client->id);
    }

    public function test_staff_number_cannot_be_attached_to_another_customer(): void
    {
        $staff = $this->user(0, ['responsible_mobile' => '+260970000000']);
        $customer = $this->user(4);
        $client = Client::create(['code' => 1, 'user_id' => $customer->id, 'name' => $customer->name,
            'email' => $customer->email, 'is_archived' => 0]);
        $this->expectException(ValidationException::class);
        (new ImportStaffPhoneGuard())->assertAllowed(['0970000000'], $client->id);
    }

    public function test_staff_profile_only_phone_can_match_own_customer_account(): void
    {
        $user = $this->user(0);
        DB::table('staffs')->insert(['code' => 1, 'user_id' => $user->id, 'responsible_mobile' => '0970000000', 'is_archived' => 0]);
        $client = Client::create(['code' => 1, 'user_id' => $user->id, 'name' => $user->name,
            'email' => $user->email, 'is_archived' => 0]);
        $this->assertSame($client->id, (new ConsignmentCustomerMatcher())->find('0970000000', $user->name)->id);
    }

    public function test_shared_staff_phone_is_not_accepted_for_either_staff_customer(): void
    {
        $user = $this->user(0, ['responsible_mobile' => '+260970000000']);
        $this->user(0, ['responsible_mobile' => '0970000000']);
        $client = Client::create(['code' => 1, 'user_id' => $user->id, 'name' => $user->name,
            'email' => $user->email, 'is_archived' => 0]);
        $this->expectException(ValidationException::class);
        (new ImportStaffPhoneGuard())->assertAllowed(['0970000000'], $client->id);
    }

    public function test_csv_preflight_rejects_staff_contacts_before_any_shipments_are_written(): void
    {
        $staff = $this->user(0, ['responsible_mobile' => '+260970000000']);
        $customer = $this->user(4);
        $client = Client::create(['code' => 1, 'user_id' => $customer->id, 'name' => $customer->name,
            'email' => $customer->email, 'is_archived' => 0]);
        $this->actingAs($staff);
        \Illuminate\Support\Facades\Route::post('/_test/import-staff-check', [\Modules\Cargo\Http\Controllers\ShipmentController::class, 'parseImport'])->middleware('web');
        $columns = ['type', 'client_id', 'client_phone', 'client_phone_2', 'client_address', 'branch_id', 'shipping_date',
            'reciver_name', 'reciver_phone', 'reciver_phone_2', 'reciver_address', 'from_country_id', 'to_country_id',
            'from_state_id', 'to_state_id', 'to_area_id', 'from_area_id', 'payment_type', 'payment_method_id', 'package_id'];
        foreach (['client_phone', 'client_phone_2', 'reciver_phone', 'reciver_phone_2'] as $field) {
            $values = array_fill_keys($columns, '1');
            $values['client_id'] = $client->id;
            foreach (['client_phone', 'client_phone_2', 'reciver_phone', 'reciver_phone_2'] as $contact) $values[$contact] = '0960000000';
            $first = implode(',', $values);
            $values[$field] = '0970000000';
            $csv = implode(',', $columns)."\n".$first."\n".implode(',', $values)."\n";
            $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('import.csv', $csv);
            $this->from('/imports')->post('/_test/import-staff-check', ['columns' => $columns, 'shipments_file' => $file])
                ->assertRedirect('/imports')->assertSessionHas('error_message_alert', fn($message) => str_contains($message, 'staff phone number'));
            $this->assertDatabaseCount('shipments', 0);
        }
    }
}
