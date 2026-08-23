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

    public function index()
    {
        $transactions = Transxn::with('shipment.client')
            ->orderBy('created_at', 'desc')
            ->get();
            
        $completedTransactions = $transactions->filter(fn($txn) => $txn->isCompleted());

        $sumCompletedBetween = fn (Carbon $start, Carbon $end) =>
            $completedTransactions->whereBetween('created_at', [$start, $end])->sum('total');

        $totals = [
            'todate' => $completedTransactions->sum('total'),
            'today' => $sumCompletedBetween(Carbon::today(), Carbon::now()),
            'yesterday' => $sumCompletedBetween(Carbon::yesterday(), Carbon::today()),
            'this_week' => $sumCompletedBetween(Carbon::now()->startOfWeek(), Carbon::now()),
            'this_month' => $sumCompletedBetween(Carbon::now()->startOfMonth(), Carbon::now()),
        ];
        
        $refundedTransactions = $transactions->filter(function ($transaction) {
            return $transaction->isRefunded() || $transaction->isPartiallyRefunded();
        });

        $resolveRefundAmount = function ($transaction) {
            $amount = (float) ($transaction->refunded_amount ?? 0);
            if ($transaction->isRefunded() && $amount <= 0) {
                $amount = (float) $transaction->total;
            }
            return $amount;
        };

        $refundDateInRange = function ($transaction, Carbon $start, Carbon $end) {
            $date = $transaction->refunded_at ?? $transaction->created_at;
            return $date && $date->between($start, $end);
        };

        $refundedTotals = [
            'todate' => $refundedTransactions->sum($resolveRefundAmount),
            'today' => $refundedTransactions->filter(fn($txn) => $refundDateInRange($txn, Carbon::today(), Carbon::now()))->sum($resolveRefundAmount),
            'yesterday' => $refundedTransactions->filter(fn($txn) => $refundDateInRange($txn, Carbon::yesterday(), Carbon::today()))->sum($resolveRefundAmount),
            'this_week' => $refundedTransactions->filter(fn($txn) => $refundDateInRange($txn, Carbon::now()->startOfWeek(), Carbon::now()))->sum($resolveRefundAmount),
            'this_month' => $refundedTransactions->filter(fn($txn) => $refundDateInRange($txn, Carbon::now()->startOfMonth(), Carbon::now()))->sum($resolveRefundAmount),
        ];
        
        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return view('cargo::' . $adminTheme . '.pages.transxns.index', compact('transactions','totals', 'refundedTotals'));
    }

}
