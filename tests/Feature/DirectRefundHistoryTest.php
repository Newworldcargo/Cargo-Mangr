<?php

namespace Tests\Feature;

use App\Models\{GeneralSettings, RefundRequest, Transxn, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Gate, Route};
use Modules\Cargo\Entities\Shipment;
use Modules\Cargo\Http\Controllers\ShipmentController;
use Tests\TestCase;

class DirectRefundHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function setupRefund(bool $allowed = true): Shipment
    {
        GeneralSettings::fake(['enable_refund_payments' => true]);
        $user = User::create(['name' => 'Refund approver', 'email' => 'refund@example.test', 'password' => bcrypt('test'), 'role' => 1]);
        $this->actingAs($user);
        Gate::before(fn ($user, $ability) => $ability === 'approve-refund-requests' ? $allowed : null);
        Route::post('/_test/direct-refund', [ShipmentController::class, 'refundPayment'])->middleware('web');
        $shipment = Shipment::create(['client_id' => 1, 'code' => 'REFUND-HISTORY', 'paid' => 1,
            'status_id' => Shipment::PENDING_STATUS, 'type' => Shipment::DROPOFF, 'shipping_date' => now()->toDateString()]);
        Transxn::create(['shipment_id' => $shipment->id, 'receipt_number' => 'REFUND-HISTORY', 'total' => 100, 'currency' => 'ZMW', 'status' => 'completed']);
        return $shipment;
    }

    public function test_direct_refund_creates_approved_history_with_actor_and_actual_amount(): void
    {
        $shipment = $this->setupRefund();
        $payload = ['shipment_id' => $shipment->id, 'refund_type' => 'full', 'reason' => 'Customer cancellation'];
        $this->postJson('/_test/direct-refund', $payload)->assertOk()->assertJsonPath('success', true);
        $record = RefundRequest::sole();
        $this->assertSame('approved', $record->status);
        $this->assertSame('100.00', $record->amount);
        $this->assertEquals(auth()->id(), $record->requested_by);
        $this->assertEquals(auth()->id(), $record->reviewed_by);
        $this->assertEquals($shipment->receipt->id, $record->transxn_id);
        $this->assertNotNull($record->refunded_at);
        $this->assertSame('Customer cancellation', $record->reason);
        $this->assertDatabaseHas('audit_logs', ['event' => 'refund_processed']);
        $controller = new \Modules\Cargo\Http\Controllers\RefundRequestController();
        $page = $controller->index(\Illuminate\Http\Request::create('/', 'GET', ['status' => 'approved']));
        $this->assertSame($record->id, $page->getData()['refundRequests']->first()->id);
        $pending = $controller->index(\Illuminate\Http\Request::create('/', 'GET', ['status' => 'pending']));
        $this->assertCount(0, $pending->getData()['refundRequests']);
        $this->postJson('/_test/direct-refund', $payload)->assertStatus(400);
        $this->assertDatabaseCount('refund_requests', 1);
    }

    public function test_partial_refund_records_only_the_refunded_amount(): void
    {
        $shipment = $this->setupRefund();
        $this->postJson('/_test/direct-refund', ['shipment_id' => $shipment->id, 'refund_type' => 'partial', 'amount' => 25, 'reason' => 'Adjustment'])->assertOk();
        $this->assertSame('25.00', RefundRequest::sole()->amount);
        $this->assertSame('partially_refunded', $shipment->receipt->status);
    }

    public function test_missing_permission_cannot_create_history(): void
    {
        $shipment = $this->setupRefund(false);
        $this->postJson('/_test/direct-refund', ['shipment_id' => $shipment->id, 'refund_type' => 'full', 'reason' => 'No approval'])->assertForbidden();
        $this->assertDatabaseCount('refund_requests', 0);
        $this->assertSame('completed', $shipment->receipt->status);
    }

    public function test_excessive_refund_cannot_create_history_or_change_money(): void
    {
        $shipment = $this->setupRefund();
        $this->postJson('/_test/direct-refund', ['shipment_id' => $shipment->id, 'refund_type' => 'partial', 'amount' => 200, 'reason' => 'Invalid amount'])->assertStatus(422);
        $this->assertDatabaseCount('refund_requests', 0);
        $this->assertSame('completed', $shipment->receipt->status);
    }

    public function test_audit_failure_rolls_back_both_refund_and_history(): void
    {
        $shipment = $this->setupRefund();
        $this->mock(\App\Services\AuditLogService::class, fn ($mock) => $mock->shouldReceive('createLog')->once()->andThrow(new \RuntimeException('Audit unavailable')));
        $this->postJson('/_test/direct-refund', ['shipment_id' => $shipment->id, 'refund_type' => 'full', 'reason' => 'Cancellation'])->assertStatus(422);
        $this->assertDatabaseCount('refund_requests', 0);
        $this->assertSame('completed', $shipment->receipt->status);
        $this->assertEquals(1, $shipment->fresh()->paid);
    }
}
