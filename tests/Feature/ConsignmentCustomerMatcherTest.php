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

    public function test_different_name_is_not_silently_linked_to_phone_owner(): void
    {
        $this->customer('Jane Banda', '+260970000000');
        $this->expectException(ValidationException::class);
        (new ConsignmentCustomerMatcher())->find('0970000000', 'Someone Else');
    }

    public function test_archived_profile_does_not_become_a_new_duplicate(): void
    {
        $client = $this->customer('Jane Banda', '+260970000000');
        $client->update(['is_archived' => 1]);
        $this->expectException(ValidationException::class);
        (new ConsignmentCustomerMatcher())->find('0970000000', 'Jane Banda');
    }

    public function test_account_without_customer_profile_is_flagged(): void
    {
        User::create(['name' => 'Jane Banda', 'email' => 'staff@example.test', 'password' => bcrypt('test'),
            'role' => 0, 'responsible_mobile' => '0970000000']);
        $this->expectException(ValidationException::class);
        (new ConsignmentCustomerMatcher())->find('0970000000', 'Jane Banda');
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
        $second = $method->invoke($controller, ['phone' => '+260970000000', 'consignee_name' => 'Banda Jane', 'phone_2' => '0960000000'], $batch);
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
        $result = $method->invoke($controller, ['phone' => '0970000000', 'consignee_name' => 'Jane Banda'], new ConsignmentImportBatch());
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

    public function test_staff_number_for_another_name_is_rejected_in_preview_and_direct_resolution(): void
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
        $this->assertSame('invalid', $row->fresh()->status);
        $this->assertFalse((bool) $row->fresh()->included);
        $this->assertStringContainsString('different customer name', $row->fresh()->validation_errors['customer']);
        $method = new \ReflectionMethod(ConsignmentImportController::class, 'resolveClient');
        $method->setAccessible(true);
        try {
            $method->invoke(new ConsignmentImportController(), ['phone' => '0970000000', 'consignee_name' => 'Another Customer'], $batch);
            $this->fail('Staff contact was accepted');
        } catch (ValidationException $exception) {
            $this->assertDatabaseCount('users', 1);
            $this->assertDatabaseCount('clients', 1);
        }
    }
}
