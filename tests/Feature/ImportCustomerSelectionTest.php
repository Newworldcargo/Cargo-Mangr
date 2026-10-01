<?php

namespace Tests\Feature;

use App\Models\{User, ConsignmentImportBatch, ConsignmentImportRow};
use App\Services\ImportCustomerSelection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Gate};
use Modules\Cargo\Entities\{Client, Branch};
use Tests\TestCase;

class ImportCustomerSelectionTest extends TestCase
{
    use RefreshDatabase;
    private $batch;
    private $row;
    private $actor;
    private bool $allowCreate = true;
    private bool $allowImport = true;

    protected function setUp(): void
    {
        parent::setUp();
        // These legacy production columns predate the repository's migrations.
        foreach (['consignment_id', 'next_destination'] as $column) {
            if (!\Illuminate\Support\Facades\Schema::hasColumn('shipments', $column)) {
                \Illuminate\Support\Facades\Schema::table('shipments', fn($table) => $table->string($column)->nullable());
            }
        }
        $this->actor = User::create(['name' => 'Import Staff', 'email' => 'staff@example.test', 'password' => bcrypt('test'), 'role' => 1]);
        $this->actingAs($this->actor);
        Gate::before(function ($user, $ability) {
            if ($ability === 'import-consignments') return $this->allowImport;
            if ($ability === 'create-customers') return $this->allowCreate;
        });
        $branch = Branch::create(['code' => 1, 'name' => 'Test Branch', 'email' => 'branch@example.test', 'user_id' => $this->actor->id, 'is_archived' => 0]);
        $this->batch = ConsignmentImportBatch::create(['uuid' => 'customer-test-batch', 'created_by' => $this->actor->id,
            'original_filename' => 'test.csv', 'storage_path' => 'test.csv', 'shipment_type' => 'air',
            'selected_sheet' => 'Sheet1', 'header_row' => 1, 'data_start_row' => 2, 'pickup_branch_id' => $branch->id,
            'mappings' => ['hawb_number' => 'A', 'consignee_name' => 'B', 'phone' => 'C', 'destination' => 'D']]);
        $this->row = ConsignmentImportRow::create(['batch_id' => $this->batch->id, 'sheet_name' => 'Sheet1', 'spreadsheet_row' => 2,
            'raw_values' => ['A' => 'TEST001', 'B' => 'Wrong Spreadsheet Name', 'C' => '0970000000', 'D' => 'Lusaka'],
            'mapped_values' => ['hawb_number' => 'TEST001'], 'status' => 'invalid', 'included' => false]);
    }

    private function customer(string $name = 'Jane Banda', string $phone = '260970000000'): Client
    {
        $user = User::create(['name' => $name, 'email' => uniqid().'@example.test', 'password' => bcrypt('test'), 'role' => 4, 'responsible_mobile' => $phone]);
        return Client::create(['code' => $user->id, 'name' => $name, 'email' => $user->email, 'user_id' => $user->id, 'responsible_mobile' => $phone, 'is_archived' => 0]);
    }

    private function url(): string { return '/consignments/imports/'.$this->batch->uuid.'/rows/'.$this->row->id.'/customer'; }

    public function test_explicit_selection_resolves_shared_phone_and_name_conflict_without_changing_accounts(): void
    {
        $client = $this->customer();
        $this->customer('Other Person');
        $this->postJson($this->url(), ['version' => 0, 'customer_id' => $client->id])->assertOk()
            ->assertJsonPath('row.status', 'new')->assertJsonPath('row.mapped_values.consignee_name', 'Jane Banda')
            ->assertJsonPath('row.selected_customer_id', $client->id);
        $this->assertSame('Wrong Spreadsheet Name', $this->row->fresh()->raw_values['B']);
        $this->assertSame('Jane Banda', $client->fresh()->name);
        $this->assertDatabaseCount('clients', 2);
        $this->assertDatabaseCount('shipments', 0);
        $this->assertDatabaseHas('audit_logs', ['event' => 'import_customer_selected', 'user_id' => $this->actor->id]);
    }

    public function test_selected_saved_contacts_replace_bad_spreadsheet_phone_only_on_this_row(): void
    {
        $client = $this->customer('Jane Banda', '260960000000');
        $this->postJson($this->url(), ['version' => 0, 'customer_id' => $client->id])->assertOk()->assertJsonPath('row.mapped_values.phone', '260960000000');
        $this->assertSame('0970000000', $this->row->fresh()->raw_values['C']);
        $this->postJson($this->url(), ['version' => 0, 'customer_id' => null])->assertStatus(409);
        $this->postJson($this->url(), ['version' => 1, 'customer_id' => null])->assertOk()->assertJsonPath('row.selected_customer_id', null);
    }

    public function test_new_customer_creation_is_atomic_and_repeated_submit_does_not_duplicate(): void
    {
        $payload = ['version' => 0, 'create' => true, 'first_name' => 'New', 'last_name' => 'Customer', 'phone' => '+260960000000', 'email' => 'new@example.test'];
        $this->postJson($this->url(), $payload)->assertOk()->assertJsonPath('row.status', 'new');
        $this->postJson($this->url(), $payload)->assertStatus(409);
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseHas('users', ['email' => 'new@example.test', 'verified' => 0]);
        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_duplicate_phone_or_email_cannot_create_a_new_account(): void
    {
        $client = $this->customer();
        $payload = ['version' => 0, 'create' => true, 'first_name' => 'Another', 'last_name' => 'Name', 'phone' => '0970000000'];
        $this->postJson($this->url(), $payload)->assertStatus(422)->assertJsonValidationErrors('phone');
        $payload['phone'] = '0960000000'; $payload['email'] = $client->email;
        $this->postJson($this->url(), $payload)->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('clients', 1);
    }

    public function test_permissions_batch_ownership_and_completed_batch_are_enforced(): void
    {
        $client = $this->customer();
        $this->allowCreate = false;
        $this->postJson($this->url(), ['version' => 0, 'create' => true])->assertForbidden();
        $this->allowImport = false;
        $this->postJson($this->url(), ['version' => 0, 'customer_id' => $client->id])->assertForbidden();
        $this->allowImport = true;
        $this->batch->update(['created_by' => $client->user_id]);
        $this->postJson($this->url(), ['version' => 0, 'customer_id' => $client->id])->assertNotFound();
        $this->batch->update(['created_by' => $this->actor->id, 'status' => 'completed']);
        $this->postJson($this->url(), ['version' => 0, 'customer_id' => $client->id])->assertStatus(409);
    }

    public function test_archived_missing_profile_phone_and_staff_contact_abuse_are_blocked(): void
    {
        $client = $this->customer();
        $client->update(['is_archived' => 1]);
        $this->postJson($this->url(), ['version' => 0, 'customer_id' => $client->id])->assertStatus(422);
        $client->update(['is_archived' => 0]);
        $this->actor->update(['responsible_mobile' => $client->responsible_mobile]);
        $this->postJson($this->url(), ['version' => 0, 'customer_id' => $client->id])->assertStatus(422);
    }

    public function test_changed_source_requires_reconfirmation(): void
    {
        $client = $this->customer();
        $this->postJson($this->url(), ['version' => 0, 'customer_id' => $client->id])->assertOk();
        $row = $this->row->fresh();
        $row->raw_values = ['A' => 'CHANGED'];
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        (new ImportCustomerSelection())->apply([], $row, $this->batch);
    }

    public function test_summary_rows_cannot_create_accounts_and_search_is_scoped_to_import_owner(): void
    {
        $client = $this->customer();
        $this->getJson('/consignments/imports/'.$this->batch->uuid.'/customers?q=Jane')->assertOk()->assertJsonPath('customers.0.id', $client->id);
        $this->row->update(['raw_values' => ['A' => 'TOTAL'], 'mapped_values' => ['hawb_number' => 'TOTAL']]);
        $this->postJson($this->url(), ['version' => 0, 'customer_id' => $client->id])->assertStatus(422);
        $this->assertDatabaseCount('clients', 1);
    }

    public function test_confirmation_imports_to_selected_account_without_duplicate_customer(): void
    {
        $client = $this->customer('Chosen Owner', '260960000000');
        $this->batch->update(['consignment_code' => 'UAT-CONTAINER', 'consignment_status' => 'pending', 'consignment_date' => '2026-09-30',
            'destination_branch_id' => $this->batch->pickup_branch_id, 'from_country_id' => 1, 'from_state_id' => 1, 'to_country_id' => 1, 'to_state_id' => 1]);
        DB::table('packages')->insert(['name' => 'Box', 'cost' => 0]);
        $this->postJson($this->url(), ['version' => 0, 'customer_id' => $client->id])->assertOk();
        $this->post('/consignments/imports/'.$this->batch->uuid.'/confirm', ['included' => [$this->row->id => 1], 'phone_override' => [$this->row->id => '0970000000']])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('shipments', ['code' => 'TEST001', 'client_id' => $client->id, 'client_phone' => '260960000000']);
        $this->assertDatabaseCount('clients', 1);
        $this->assertSame('completed', $this->batch->fresh()->status);
    }

    public function test_existing_shipment_ownership_cannot_be_reassigned_by_selection(): void
    {
        $client = $this->customer();
        DB::table('shipments')->insert(['code' => 'TEST001', 'status_id' => 1, 'type' => 1, 'shipping_date' => '2026-09-30',
            'client_id' => 999, 'client_phone' => '260960000000', 'from_country_id' => 1, 'from_state_id' => 1, 'to_country_id' => 1, 'to_state_id' => 1]);
        $this->postJson($this->url(), ['version' => 0, 'customer_id' => $client->id])->assertStatus(422)->assertJsonValidationErrors('customer');
        $this->assertDatabaseHas('shipments', ['code' => 'TEST001', 'client_id' => 999]);
        $this->assertNull($this->row->fresh()->selected_customer_id);
    }

    public function test_search_normalizes_phone_and_missing_contact_profiles_cannot_be_selected(): void
    {
        $client = $this->customer('Jane Banda', '+260 970 000 000');
        $this->getJson('/consignments/imports/'.$this->batch->uuid.'/customers?q=0970000000')->assertOk()->assertJsonPath('customers.0.id', $client->id);
        $client->update(['responsible_mobile' => null]);
        User::find($client->user_id)->update(['responsible_mobile' => null]);
        $this->postJson($this->url(), ['version' => 0, 'customer_id' => $client->id])->assertStatus(422)->assertJsonValidationErrors('customer');
    }

    public function test_failed_creation_rolls_back_account_and_preserves_other_rows(): void
    {
        $other = $this->row->replicate();
        $other->spreadsheet_row = 3; $other->included = true; $other->status = 'new'; $other->save();
        $this->postJson($this->url(), ['version' => 0, 'create' => true, 'first_name' => 'New', 'last_name' => 'Customer', 'phone' => '0960000000', 'address' => 'Test address'])
            ->assertStatus(422)->assertJsonValidationErrors('address');
        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseCount('users', 1);
        $this->assertTrue($other->fresh()->included);
        $this->assertSame(0, $this->row->fresh()->customer_selection_version);
    }

    private function prepareReimport(): \App\Models\Consignment
    {
        $consignment = \App\Models\Consignment::create(['consignment_code' => 'ADD-ONLY', 'name' => 'Original',
            'status' => 'delivered', 'source' => 'Original source', 'destination' => 'Original destination', 'cargo_date' => '2026-09-01']);
        $this->batch->update(['mode' => 'update', 'target_consignment_id' => $consignment->id,
            'consignment_code' => 'ADD-ONLY', 'consignment_status' => 'pending', 'consignment_date' => '2026-09-30',
            'destination_branch_id' => $this->batch->pickup_branch_id, 'from_country_id' => 1,
            'from_state_id' => 1, 'to_country_id' => 1, 'to_state_id' => 1]);
        DB::table('packages')->insert(['name' => 'Box', 'cost' => 0]);
        return $consignment;
    }

    public function test_import_uses_unique_phone_owner_but_preserves_the_spreadsheet_consignee(): void
    {
        $this->prepareReimport();
        $client = $this->customer();
        $before = $client->fresh()->getAttributes();
        $this->post('/consignments/imports/'.$this->batch->uuid.'/confirm', ['included' => [$this->row->id => 1]])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('shipments', ['code' => 'TEST001', 'client_id' => $client->id, 'reciver_name' => 'Wrong Spreadsheet Name']);
        $this->assertSame($before, $client->fresh()->getAttributes());
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('shipments', 1);
    }

    public function test_import_history_preserves_original_values_account_and_actor_after_changes(): void
    {
        $this->prepareReimport();
        $client = $this->customer();
        $this->postJson($this->url(), ['version' => 0, 'customer_id' => $client->id])->assertOk();
        $url = '/consignments/imports/'.$this->batch->uuid.'/confirm';
        $this->post($url, ['included' => [$this->row->id => 1]])->assertRedirect();
        $shipment = \Modules\Cargo\Entities\Shipment::where('code', 'TEST001')->firstOrFail();
        $log = \App\Models\AuditLog::where('event', 'shipment_imported')->sole();
        $this->assertEquals($this->actor->id, $log->user_id);
        $this->assertSame('Wrong Spreadsheet Name', $log->old_values['raw_values']['B']);
        $this->assertSame('Jane Banda', $log->new_values['customer']['name']);
        $this->assertSame('Jane Banda', $log->new_values['imported_values']['consignee_name']);
        $client->update(['name' => 'Later name']);
        $this->row->update(['raw_values' => ['B' => 'Later row edit']]);
        $history = app(\App\Services\ShipmentImportHistory::class)->forShipment($shipment);
        $this->assertCount(1, $history['imports']);
        $this->assertSame('Wrong Spreadsheet Name', $history['imports'][0]['raw_values']['B']);
        $this->assertSame('Jane Banda', $history['imports'][0]['customer']['name']);
        $html = view('cargo::adminLte.pages.shipments.import-history', compact('shipment'))->render();
        $this->assertStringContainsString('Different names:', $html);
        $this->assertStringContainsString('Wrong Spreadsheet Name', $html);
        $this->assertStringContainsString('Later name', $html);
        $this->post($url)->assertRedirect();
        $this->assertSame(1, \App\Models\AuditLog::where('event', 'shipment_imported')->count());
    }

    public function test_historical_rows_and_merges_are_limited_to_this_shipment_and_escaped(): void
    {
        $consignment = $this->prepareReimport();
        $shipment = $this->existingParcel($consignment->id);
        $this->batch->update(['status' => 'completed', 'confirmed_at' => now()]);
        $this->row->update(['shipment_id' => $shipment->id, 'import_action' => 'created',
            'raw_values' => ['B' => '<script>alert(1)</script>']]);
        foreach ([$shipment->id, $shipment->id + 1] as $id) {
            \App\Models\AuditLog::create(['event' => 'customer_account_merged',
                'auditable_type' => Client::class, 'auditable_id' => 999,
                'old_values' => ['client_id' => 123, 'shipment_ids' => [$id]],
                'new_values' => ['client_id' => 999, 'backup_file' => 'PRIVATE-BACKUP']]);
        }
        $history = app(\App\Services\ShipmentImportHistory::class)->forShipment($shipment);
        $this->assertCount(1, $history['imports']);
        $this->assertFalse($history['imports'][0]['snapshot']);
        $this->assertCount(1, $history['merges']);
        $html = view('cargo::adminLte.pages.shipments.import-history', compact('shipment'))->render();
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('PRIVATE-BACKUP', $html);
        $this->assertStringContainsString('Historical import:', $html);
        $this->actingAs(User::findOrFail($this->customer()->user_id));
        $this->assertStringNotContainsString('Import history', view('cargo::adminLte.pages.shipments.import-history', compact('shipment'))->render());
    }

    private function existingParcel(int $consignmentId): \Modules\Cargo\Entities\Shipment
    {
        return \Modules\Cargo\Entities\Shipment::create(['code' => 'TEST001', 'consignment_id' => $consignmentId,
            'client_id' => 999, 'client_phone' => '260960000000', 'status_id' => 1, 'type' => 1,
            'paid' => 1, 'shipping_cost' => 500, 'amount_to_be_collected' => 500,
            'shipping_date' => '2026-09-01', 'total_weight' => 12]);
    }

    public function test_reimport_adds_only_missing_parcels_preserving_paid_shipments_and_consignment(): void
    {
        $consignment = $this->prepareReimport();
        $shipment = $this->existingParcel($consignment->id);
        $invoice = \App\Models\Transxn::create(['shipment_id' => $shipment->id, 'receipt_number' => 'PAID-1', 'total' => 500, 'currency' => 'ZMW', 'status' => 'completed']);
        $receipt = \App\Models\ShipmentPaymentReceipt::create(['shipment_id' => $shipment->id, 'receipt_number' => 'PAID-1-1', 'amount' => 500, 'currency' => 'ZMW', 'method_of_payment' => 'cash_payment']);
        $package = \Modules\Cargo\Entities\PackageShipment::create(['package_id' => DB::table('packages')->value('id'), 'shipment_id' => $shipment->id, 'description' => 'Original goods', 'weight' => 12, 'qty' => 3]);
        $before = [$shipment->fresh()->getAttributes(), $consignment->fresh()->getAttributes(), $invoice->fresh()->getAttributes(), $receipt->fresh()->getAttributes(), $package->fresh()->getAttributes()];
        // Existing parcel data may now be invalid or belong to a different named customer.
        $this->row->update(['raw_values' => ['A' => 'TEST001', 'B' => 'Wrong name', 'C' => 'BAD PHONE', 'D' => 'Changed address'], 'status' => 'update', 'included' => true]);
        $client = $this->customer('Remaining Customer', '260950000000');
        $new = ConsignmentImportRow::create(['batch_id' => $this->batch->id, 'sheet_name' => 'Sheet1', 'spreadsheet_row' => 3,
            'raw_values' => ['A' => 'TEST002', 'B' => $client->name, 'C' => $client->responsible_mobile, 'D' => 'Lusaka'], 'included' => true]);
        $url = '/consignments/imports/'.$this->batch->uuid.'/confirm';
        $payload = ['included' => [$this->row->id => 1, $new->id => 1]];
        DB::table('currencies')->where('code', 'USD')->update(['default' => 1]);
        $preview = $this->get('/consignments/imports/'.$this->batch->uuid.'/preview')
            ->assertOk()->assertSee('Import new parcels')->assertSee('Already imported. Shipment and payment details will not be changed.');
        $document = new \DOMDocument();
        @$document->loadHTML($preview->getContent());
        $xpath = new \DOMXPath($document);
        $checkbox = $xpath->query('//input[@name="included['.$this->row->id.']"]')->item(0);
        $this->assertTrue($checkbox->hasAttribute('disabled'));
        $this->assertFalse($checkbox->hasAttribute('checked'));
        if (getenv('NWC_IMPORT_PREVIEW') === '1') file_put_contents('/tmp/nwc-add-only-import.html', $preview->getContent());
        $this->post($url, $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->post($url, $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('shipments', 2);
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseHas('shipments', ['code' => 'TEST002', 'client_id' => $client->id, 'consignment_id' => $consignment->id]);
        $this->assertSame($before, [$shipment->fresh()->getAttributes(), $consignment->fresh()->getAttributes(), $invoice->fresh()->getAttributes(), $receipt->fresh()->getAttributes(), $package->fresh()->getAttributes()]);
        $this->assertSame('skipped', $this->row->fresh()->status);
        $this->assertFalse($this->row->fresh()->included);
        $this->assertSame(1, $this->batch->fresh()->result['created']);
        $this->assertSame(1, $this->batch->fresh()->result['skipped']);
        $this->assertSame(0, $this->batch->fresh()->result['updated']);
    }

    public function test_existing_only_reimport_does_not_write_or_create_customers(): void
    {
        $consignment = $this->prepareReimport();
        $shipment = $this->existingParcel($consignment->id);
        $before = $shipment->fresh()->getAttributes();
        $this->postJson('/consignments/imports/'.$this->batch->uuid.'/confirm', ['included' => [$this->row->id => 1]])
            ->assertStatus(422)->assertJsonValidationErrors('import_rows');
        $this->assertSame('skipped', $this->row->fresh()->status);
        $this->assertSame($before, $shipment->fresh()->getAttributes());
        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseCount('shipments', 1);
        $this->postJson($this->url(), ['version' => 0, 'create' => true, 'first_name' => 'Another', 'last_name' => 'Person', 'phone' => '0950000000'])
            ->assertStatus(422)->assertJsonValidationErrors('customer');
        $this->assertDatabaseCount('clients', 0);
    }

    public function test_code_from_another_consignment_is_never_reassigned(): void
    {
        $consignment = $this->prepareReimport();
        $other = \App\Models\Consignment::create(['consignment_code' => 'OTHER', 'name' => 'Other']);
        $shipment = $this->existingParcel($other->id);
        $this->postJson('/consignments/imports/'.$this->batch->uuid.'/confirm', ['included' => [$this->row->id => 1]])->assertStatus(422);
        $this->assertSame('conflict', $this->row->fresh()->status);
        $this->assertEquals($other->id, $shipment->fresh()->consignment_id);
        $this->assertDatabaseCount('clients', 0);
    }

    public function test_parcel_created_after_validation_is_skipped_inside_confirmation(): void
    {
        $consignment = $this->prepareReimport();
        $this->row->update(['raw_values' => ['A' => 'TEST001', 'B' => 'New Customer', 'C' => '0950000000', 'D' => 'Lusaka']]);
        $inserted = false;
        $shipment = null;
        DB::listen(function ($query) use (&$inserted, &$shipment, $consignment) {
            if (!$inserted && str_contains($query->sql, 'select * from "packages"')) {
                $inserted = true;
                $shipment = $this->existingParcel($consignment->id);
            }
        });
        $this->post('/consignments/imports/'.$this->batch->uuid.'/confirm', ['included' => [$this->row->id => 1]])->assertSessionHasNoErrors();
        $this->assertTrue($inserted);
        $this->assertSame('skipped', $this->row->fresh()->status);
        $this->assertSame(0, $this->batch->fresh()->result['created']);
        $this->assertSame(1, $this->batch->fresh()->result['skipped']);
        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseCount('shipments', 1);
        $this->assertEquals(500, $shipment->fresh()->shipping_cost);
    }
}
