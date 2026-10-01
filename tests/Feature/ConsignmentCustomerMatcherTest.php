<?php

namespace Tests\Feature;

use App\Http\Controllers\ConsignmentImportController;
use App\Models\ConsignmentImportBatch;
use App\Models\ConsignmentImportRow;
use App\Models\User;
use App\Services\ConsignmentCustomerMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Cargo\Entities\Client;
use Tests\TestCase;

class ConsignmentCustomerMatcherTest extends TestCase
{
    use RefreshDatabase;

    private function customer(string $name = 'Jane Banda', ?string $userPhone = null, ?string $profilePhone = null): Client
    {
        $user = User::create(['name' => $name, 'email' => uniqid().'@example.test', 'password' => bcrypt('test'),
            'role' => 4, 'responsible_mobile' => $userPhone]);
        return Client::create(['code' => $user->id, 'user_id' => $user->id, 'name' => $name,
            'email' => $user->email, 'responsible_mobile' => $profilePhone, 'is_archived' => 0]);
    }

    public function test_full_phone_formats_match_without_cross_country_suffix_matching(): void
    {
        foreach (['0970000000', '970000000', '+260 970-000-000', '00260970000000', '2600970000000'] as $phone) {
            $this->assertSame('260970000000', ConsignmentCustomerMatcher::phone($phone));
        }
        $this->assertNotSame(ConsignmentCustomerMatcher::phone('+263970000000'), ConsignmentCustomerMatcher::phone('0970000000'));
        $this->assertNull(ConsignmentCustomerMatcher::phone('0970000000 / 0960000000'));
    }

    public function test_matches_account_phone_when_profile_phone_is_missing_and_name_order_differs(): void
    {
        $client = $this->customer('Jane Banda', '+260970000000');
        $match = (new ConsignmentCustomerMatcher())->find('0970000000', ' BANDA   jane ');
        $this->assertSame($client->id, $match->id);
        $this->assertDatabaseCount('clients', 1);
    }

    public function test_matches_secondary_contact_and_keeps_countries_distinct(): void
    {
        $client = $this->customer('Jane Banda', null, '+260960000000');
        User::find($client->user_id)->update(['secondary_mobile' => '+260970000000']);
        $matcher = new ConsignmentCustomerMatcher();
        $this->assertSame($client->id, $matcher->find('970000000', 'Jane Banda')->id);
        $this->assertNull($matcher->find('+263970000000', 'Jane Banda'));
    }

    public function test_shared_number_is_blocked_even_when_only_one_name_matches(): void
    {
        $this->customer('Jane Banda', '+260970000000');
        $this->customer('Other Person', null, '0970000000');
        $this->expectException(ValidationException::class);
        (new ConsignmentCustomerMatcher())->find('970000000', 'Jane Banda');
    }

    public function test_two_input_numbers_owned_by_different_accounts_are_blocked(): void
    {
        $this->customer('Jane Banda', '+260970000000');
        $this->customer('Jane Banda', '+260960000000');
        $this->expectException(ValidationException::class);
        (new ConsignmentCustomerMatcher())->find('0970000000', 'Jane Banda', '0960000000');
    }

    public function test_unique_customer_phone_takes_priority_over_buyers_name_without_renaming_account(): void
    {
        $client = $this->customer('Jane Banda', '+260970000000');
        $match = (new ConsignmentCustomerMatcher())->find('0970000000', 'Someone Else');
        $this->assertSame($client->id, $match->id);
        $this->assertSame('Jane Banda', $client->fresh()->name);
        $this->assertSame('Jane Banda', User::find($client->user_id)->name);
        $this->assertDatabaseCount('clients', 1);
    }

    public function test_archived_profile_does_not_become_a_new_duplicate(): void
    {
        $client = $this->customer('Jane Banda', '+260970000000');
        $client->update(['is_archived' => 1]);
        $this->expectException(ValidationException::class);
        (new ConsignmentCustomerMatcher())->find('0970000000', 'Jane Banda');
    }

    public function test_staff_account_without_customer_profile_is_allowed_without_writes_in_preview(): void
    {
        User::create(['name' => 'Jane Banda', 'email' => 'staff@example.test', 'password' => bcrypt('test'),
            'role' => 0, 'responsible_mobile' => '0970000000']);
        $this->assertNull((new ConsignmentCustomerMatcher())->find('0970000000', 'Jane Banda'));
        $this->assertDatabaseCount('clients', 0);
    }

    public function test_multiple_profiles_for_one_account_need_review(): void
    {
        $client = $this->customer('Jane Banda', '+260970000000');
        $client->replicate()->save();
        $this->expectException(ValidationException::class);
        (new ConsignmentCustomerMatcher())->find('0970000000', 'Jane Banda');
    }

    public function test_name_alone_does_not_grant_access_to_someone_elses_history(): void
    {
        $this->customer('Jane Banda');
        $this->assertNull((new ConsignmentCustomerMatcher())->find('0970000000', 'Jane Banda'));
    }

    public function test_repeated_import_rows_reuse_one_new_account_without_changing_its_contacts(): void
    {
        $controller = new ConsignmentImportController();
        $method = new \ReflectionMethod($controller, 'resolveClient');
        $method->setAccessible(true);
        $batch = new ConsignmentImportBatch();
        $data = ['phone' => '0970000000', 'consignee_name' => 'Jane Banda'];
        $first = $method->invoke($controller, $data, $batch);
        $second = $method->invoke($controller, ['phone' => '+260970000000', 'consignee_name' => 'Different Buyer', 'phone_2' => '0960000000'], $batch);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('clients', 1);
        $this->assertNull($first->fresh()->secondary_mobile);
        $this->assertSame('260970000000', $first->fresh()->responsible_mobile);
    }

    public function test_existing_customer_credentials_and_contacts_are_unchanged_by_import(): void
    {
        $client = $this->customer('Jane Banda', '+260970000000');
        $client->refresh();
        $before = User::find($client->user_id)->getRawOriginal();
        $controller = new ConsignmentImportController();
        $method = new \ReflectionMethod($controller, 'resolveClient');
        $method->setAccessible(true);
        $result = $method->invoke($controller, ['phone' => '0970000000', 'consignee_name' => 'Buyer Representative'], new ConsignmentImportBatch());
        $this->assertSame($client->id, $result->id);
        $this->assertSame($before, User::find($client->user_id)->getRawOriginal());
        $this->assertSame($client->getRawOriginal(), $client->fresh()->getRawOriginal());
    }

    public function test_preview_identifies_existing_customer_and_blocks_shared_number_rows(): void
    {
        $client = $this->customer('Jane Banda', '+260970000000');
        $batch = ConsignmentImportBatch::create(['uuid' => (string) \Illuminate\Support\Str::uuid(),
            'created_by' => $client->user_id, 'original_filename' => 'test.xlsx', 'storage_path' => 'test.xlsx',
            'shipment_type' => 'air', 'selected_sheet' => 'Sheet1', 'data_start_row' => 2,
            'mappings' => ['hawb_number' => 'A', 'consignee_name' => 'B', 'phone' => 'C', 'destination' => 'D']]);
        $row = ConsignmentImportRow::create(['batch_id' => $batch->id, 'sheet_name' => 'Sheet1', 'spreadsheet_row' => 2,
            'raw_values' => ['A' => 'TEST123', 'B' => 'Jane Banda', 'C' => '0970000000', 'D' => 'Lusaka'], 'included' => true]);
        $method = new \ReflectionMethod(ConsignmentImportController::class, 'validateRows');
        $method->setAccessible(true);
        $method->invoke(new ConsignmentImportController(), $batch);
        $this->assertSame('new', $row->fresh()->status);
        $this->assertStringContainsString('profile #'.$client->id, $row->fresh()->validation_warnings['customer']);
        $this->customer('Other Owner', '0970000000');
        $method->invoke(new ConsignmentImportController(), $batch);
        $this->assertSame('invalid', $row->fresh()->status);
        $this->assertFalse((bool) $row->fresh()->included);
        $this->assertStringContainsString('more than one account', $row->fresh()->validation_errors['customer']);
    }

    public function test_staff_number_for_another_name_is_accepted_without_renaming_the_account(): void
    {
        $client = $this->customer('Staff Customer', '+260970000000');
        User::find($client->user_id)->update(['role' => 0]);
        $batch = ConsignmentImportBatch::create(['uuid' => (string) \Illuminate\Support\Str::uuid(),
            'created_by' => $client->user_id, 'original_filename' => 'test.xlsx', 'storage_path' => 'test.xlsx',
            'shipment_type' => 'air', 'selected_sheet' => 'Sheet1', 'data_start_row' => 2,
            'mappings' => ['hawb_number' => 'A', 'consignee_name' => 'B', 'phone' => 'C', 'destination' => 'D']]);
        $row = ConsignmentImportRow::create(['batch_id' => $batch->id, 'sheet_name' => 'Sheet1', 'spreadsheet_row' => 2,
            'raw_values' => ['A' => 'TEST123', 'B' => 'Another Customer', 'C' => '0970000000', 'D' => 'Lusaka'], 'included' => true]);
        $method = new \ReflectionMethod(ConsignmentImportController::class, 'validateRows');
        $method->setAccessible(true);
        $method->invoke(new ConsignmentImportController(), $batch);
        $this->assertSame('new', $row->fresh()->status);
        $this->assertTrue((bool) $row->fresh()->included);
        $this->assertStringContainsString('Staff Customer', $row->fresh()->validation_warnings['customer']);
        $method = new \ReflectionMethod(ConsignmentImportController::class, 'resolveClient');
        $method->setAccessible(true);
        $result = $method->invoke(new ConsignmentImportController(), ['phone' => '0970000000', 'consignee_name' => 'Another Customer'], $batch);
        $this->assertSame($client->id, $result->id);
        $this->assertSame('Staff Customer', $result->name);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('clients', 1);
    }

    public function test_import_creates_one_staff_customer_profile_without_changing_staff_credentials(): void
    {
        $user = User::create(['name' => 'Staff Owner', 'email' => 'owner@example.test', 'password' => bcrypt('test'),
            'role' => 0, 'responsible_mobile' => '0970000000']);
        $before = $user->fresh()->getAttributes();
        $controller = new ConsignmentImportController();
        $resolve = new \ReflectionMethod($controller, 'resolveClient');
        $resolve->setAccessible(true);
        $batch = new ConsignmentImportBatch();
        $first = $resolve->invoke($controller, ['phone' => '0970000000', 'consignee_name' => 'Different buyer'], $batch);
        $second = $resolve->invoke($controller, ['phone' => '+260970000000', 'consignee_name' => 'Another buyer'], $batch);
        $this->assertSame($first->id, $second->id);
        $this->assertEquals($user->id, $first->user_id);
        $this->assertSame('Staff Owner', $first->name);
        $this->assertSame($before, $user->fresh()->getAttributes());
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseHas('audit_logs', ['event' => 'staff_customer_profile_created']);
    }

    public function test_staff_profile_only_number_can_create_customer_profile(): void
    {
        $user = User::create(['name' => 'Staff Owner', 'email' => 'owner@example.test', 'password' => bcrypt('test'), 'role' => 4]);
        \Illuminate\Support\Facades\DB::table('staffs')->insert(['code' => 1, 'user_id' => $user->id, 'responsible_mobile' => '0970000000', 'is_archived' => 0]);
        $matcher = new ConsignmentCustomerMatcher();
        $this->assertNull($matcher->find('0970000000', 'Buyer'));
        $client = $matcher->find('0970000000', 'Buyer', null, true);
        $this->assertEquals($user->id, $client->user_id);
        $this->assertSame('260970000000', $client->responsible_mobile);
    }

    public function test_archived_staff_customer_is_not_recreated(): void
    {
        $client = $this->customer('Staff Owner', '0970000000');
        User::findOrFail($client->user_id)->update(['role' => 0]);
        $client->update(['is_archived' => 1]);
        $this->expectException(ValidationException::class);
        (new ConsignmentCustomerMatcher())->find('0970000000', 'Buyer', null, true);
    }

    public function test_staff_customer_creation_and_audit_roll_back_with_failed_import(): void
    {
        User::create(['name' => 'Staff Owner', 'email' => 'owner@example.test', 'password' => bcrypt('test'),
            'role' => 0, 'responsible_mobile' => '0970000000']);
        try {
            \Illuminate\Support\Facades\DB::transaction(function () {
                (new ConsignmentCustomerMatcher())->find('0970000000', 'Buyer', null, true);
                throw new \RuntimeException('Simulated import failure');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated import failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'staff_customer_profile_created']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_shared_staff_number_cannot_automatically_create_a_customer(): void
    {
        foreach ([0, 1] as $role) {
            User::create(['name' => 'Staff Owner', 'email' => $role.'@example.test', 'password' => bcrypt('test'),
                'role' => $role, 'responsible_mobile' => '0970000000']);
        }
        try {
            (new ConsignmentCustomerMatcher())->find('0970000000', 'Buyer', null, true);
            $this->fail('Ambiguous ownership must be reviewed');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('more than one account', $exception->errors()['customer'][0]);
        }
        $this->assertDatabaseCount('clients', 0);
    }
}
