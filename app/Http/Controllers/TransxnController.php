<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transxn;
use App\Services\FinancialTransactionScopeService;
use Carbon\Carbon;

class TransxnController extends Controller
{
    public function __construct(private readonly FinancialTransactionScopeService $transactionScope)
    {
        $this->middleware('auth')->only('index');
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
    private function periodTotals(Carbon $start, Carbon $end, $scopeQuery = null, string $displayCurrency = 'ZMW'): array
    {
        $q = Transxn::whereIn('status', ['completed', 'refund_requested', 'partially_refunded'])
            ->whereBetween('created_at', [$start, $end]);
        if ($scopeQuery) { $scopeQuery($q); }
        $completedRows = $q
            ->selectRaw('currency, COUNT(*) AS cnt, COALESCE(SUM(total),0) AS total')
            ->groupBy('currency')
            ->get();

        $rq = Transxn::whereNotNull('refunded_at')
            ->whereBetween('refunded_at', [$start, $end]);
        if ($scopeQuery) { $scopeQuery($rq); }
        $refundRows = $rq
            ->selectRaw("currency, COUNT(*) AS cnt, COALESCE(SUM(
                CASE WHEN COALESCE(refunded_amount,0) <= 0 THEN total
                     ELSE refunded_amount END
            ),0) AS total")
            ->groupBy('currency')
            ->get();

        return [
            'completed'       => $completedRows->sum(fn ($row) => $this->displayAmount($row->total, $row->currency, $displayCurrency)),
            'completed_count' => $completedRows->sum('cnt'),
            'refunded'        => $refundRows->sum(fn ($row) => $this->displayAmount($row->total, $row->currency, $displayCurrency)),
            'refunded_count'  => $refundRows->sum('cnt'),
        ];
    }

    public function index()
    {
        $now = Carbon::now();

        $viewer = auth()->user();
        $branchCurrency = 'ZMW';
        $viewerBranch = $this->viewerBranch($viewer);
        if ($viewerBranch) {
            $branchCurrency = $viewerBranch->default_currency ?? 'ZMW';
        }

        $scopeQuery = fn ($query) => $this->transactionScope->apply($query, $viewer);

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
            $p = $this->periodTotals($start, $end, $scopeQuery, $branchCurrency);
            $totals[$key] = $p['completed'];
            $refundedTotals[$key] = $p['refunded'];
        }

        $listingQuery = $this->transactionScope->apply(Transxn::with(['shipment.client', 'shipment']), $viewer)
            ->orderBy('created_at', 'desc')
            ->limit(500);
        $transactions = $listingQuery->get();

        foreach ($transactions as $t) {
            $displayCurrency = $t->currency ?: $branchCurrency;
            $t->display_currency = $displayCurrency;
            $t->display_total = $t->currency
                ? (float) $t->total
                : $this->displayAmount($t->total, null, $displayCurrency);
            $t->display_refunded_amount = $t->currency
                ? (float) ($t->refunded_amount ?? 0)
                : $this->displayAmount($t->refunded_amount ?? 0, null, $displayCurrency);
        }
        $branchCurrency = $branchCurrency; // keep for the view

        $adminTheme = env('ADMIN_THEME', 'adminLte');

        return view('cargo::' . $adminTheme . '.pages.transxns.index', compact('transactions', 'totals', 'refundedTotals', 'branchCurrency'));
    }

    private function viewerBranch($user)
    {
        if ((int) $user->role === 3) {
            return \Modules\Cargo\Entities\Branch::where('user_id', $user->id)->first();
        }

        if (in_array((int) $user->role, [\App\Models\User::STAFF, 2], true)) {
            $branchId = \Modules\Cargo\Entities\Staff::where('user_id', $user->id)->value('branch_id');
            return $branchId ? \Modules\Cargo\Entities\Branch::find($branchId) : null;
        }

        return null;
    }

    private function displayAmount($amount, ?string $storedCurrency, string $displayCurrency): float
    {
        $from = strtoupper($storedCurrency ?: 'ZMW');
        $to = strtoupper($displayCurrency ?: 'ZMW');
        $amount = (float) $amount;

        if ($from === $to) {
            return $amount;
        }

        if ($from === 'ZMW') {
            return convert_amount_to_branch_currency($amount, $to);
        }

        $zmwAmount = convert_currency($amount, strtolower($from), 'zmw');
        if ($to === 'ZMW') {
            return (float) $zmwAmount;
        }

        return convert_amount_to_branch_currency($zmwAmount, $to);
    }
}
