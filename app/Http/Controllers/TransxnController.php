<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transxn;
use Carbon\Carbon;

class TransxnController extends Controller
{
    public function __construct()
    {
        // check on permissions
        $this->middleware('can:access-finance-transactions')->only('index');
    }

    /**
     * Compute completed/refunded totals for a date range using SQL aggregates
     * instead of loading every transaction into memory.
     *
     * Mirrors the original PHP semantics:
     *  - completed  = status in (completed, refund_requested, partially_refunded)
     *  - refunded   = refunded_at is set; amount = refunded_amount when > 0,
     *                 otherwise the full transaction total
     */
    private function periodTotals(Carbon $start, Carbon $end): array
    {
        $totals = Transxn::whereIn('status', ['completed', 'refund_requested', 'partially_refunded'])
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('COUNT(*) AS cnt, COALESCE(SUM(total),0) AS total')
            ->first();

        $refunds = Transxn::whereNotNull('refunded_at')
            ->whereBetween('refunded_at', [$start, $end])
            ->selectRaw("COUNT(*) AS cnt, COALESCE(SUM(
                CASE WHEN COALESCE(refunded_amount,0) <= 0 THEN total
                     ELSE refunded_amount END
            ),0) AS total")
            ->first();

        return [
            'completed'       => (float) ($totals->total ?? 0),
            'completed_count' => (int) ($totals->cnt ?? 0),
            'refunded'        => (float) ($refunds->total ?? 0),
            'refunded_count'  => (int) ($refunds->cnt ?? 0),
        ];
    }

    public function index()
    {
        $now = Carbon::now();

        $periods = [
            'todate'     => [Carbon::parse('1970-01-01'), $now],
            'today'      => [$now->copy()->startOfDay(), $now],
            'yesterday'  => [$now->copy()->subDay()->startOfDay(), $now->copy()->startOfDay()],
            'this_week'  => [$now->copy()->startOfWeek(), $now],
            'this_month' => [$now->copy()->startOfMonth(), $now],
        ];

        $totals = [];
        $refundedTotals = [];
        foreach ($periods as $key => [$start, $end]) {
            $p = $this->periodTotals($start, $end);
            $totals[$key] = $p['completed'];
            $refundedTotals[$key] = $p['refunded'];
        }

        // Listing table: recent transactions only (paginated to limit memory).
        $transactions = Transxn::with('shipment.client')
            ->orderBy('created_at', 'desc')
            ->limit(500)
            ->get();

        $adminTheme = env('ADMIN_THEME', 'adminLte');

        return view('cargo::' . $adminTheme . '.pages.transxns.index', compact('transactions', 'totals', 'refundedTotals'));
    }
}
