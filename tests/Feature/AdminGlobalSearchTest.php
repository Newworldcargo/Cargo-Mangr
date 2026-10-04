<?php

namespace Tests\Feature;

use App\Http\Controllers\SearchController;
use App\Models\{User, Transxn};
use App\Services\AdminGlobalSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, Gate};
use Modules\Cargo\Entities\{Client, Shipment};
use Tests\TestCase;

class AdminGlobalSearchTest extends TestCase
{
    use RefreshDatabase;
    private array $permissions = ['use-global-search', 'view-shipments', 'view-consignments'];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $user = User::create(['name' => 'Search Staff', 'email' => 'search@example.test', 'password' => bcrypt('test'), 'role' => 0]);
        $this->actingAs($user);
        Gate::before(fn ($user, $ability) => in_array($ability, $this->permissions, true));
    }

    private function shipment(string $code = 'LE2412-TEST'): Shipment
    {
        $client = Client::firstOrCreate(['user_id' => auth()->id()], ['name' => 'Jane Customer', 'email' => 'jane@example.test', 'code' => 1]);
        return Shipment::create(['code' => $code, 'client_id' => $client->id, 'client_phone' => '+260 (972) 827-372',
            'client_phone_2' => '0977 334 617', 'client_address' => 'Old Road', 'type' => 1, 'status_id' => 1, 'shipping_date' => '2026-10-04']);
    }

    public function test_indexed_identifiers_normalized_phones_names_and_receipts(): void
    {
        $shipment = $this->shipment();
        Transxn::create(['shipment_id' => $shipment->id, 'receipt_number' => 'REC-SEARCH', 'total' => 100, 'currency' => 'ZMW', 'status' => 'pending']);
        foreach (['LE2412', '0972827372', '972827372', '+260 972 827 372', '260977334617', 'Jane', 'jane@example', 'REC-SEARCH'] as $term) {
            $this->assertEquals([$shipment->id], app(AdminGlobalSearch::class)->shipments($term, true)->pluck('shipments.id')->all(), $term);
        }
        $shipment->update(['client_phone' => '0976 111 222']);
        $this->assertEquals([$shipment->id], app(AdminGlobalSearch::class)->shipments('260976111222', true)->pluck('shipments.id')->all());
    }

    public function test_exact_match_precedes_newer_prefix_and_wildcards_are_literal(): void
    {
        $exact = $this->shipment('LE2412'); $this->shipment('LE2412-NEW');
        $this->assertEquals($exact->id, app(AdminGlobalSearch::class)->shipments('LE2412', true)->first()->id);
        foreach (['%', '_', 'absent-term'] as $term) $this->assertCount(0, app(AdminGlobalSearch::class)->shipments($term, true)->get());
        $this->assertCount(2, app(AdminGlobalSearch::class)->shipments('Old Road', false)->get());
    }

    public function test_dropdown_is_small_and_permission_changes_do_not_leak_cached_results(): void
    {
        for ($i = 0; $i < 5; $i++) $this->shipment('LE2412-' . $i);
        $controller = new SearchController();
        $request = Request::create('/search/live', 'GET', ['q' => 'LE2412']);
        $data = $controller->liveSearch($request)->getData(true);
        $this->assertCount(3, $data['results']['shipments']['data']);
        $this->assertTrue($data['results']['shipments']['hasMore']);
        $this->assertSame(3, $data['total']);
        $this->permissions = ['use-global-search', 'view-consignments'];
        $this->assertArrayNotHasKey('shipments', $controller->liveSearch($request)->getData(true)['results']);
        $this->permissions = [];
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $controller->liveSearch($request);
    }

    public function test_full_results_paginate_and_keep_query(): void
    {
        for ($i = 0; $i < 25; $i++) $this->shipment('LE2412-' . $i);
        $controller = new SearchController();
        $first = $controller->index(Request::create('/search', 'GET', ['q' => 'LE2412']))->getData()['results']['shipments'];
        $second = $controller->index(Request::create('/search', 'GET', ['q' => 'LE2412', 'shipments_page' => 2]))->getData()['results']['shipments'];
        $this->assertCount(20, $first['data']); $this->assertCount(5, $second['data']);
        $this->assertEmpty(array_intersect(array_column($first['data'], 'id'), array_column($second['data'], 'id')));
        $this->assertStringContainsString('q=LE2412', $first['pagination']->nextPageUrl());
        \Illuminate\Support\Facades\DB::table('currencies')->where('code', 'USD')->update(['default' => 1]);
        $html = $controller->index(Request::create('/search', 'GET', ['q' => 'LE2412']))->render();
        $this->assertStringContainsString('shipments_page=2', $html);
        if (getenv('NWC_SEARCH_PREVIEW') === '1') file_put_contents('/tmp/nwc-search-preview.html', $html);
    }

    public function test_unlinked_user_does_not_return_ownerless_shipments(): void
    {
        $this->shipment()->update(['client_id' => null]);
        $unknown = User::create(['name' => 'No Customer', 'email' => 'no-customer@example.test', 'password' => bcrypt('test'), 'role' => 0]);
        $data = (new SearchController())->index(Request::create('/search', 'GET', ['user_id' => $unknown->id]))->getData();
        $this->assertEmpty($data['results']);
    }

    public function test_request_is_bounded_and_empty_search_does_not_list_everything(): void
    {
        $this->shipment();
        $controller = new SearchController();
        $this->assertSame([], $controller->liveSearch(Request::create('/search/live', 'GET', ['q' => ' ']))->getData(true)['results']);
        foreach ([['q' => ['bad']], ['q' => str_repeat('x', 101)], ['q' => 'test', 'shipments_page' => -1]] as $input) {
            try {
                $controller->liveSearch(Request::create('/search/live', 'GET', $input));
                $this->fail('Invalid query accepted');
            } catch (\Illuminate\Validation\ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }

    public function test_consignment_search_keeps_keywords_and_category_permissions(): void
    {
        $exact = \App\Models\Consignment::create(['consignment_code' => 'LE2412', 'name' => 'Ocean manifest', 'cargo_type' => 'sea']);
        \App\Models\Consignment::create(['consignment_code' => 'LE2412-NEW', 'name' => 'Other manifest', 'cargo_type' => 'air']);
        $service = app(AdminGlobalSearch::class);
        $this->assertSame($exact->id, $service->consignments('LE2412', true)->first()->id);
        $this->assertSame($exact->id, $service->consignments('Ocean', true)->sole()->id);
        $controller = new SearchController();
        $request = Request::create('/search/live', 'GET', ['q' => 'LE2412']);
        $this->assertArrayHasKey('consignments', $controller->liveSearch($request)->getData(true)['results']);
        $this->permissions = ['use-global-search', 'view-shipments'];
        $this->assertArrayNotHasKey('consignments', $controller->liveSearch($request)->getData(true)['results']);
    }
}
