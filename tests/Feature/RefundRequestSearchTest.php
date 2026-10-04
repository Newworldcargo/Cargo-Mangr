<?php

namespace Tests\Feature;

use App\Models\{RefundRequest, Transxn, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Gate};
use Modules\Cargo\Entities\{Client, Shipment};
use Modules\Cargo\Http\Controllers\RefundRequestController;
use Tests\TestCase;

class RefundRequestSearchTest extends TestCase
{
    use RefreshDatabase;
    private bool $allowed = true;
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::create(['name' => 'Approver Example', 'email' => 'approver@example.test', 'password' => bcrypt('test'), 'role' => 1]);
        $this->actingAs($this->actor);
        Gate::before(fn ($user, $ability) => $ability === 'approve-refund-requests' ? $this->allowed : null);
    }

    private function refund(string $code = 'SEARCH-SHIP', string $status = 'approved'): RefundRequest
    {
        $client = Client::firstOrCreate(['user_id' => $this->actor->id], ['name' => 'Jane Customer', 'email' => 'jane@example.test', 'responsible_mobile' => '260970001234', 'code' => 1]);
        $shipment = Shipment::create(['code' => $code, 'client_id' => $client->id, 'type' => 1, 'status_id' => 1, 'shipping_date' => '2026-10-04']);
        $invoice = Transxn::create(['shipment_id' => $shipment->id, 'receipt_number' => 'RECEIPT-'.$code, 'total' => 100, 'currency' => 'ZMW', 'status' => 'refunded']);
        return RefundRequest::create(['shipment_id' => $shipment->id, 'transxn_id' => $invoice->id, 'requested_by' => $this->actor->id,
            'reviewed_by' => $this->actor->id, 'status' => $status, 'refund_type' => 'full', 'amount' => 100, 'reason' => 'Damaged packaging']);
    }

    private function page(array $params = [])
    {
        return (new RefundRequestController())->index(Request::create('/refund-requests', 'GET', $params));
    }

    public function test_search_matches_shipment_customer_receipt_reason_staff_and_ids(): void
    {
        $record = $this->refund();
        foreach (['SEARCH-SHIP', 'Jane', 'jane@example.test', '970001234', 'RECEIPT-SEARCH', 'Damaged', 'Approver', (string)$record->id] as $term) {
            $results = $this->page(['q' => $term])->getData()['refundRequests'];
            $this->assertSame($record->id, $results->sole()->id, $term);
        }
        $this->assertSame(0, $this->page(['q' => 'no-such-refund'])->getData()['refundRequests']->total());
    }

    public function test_pagination_keeps_search_status_and_page_size_without_overlapping_rows(): void
    {
        for ($i = 0; $i < 25; $i++) $this->refund('MATCH-'.$i);
        $this->refund('MATCH-PENDING', 'pending');
        $this->refund('OTHER');
        $params = ['q' => 'MATCH', 'status' => 'approved', 'per_page' => 20];
        $first = $this->page($params)->getData()['refundRequests'];
        $second = $this->page($params + ['page' => 2])->getData()['refundRequests'];
        $this->assertSame(25, $first->total());
        $this->assertCount(20, $first);
        $this->assertCount(5, $second);
        $this->assertEmpty(array_intersect($first->pluck('id')->all(), $second->pluck('id')->all()));
        parse_str(parse_url($first->nextPageUrl(), PHP_URL_QUERY), $query);
        $this->assertSame(['q' => 'MATCH', 'status' => 'approved', 'per_page' => '20', 'page' => '2'], $query);
        $this->assertCount(25, $this->page(['q' => 'MATCH', 'status' => 'approved', 'per_page' => 50])->getData()['refundRequests']);
        DB::table('currencies')->where('code', 'USD')->update(['default' => 1]);
        $html = $this->page($params)->render();
        $this->assertStringContainsString('Showing 1-20 of 25 refunds', $html);
        $this->assertStringContainsString('refund-search', $html);
        $this->assertStringContainsString('page=2', $html);
        if (getenv('NWC_REFUND_PREVIEW') === '1') file_put_contents('/tmp/nwc-refund-search.html', $html);
    }

    public function test_wildcards_are_literal_and_empty_page_size_uses_default(): void
    {
        $record = $this->refund();
        $this->assertSame(0, $this->page(['q' => '%'])->getData()['refundRequests']->total());
        $this->assertSame(0, $this->page(['q' => '_'])->getData()['refundRequests']->total());
        $record->update(['reason' => '100% adjustment']);
        $this->assertSame(1, $this->page(['q' => '%'])->getData()['refundRequests']->total());
        $this->assertSame(20, $this->page(['per_page' => null])->getData()['refundRequests']->perPage());
    }

    public function test_invalid_page_size_is_rejected(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->page(['per_page' => 100000]);
    }

    public function test_search_does_not_bypass_refund_permission(): void
    {
        $this->allowed = false;
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->page(['q' => 'MATCH']);
    }
}
