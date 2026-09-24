<?php

namespace Tests\Feature;

use App\Http\Controllers\ConsignmentController;
use App\Models\Consignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ConsignmentCargoTabsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabs_filter_by_cargo_type_and_keep_global_counts(): void
    {
        // The legacy production column is absent from the SQLite migration baseline.
        if (!\Illuminate\Support\Facades\Schema::hasColumn('shipments', 'consignment_id')) {
            \Illuminate\Support\Facades\Schema::table('shipments', function ($table) {
                $table->unsignedBigInteger('consignment_id')->nullable();
            });
        }
        foreach (['air', 'sea', 'sea'] as $index => $type) {
            Consignment::create(['name' => 'Tab test', 'consignment_code' => 'TAB-' . $index, 'cargo_type' => $type]);
        }
        foreach (['all' => 3, 'air' => 1, 'sea' => 2, 'invalid' => 3] as $type => $count) {
            $data = app(ConsignmentController::class)->index(Request::create('/consignment', 'GET', ['cargo_type' => $type]))->getData();
            $this->assertSame($count, $data['consignments']->total());
            $this->assertEquals(['all' => 3, 'air' => 1, 'sea' => 2], $data['consignmentCounts']);
            $this->assertSame($type === 'invalid' ? 'all' : $type, $data['cargoType']);
            if (in_array($type, ['air', 'sea'], true)) {
                $this->assertSame([$type], $data['consignments']->pluck('cargo_type')->unique()->values()->all());
                $this->assertStringContainsString('cargo_type=' . $type, $data['consignments']->url(2));
            }
        }
        $default = app(ConsignmentController::class)->index(Request::create('/consignment'))->getData();
        $this->assertSame('all', $default['cargoType']);
        $this->assertSame(3, $default['consignments']->total());
    }
}
