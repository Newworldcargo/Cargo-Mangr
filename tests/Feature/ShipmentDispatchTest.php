<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Cargo\Entities\Branch;
use Modules\Cargo\Entities\Driver;
use Modules\Cargo\Entities\Shipment;
use Modules\Cargo\Entities\Staff;
use Modules\Cargo\Services\ShipmentDispatchService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ShipmentDispatchTest extends TestCase
{
    use RefreshDatabase;

    private function setupShipment(): array
    {
        $admin = User::create(['name' => 'Dispatch Admin', 'email' => 'dispatch@example.test', 'password' => bcrypt('test'), 'role' => User::ADMIN]);
        $this->actingAs($admin);
        $branch = Branch::create(['name' => 'Test branch', 'code' => 901, 'user_id' => $admin->id, 'email' => 'branch@example.test']);
        $shipment = Shipment::create(['code' => 'DISPATCH-TEST', 'branch_id' => $branch->id, 'status_id' => Shipment::PENDING_STATUS, 'type' => Shipment::DROPOFF, 'shipping_date' => now()->toDateString()]);
        return [$admin, $branch, $shipment];
    }

    public function test_optional_driver_repeat_submission_and_audit_without_status_changes(): void
    {
        [$admin, $branch, $shipment] = $this->setupShipment();
        $service = app(ShipmentDispatchService::class);
        $first = $service->send($admin, $shipment->id, null);
        $second = $service->send($admin, $shipment->id, null);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('shipment_dispatch_entries', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertSame(Shipment::PENDING_STATUS, (int) $shipment->fresh()->status_id);
        $this->assertNull($shipment->fresh()->dispatch_time);
        $driver = Driver::create(['name' => 'Test Driver', 'email' => 'driver@example.test', 'code' => 901, 'user_id' => $admin->id, 'branch_id' => $branch->id]);
        $service->send($admin, $shipment->id, $driver->id);
        $service->send($admin, $shipment->id, null);
        $this->assertSame($driver->id, (int) $shipment->fresh()->captain_id);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_foreign_branch_driver_is_rejected_without_queue_entry(): void
    {
        [$admin, , $shipment] = $this->setupShipment();
        $driver = Driver::create(['name' => 'Other Driver', 'email' => 'other@example.test', 'code' => 902, 'user_id' => $admin->id, 'branch_id' => 9999]);
        try {
            app(ShipmentDispatchService::class)->send($admin, $shipment->id, $driver->id);
            $this->fail('Expected invalid driver to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('driver_id', $exception->errors());
        }
        $this->assertDatabaseCount('shipment_dispatch_entries', 0);
    }

    public function test_delivered_shipment_cannot_be_queued(): void
    {
        [$admin, , $shipment] = $this->setupShipment();
        $shipment->update(['status_id' => Shipment::DELIVERED_STATUS]);
        $this->expectException(ValidationException::class);
        app(ShipmentDispatchService::class)->send($admin, $shipment->id, null);
    }

    public function test_role_permission_does_not_bypass_branch_boundary(): void
    {
        [, $branch, $shipment] = $this->setupShipment();
        $group = DB::table('permission_groups')->insertGetId(['name' => 'dispatch-test']);
        Permission::firstOrCreate(['name' => 'manage-dispatch', 'guard_name' => 'web'], ['permission_group_id' => $group]);
        $staff = User::create(['name' => 'Staff', 'email' => 'staff@example.test', 'password' => bcrypt('test'), 'role' => User::STAFF]);
        Staff::create(['code' => 999, 'user_id' => $staff->id, 'branch_id' => $branch->id, 'responsible_mobile' => '000']);
        $service = app(ShipmentDispatchService::class);
        $this->assertFalse($service->canManage($staff, $shipment));
        $staff->givePermissionTo('manage-dispatch');
        $this->assertTrue($service->canManage($staff, $shipment));
        $shipment->branch_id = 9999;
        $this->assertFalse($service->canManage($staff, $shipment));
    }

    public function test_mission_driver_cannot_be_overwritten(): void
    {
        [$admin, $branch, $shipment] = $this->setupShipment();
        $driver = Driver::create(['name' => 'Driver', 'email' => 'driver@example.test', 'code' => 903, 'user_id' => $admin->id, 'branch_id' => $branch->id]);
        $shipment->update(['mission_id' => 123]);
        $this->expectException(ValidationException::class);
        app(ShipmentDispatchService::class)->send($admin, $shipment->id, $driver->id);
    }

    public function test_queue_page_and_http_submission(): void
    {
        [$admin, , $shipment] = $this->setupShipment();
        DB::table('currencies')->where('code', 'USD')->update(['default' => 1]);
        $this->postJson(route('shipments.dispatch.store', $shipment->id), ['driver_id' => null])->assertOk();
        $this->get(route('shipments.dispatch.index'))->assertOk()->assertSee('DISPATCH-TEST')->assertSee('Unassigned');
        $this->get(route('shipments.dispatch.index', ['search' => 'no-match']))->assertOk()->assertDontSee('DISPATCH-TEST');
        $this->getJson(route('shipments.dispatch.drivers', $shipment->id))->assertOk()->assertJson(['drivers' => []]);
        auth()->logout();
        $this->postJson(route('shipments.dispatch.store', $shipment->id), [])->assertUnauthorized();
    }
}
