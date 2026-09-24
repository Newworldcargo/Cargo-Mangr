<?php

namespace Tests\Feature;

use App\Http\Controllers\ConsignmentController;
use App\Models\Consignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ConsignmentTrackerDateTest extends TestCase
{
    use RefreshDatabase;

    private function consignment(): Consignment
    {
        $this->actingAs(User::create(['name' => 'Tracker test', 'email' => 'tracker@example.test',
            'password' => bcrypt('test'), 'role' => User::ADMIN]));
        return Consignment::create(['name' => 'Date test', 'consignment_code' => 'DATE-TEST', 'cargo_type' => 'air']);
    }

    public function test_backdated_event_keeps_save_timestamp_and_displays_selected_date(): void
    {
        $consignment = $this->consignment();
        $date = now()->subDays(2)->format('Y-m-d\TH:i');
        $controller = app(ConsignmentController::class);
        $controller->updateTracker(Request::create('/', 'PATCH', ['status' => 1, 'completed_at' => $date]), $consignment->id);
        $history = $consignment->trackingHistory()->firstOrFail();
        $this->assertSame($date, $history->completed_at->format('Y-m-d\TH:i'));
        $this->assertTrue($history->created_at->greaterThan($history->completed_at));
        $this->assertTrue($consignment->fresh()->updated_at->greaterThan($history->completed_at));
        $data = $controller->getCurrentStage(Request::create('/', 'GET', ['consignment_id' => $consignment->id]))->getData(true);
        $this->assertSame($date, $data['completed_at_local']);
        $this->assertEquals(1, $data['stage_id']);
    }

    public function test_same_stage_date_correction_retains_previous_history(): void
    {
        $consignment = $this->consignment();
        $consignment->updateTrackingStage(1);
        $date = now()->subDays(3)->format('Y-m-d\TH:i');
        $controller = app(ConsignmentController::class);
        $controller->updateTracker(Request::create('/', 'PATCH', ['status' => 1, 'completed_at' => $date]), $consignment->id);
        $this->assertSame(2, $consignment->trackingHistory()->count());
        $data = $controller->getCurrentStage(Request::create('/', 'GET', ['consignment_id' => $consignment->id]))->getData(true);
        $this->assertSame($date, $data['completed_at_local']);
    }

    public function test_omitted_date_preserves_existing_client_behaviour(): void
    {
        $consignment = $this->consignment();
        app(ConsignmentController::class)->updateTracker(Request::create('/', 'PATCH', ['status' => 1]), $consignment->id);
        $this->assertTrue($consignment->trackingHistory()->firstOrFail()->completed_at->isToday());
    }

    public function test_invalid_and_future_dates_do_not_write_tracking_history(): void
    {
        $consignment = $this->consignment();
        foreach (['invalid', '2026-02-30T12:00', now()->addDay()->format('Y-m-d\TH:i')] as $date) {
            try {
                app(ConsignmentController::class)->updateTracker(Request::create('/', 'PATCH', ['status' => 1, 'completed_at' => $date]), $consignment->id);
                $this->fail('Invalid date accepted');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('completed_at', $error->errors());
            }
        }
        $this->assertSame(0, $consignment->trackingHistory()->count());
    }
}
