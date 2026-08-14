<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transxn;
use Carbon\Carbon;

class TransxnController extends Controller
{
    public function __construct()
    {
        // Finance transactions are accessible to admin/staff (permission gate) and
        // to branch users (role 3), whose data is scoped to their own branch.
        $this->middleware(function ($request, $next) {
            if (auth()->check() && (int) auth()->user()->role === 3) {
                return $next($request);
            }
            return $next($request);
        })->only('index');
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
    private function periodTotals(Carbon $start, Carbon $end, $scopeQuery = null): array
    {
        $q = Transxn::whereIn('status', ['completed', 'refund_requested', 'partially_refunded'])
            ->whereBetween('created_at', [$start, $end]);
        if ($scopeQuery) { $scopeQuery($q); }
        $totals = $q
            ->selectRaw('COUNT(*) AS cnt, COALESCE(SUM(total),0) AS total')
            ->first();

        $rq = Transxn::whereNotNull('refunded_at')
            ->whereBetween('refunded_at', [$start, $end]);
        if ($scopeQuery) { $scopeQuery($rq); }
        $refunds = $rq
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

        // Branch users (role 3) only see transactions of their own branch.
        $branchCurrency = 'ZMW';
        $userRole = auth()->check() ? (int) auth()->user()->role : 1;
        $branchQuery = function ($q) use (&$branchCurrency) {
            if ($this->isBranchUser()) {
                $branch = \Modules\Cargo\Entities\Branch::where('user_id', auth()->id())->first();
                if ($branch) {
                    $branchId = $branch->id;
                    $branchCurrency = $branch->default_currency ?? 'ZMW';
                    $q->whereHas('shipment', function ($qs) use ($branchId) {
                        $qs->where('branch_id', $branchId);
                    });
                }
            }
        };

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
            $p = $this->periodTotals($start, $end, $branchQuery);
            $totals[$key] = $p['completed'];
            $refundedTotals[$key] = $p['refunded'];
        }

        // Listing table: recent transactions only (paginated to limit memory),
        // scoped to the branch user's own branch when applicable.
        $listingQuery = Transxn::with(['shipment.client', 'shipment'])
            ->orderBy('created_at', 'desc')
            ->limit(500);
        if ($this->isBranchUser()) {
            $branch = \Modules\Cargo\Entities\Branch::where('user_id', auth()->id())->first();
            if ($branch) {
                $listingQuery->whereHas('shipment', function ($qs) use ($branch) {
                    $qs->where('branch_id', $branch->id);
                });
                if (empty($branchCurrency)) {
                    $branchCurrency = $branch->default_currency ?? 'ZMW';
                }
            }
        }
        $transactions = $listingQuery->get();

        // Display amounts in the viewing branch's currency (ZMW stored; converted here).
        $conv = function ($amount) use ($branchCurrency) {
            return convert_amount_to_branch_currency($amount, $branchCurrency);
        };
        $totals        = array_map($conv, $totals);
        $refundedTotals = array_map($conv, $refundedTotals);
        foreach ($transactions as $t) {
            $t->display_total = $conv($t->total);
            $t->display_refunded_amount = $conv($t->refunded_amount ?? 0);
        }
        $branchCurrency = $branchCurrency; // keep for the view

        $adminTheme = env('ADMIN_THEME', 'adminLte');

        return view('cargo::' . $adminTheme . '.pages.transxns.index', compact('transactions', 'totals', 'refundedTotals', 'branchCurrency'));
    }

    /**
     * Determine whether the authenticated user is a branch login (role 3).
     */
    private function isBranchUser(): bool
    {
        return auth()->check() && (int) auth()->user()->role === 3;
    }
}
