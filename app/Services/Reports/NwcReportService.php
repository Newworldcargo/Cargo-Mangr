<?php

namespace App\Services\Reports;

use App\Models\Transxn;
use App\Models\User;
use App\Services\FinancialTransactionScopeService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class NwcReportService
{
    public function __construct(private readonly FinancialTransactionScopeService $transactionScope)
    {
    }
    /**
     * Resolve a start and end date for the report, defaulting to the current day.
     */
    public function resolveDateRange(?string $startDate, ?string $endDate): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfDay();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : ($startDate ? Carbon::parse($startDate)->endOfDay() : now()->endOfDay());

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start, $end];
    }

    /**
     * Fetch report rows for the given filters.
     */
    public function getReportData(array $filters = [], ?User $viewer = null, ?int $limit = null): Collection
    {
        [$start, $end] = $this->resolveDateRange($filters['start_date'] ?? null, $filters['end_date'] ?? null);

        $transactions = Transxn::query()
            ->with([
                'shipment.client',
                'shipment.consignment',
                'shipment.nwcReceipt.user',
                'nwcReceipt.user',
                'cashier',
                'shipment.paymentReceipts' // Include payment receipts for multiple payment support
            ])
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('status', ['completed', 'refund_requested', 'partially_refunded'])
            // Only include transactions for shipments that are currently marked as paid
            ->whereHas('shipment', function($query) {
                $query->where('paid', 1);
            })
            ->orderByDesc('created_at')
            ;

        if ($viewer) {
            $this->transactionScope->apply($transactions, $viewer);
        }
        if (!empty($filters['branch_id'])) {
            $transactions->whereHas('shipment', fn ($shipment) => $shipment->where('branch_id', $filters['branch_id']));
        }
        if (!empty($filters['user_id'])) {
            $transactions->where('cashier_user_id', $filters['user_id']);
        }
        if ($limit) {
            $transactions->limit($limit);
        }

        $transactions = $transactions->get();

        $results = collect();

        foreach ($transactions as $transaction) {
            $shipment = $transaction->shipment;
            $receipt = $transaction->nwcReceipt ?: optional($shipment)->nwcReceipt;

            // Check if there are multiple payment receipts for this shipment
            $multiplePaymentReceipts = $shipment->paymentReceipts ?? collect();
            // Filter out refunded payments
            $multiplePaymentReceipts = $multiplePaymentReceipts->where('refunded', false);
            
            if ($multiplePaymentReceipts->count() > 0) {
                // For multiple payments, aggregate them into a single row
                $totalBillKwacha = $multiplePaymentReceipts->sum('amount'); // This is the actual Kwacha amount paid
                
                // Calculate the USD amount by using the rate from the receipt if available
                if ($receipt && $receipt->rate && $receipt->rate > 0) {
                    $totalBillUsd = round($totalBillKwacha / $receipt->rate, 2);
                } else {
                    // Fallback: if no rate available, try to calculate from receipt if it has both bill values
                    if ($receipt && $receipt->bill_usd && $receipt->bill_kwacha && $receipt->bill_kwacha > 0) {
                        $calculatedRate = $receipt->bill_kwacha / $receipt->bill_usd;
                        $totalBillUsd = round($totalBillKwacha / $calculatedRate, 2);
                    } else {
                        // If no rate is available, we can't convert - so show Kwacha value in both as fallback
                        // Though this isn't ideal, it maintains backward compatibility
                        $totalBillUsd = $totalBillKwacha;
                    }
                }
                
                // Combine all payment methods into a single string
                $paymentMethods = $multiplePaymentReceipts->pluck('method_of_payment')->unique()->filter()->implode(', ');

                $paymentMethodGroups = [
                    'airtel' => ['airtel'],
                    'mtn' => ['mtn'],
                    'cash_payments' => ['cash'],
                    'invoice_payment' => ['invoice'],
                    'bank_transfer' => ['bank_transfer', 'bank'],
                    'card_payment' => ['card', 'card_payment'],
                    'zamtel' => ['zamtel'],
                    'other_payment' => ['other', 'others'],
                ];

                $methodTotals = [];
                foreach ($paymentMethodGroups as $key => $matches) {
                    $methodTotals[$key] = $this->sumPaymentAmounts($multiplePaymentReceipts, $matches);
                }

                $totalAirtel = $methodTotals['airtel'];
                $totalMtn = $methodTotals['mtn'];
                $totalCash = $methodTotals['cash_payments'];
                $totalInvoice = $methodTotals['invoice_payment'];
                $totalBankTransfer = $methodTotals['bank_transfer'];
                $totalCard = $methodTotals['card_payment'];
                $totalZamtel = $methodTotals['zamtel'];
                $totalOther = $methodTotals['other_payment'];

                $consignment = optional($shipment)->consignment;
                $client = optional($shipment)->client;

                // Get rate from main receipt if available
                $rate = $receipt?->rate;
                if (($rate === null || $rate == 0.0) && $totalBillUsd && $totalBillUsd != 0 && $totalBillKwacha) {
                    $rate = round($totalBillKwacha / $totalBillUsd, 6);
                }

                $cashierName = $multiplePaymentReceipts->first()?->cashier_name
                    ?: $transaction->cashier?->name
                    ?: $this->resolveCashierName($receipt);
                $cargoType = $consignment?->cargo_type ?? 'unknown';

                $results->push([
                    'date' => $transaction->created_at ?? now(),
                    'receipt_number' => $transaction->receipt_number,
                    'hawb_number' => optional($shipment)->code,
                    'consignee_name' => $consignment?->consignee ?? $consignment?->name,
                    'client_name' => $client?->name,
                    'rate' => $rate !== null ? (float) $rate : null,
                    'bill_usd' => $totalBillUsd,
                    'bill_kwacha' => $totalBillKwacha,
                    'method_of_payment' => $paymentMethods ?: $this->formatMethodLabel($receipt?->method_of_payment),
                    'method_slug' => $this->normalizeMethod($receipt?->method_of_payment),
                    'airtel' => $totalAirtel,
                    'mtn' => $totalMtn,
                    'cash_payments' => $totalCash,
                    'invoice_payment' => $totalInvoice,
                    'bank_transfer' => $totalBankTransfer,
                    'card_payment' => $totalCard,
                    'zamtel' => $totalZamtel,
                    'other_payment' => $totalOther,
                    'cashier_name' => $cashierName,
                    'cargo_type' => $cargoType,
                    'shipment' => $shipment,
                    'consignment' => $consignment,
                    'client' => $client,
                    'receipt' => $receipt,
                ]);
            } else {
                // When no multiple payments exist (legacy behavior), use original logic
                $consignment = optional($shipment)->consignment;
                $client = optional($shipment)->client;

                $billUsd = $receipt?->bill_usd;
                $billKwacha = $receipt?->bill_kwacha;

                if ($billUsd === null && $shipment) {
                    $billUsd = (float) $shipment->amount_to_be_collected;
                }

                if ($billKwacha === null && $billUsd !== null && function_exists('convert_currency')) {
                    try {
                        $billKwacha = convert_currency($billUsd, 'usd', 'zmw');
                    } catch (\Throwable $th) {
                        $billKwacha = null;
                    }
                }

                $rate = $receipt?->rate;
                if (($rate === null || $rate == 0.0) && $billUsd && $billUsd != 0 && $billKwacha) {
                    $rate = round($billKwacha / $billUsd, 6);
                }

                $method = $this->normalizeMethod($receipt?->method_of_payment);
                $methodLabel = $this->formatMethodLabel($receipt?->method_of_payment);

                $airtel = $method === 'airtel' ? (float) ($billKwacha ?? 0) : 0.0;
                $mtn = $method === 'mtn' ? (float) ($billKwacha ?? 0) : 0.0;
                $cashPayments = $method === 'cash' ? (float) ($billKwacha ?? 0) : 0.0;
                $invoicePayment = $method === 'invoice' ? (float) ($billKwacha ?? 0) : 0.0;
                $bankTransfer = ($method === 'bank_transfer' || $method === 'bank') ? (float) ($billKwacha ?? 0) : 0.0;
                $cardPayment = ($method === 'card' || $method === 'card_payment') ? (float) ($billKwacha ?? 0) : 0.0;
                $zamtel = $method === 'zamtel' ? (float) ($billKwacha ?? 0) : 0.0;
                $otherPayment = in_array($method, ['other', 'others']) ? (float) ($billKwacha ?? 0) : 0.0;

                $cashierName = $transaction->cashier?->name ?: $this->resolveCashierName($receipt);
                $cargoType = $consignment?->cargo_type ?? 'unknown';

                $results->push([
                    'date' => $transaction->created_at ?? now(),
                    'receipt_number' => $transaction->receipt_number,
                    'hawb_number' => optional($shipment)->code,
                    'consignee_name' => $consignment?->consignee ?? $consignment?->name,
                    'client_name' => $client?->name,
                    'rate' => $rate !== null ? (float) $rate : null,
                    'bill_usd' => $billUsd !== null ? (float) $billUsd : null,
                    'bill_kwacha' => $billKwacha !== null ? (float) $billKwacha : null,
                    'method_of_payment' => $methodLabel,
                    'method_slug' => $method,
                    'airtel' => $airtel,
                    'mtn' => $mtn,
                    'cash_payments' => $cashPayments,
                    'invoice_payment' => $invoicePayment,
                    'bank_transfer' => $bankTransfer,
                    'card_payment' => $cardPayment,
                    'zamtel' => $zamtel,
                    'other_payment' => $otherPayment,
                    'cashier_name' => $cashierName,
                    'cargo_type' => $cargoType,
                    'shipment' => $shipment,
                    'consignment' => $consignment,
                    'client' => $client,
                    'receipt' => $receipt,
                ]);
            }
        }

        return $results;
    }

    /**
     * Apply additional filters to an existing set of report rows.
     */
    public function applyFilters(Collection $rows, array $filters = []): Collection
    {
        $filtered = $rows;

        if (!empty($filters['cashier'])) {
            $cashier = Str::lower($filters['cashier']);
            $filtered = $filtered->filter(function (array $row) use ($cashier) {
                if (empty($row['cashier_name'])) {
                    return false;
                }

                return Str::contains(Str::lower($row['cashier_name']), $cashier);
            });
        }

        if (!empty($filters['method'])) {
            $method = Str::lower($filters['method']);
            $filtered = $filtered->filter(function (array $row) use ($method) {
                if (empty($row['method_slug'])) {
                    return false;
                }

                return Str::lower($row['method_slug']) === $method;
            });
        }

        if (!empty($filters['cargo_type'])) {
            $cargoType = Str::lower($filters['cargo_type']);
            $filtered = $filtered->filter(function (array $row) use ($cargoType) {
                if (empty($row['cargo_type'])) {
                    return false;
                }

                return Str::lower($row['cargo_type']) === $cargoType;
            });
        }

        if (!empty($filters['hawb_number'])) {
            $hawb = Str::lower($filters['hawb_number']);
            $filtered = $filtered->filter(function (array $row) use ($hawb) {
                if (!$row['hawb_number']) {
                    return false;
                }

                return Str::contains(Str::lower($row['hawb_number']), $hawb);
            });
        }

        if (!empty($filters['date'])) {
            try {
                $targetDate = Carbon::parse($filters['date'])->toDateString();
                $filtered = $filtered->filter(function (array $row) use ($targetDate) {
                    if (empty($row['date'])) {
                        return false;
                    }

                    return optional($row['date'])->toDateString() === $targetDate;
                });
            } catch (\Throwable $th) {
                // Ignore invalid dates silently.
            }
        }

        $order = $filters['bill_order'] ?? null;
        if (is_string($order) && Str::contains($order, '_')) {
            [$field, $direction] = array_pad(explode('_', $order, 2), 2, 'asc');
            $direction = Str::lower($direction) === 'desc' ? 'desc' : 'asc';

            if (in_array($field, ['bill_usd', 'bill_kwacha'], true)) {
                $filtered = $filtered->sortBy(function (array $row) use ($field, $direction) {
                    $value = $row[$field];

                    if ($value === null) {
                        return $direction === 'asc' ? INF : -INF;
                    }

                    return (float) $value;
                }, SORT_REGULAR, $direction === 'desc');
            }
        }

        return $filtered->values();
    }

    /**
     * Build filter option lists from the provided rows.
     */
    public function availableFilterOptions(Collection $rows): array
    {
        $methods = $rows
            ->filter(fn (array $row) => !empty($row['method_slug']) && !empty($row['method_of_payment']))
            ->map(fn (array $row) => [
                'value' => Str::lower($row['method_slug']),
                'label' => $row['method_of_payment'],
            ])
            ->unique('value')
            ->sortBy('label')
            ->values();

        // Fetch users with 'cashier' or 'cashiers' roles
        $cashierUsers = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['cashier', 'cashiers']);
        })->pluck('name')->sort()->values();

        $cargoTypes = $rows
            ->pluck('cargo_type')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $hawbNumbers = $rows
            ->pluck('hawb_number')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return [
            'methods' => $methods->all(),
            'cashiers' => $cashierUsers->all(),
            'cargo_types' => $cargoTypes->all(),
            'hawb_numbers' => $hawbNumbers->all(),
        ];
    }

    /**
     * Summarise the report rows.
     */
    public function summarize(Collection $rows, Carbon $start, Carbon $end): array
    {
        $totals = [
            'total_rows' => $rows->count(),
            'total_rate' => $rows->filter(fn ($row) => $row['rate'] !== null)->sum('rate'),
            'total_bill_usd' => $rows->filter(fn ($row) => $row['bill_usd'] !== null)->sum('bill_usd'),
            'total_bill_kwacha' => $rows->filter(fn ($row) => $row['bill_kwacha'] !== null)->sum('bill_kwacha'),
            'total_airtel' => $rows->sum('airtel'),
            'total_mtn' => $rows->sum('mtn'),
            'total_cash_payments' => $rows->sum('cash_payments'),
            'total_invoice_payment' => $rows->sum('invoice_payment'),
            'total_bank_transfer' => $rows->sum('bank_transfer'),
            'total_card_payment' => $rows->sum('card_payment'),
            'total_zamtel' => $rows->sum('zamtel'),
            'total_other_payment' => $rows->sum('other_payment'),
        ];

        // Calculate SEA and AIR totals
        $seaRows = $rows->filter(fn ($row) => $row['cargo_type'] === 'sea');
        $airRows = $rows->filter(fn ($row) => $row['cargo_type'] === 'air');

        $totals['total_sea_receipts'] = $seaRows->count();
        $totals['total_sea_bill_usd'] = $seaRows->filter(fn ($row) => $row['bill_usd'] !== null)->sum('bill_usd');
        $totals['total_sea_bill_kwacha'] = $seaRows->filter(fn ($row) => $row['bill_kwacha'] !== null)->sum('bill_kwacha');

        $totals['total_air_receipts'] = $airRows->count();
        $totals['total_air_bill_usd'] = $airRows->filter(fn ($row) => $row['bill_usd'] !== null)->sum('bill_usd');
        $totals['total_air_bill_kwacha'] = $airRows->filter(fn ($row) => $row['bill_kwacha'] !== null)->sum('bill_kwacha');

        $totals['average_rate'] = $totals['total_rows'] > 0
            ? round($rows->filter(fn ($row) => $row['rate'] !== null)->avg('rate'), 4)
            : 0;

        return array_merge($totals, [
            'period_start' => $start,
            'period_end' => $end,
        ]);
    }

    /**
     * Generate an Excel file for the supplied rows and return storage details.
     */
    public function generateExcel(Collection $rows, array $summary, string $disk = 'local', ?string $filename = null): array
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'A1' => 'Date',
            'B1' => 'HAWB No',
            'C1' => 'Receipt #',
            'D1' => 'Consignee Name',
            'E1' => 'Client Name',
            'F1' => 'Rate',
            'G1' => 'Bill (USD)',
            'H1' => 'Bill (ZMW)',
            'I1' => 'Method of Payment',
            'J1' => 'Cashier',
            'K1' => 'Airtel',
            'L1' => 'MTN',
            'M1' => 'Cash Payments',
            'N1' => 'Invoice Payment',
            'O1' => 'Bank Transfer',
            'P1' => 'Card Payment',
            'Q1' => 'Zamtel',
            'R1' => 'Other',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        $rowPointer = 2;
        foreach ($rows as $row) {
            $sheet->setCellValue("A{$rowPointer}", optional($row['date'])->format('Y-m-d'));
            $sheet->setCellValue("B{$rowPointer}", $row['hawb_number']);
            $sheet->setCellValue("C{$rowPointer}", $row['receipt_number']);
            $sheet->setCellValue("D{$rowPointer}", $row['consignee_name']);
            $sheet->setCellValue("E{$rowPointer}", $row['client_name']);
            $sheet->setCellValue("F{$rowPointer}", $row['rate']);
            $sheet->setCellValue("G{$rowPointer}", $row['bill_usd']);
            $sheet->setCellValue("H{$rowPointer}", $row['bill_kwacha']);
            $sheet->setCellValue("I{$rowPointer}", $row['method_of_payment']);
            $sheet->setCellValue("J{$rowPointer}", $row['cashier_name'] ?? 'N/A');
            $sheet->setCellValue("K{$rowPointer}", $row['airtel']);
            $sheet->setCellValue("L{$rowPointer}", $row['mtn']);
            $sheet->setCellValue("M{$rowPointer}", $row['cash_payments']);
            $sheet->setCellValue("N{$rowPointer}", $row['invoice_payment']);
            $sheet->setCellValue("O{$rowPointer}", $row['bank_transfer']);
            $sheet->setCellValue("P{$rowPointer}", $row['card_payment']);
            $sheet->setCellValue("Q{$rowPointer}", $row['zamtel']);
            $sheet->setCellValue("R{$rowPointer}", $row['other_payment']);
            $rowPointer++;
        }

        $summaryStartRow = $rowPointer + 1;
        $sheet->setCellValue("E{$summaryStartRow}", 'Totals');
        $sheet->setCellValue("F{$summaryStartRow}", $summary['total_rate']);
        $sheet->setCellValue("G{$summaryStartRow}", $summary['total_bill_usd']);
        $sheet->setCellValue("H{$summaryStartRow}", $summary['total_bill_kwacha']);
        $sheet->setCellValue("K{$summaryStartRow}", $summary['total_airtel']);
        $sheet->setCellValue("L{$summaryStartRow}", $summary['total_mtn']);
        $sheet->setCellValue("M{$summaryStartRow}", $summary['total_cash_payments']);
        $sheet->setCellValue("N{$summaryStartRow}", $summary['total_invoice_payment']);
        $sheet->setCellValue("O{$summaryStartRow}", $summary['total_bank_transfer']);
        $sheet->setCellValue("P{$summaryStartRow}", $summary['total_card_payment']);
        $sheet->setCellValue("Q{$summaryStartRow}", $summary['total_zamtel']);
        $sheet->setCellValue("R{$summaryStartRow}", $summary['total_other_payment']);

        $sheet->setCellValue("E" . ($summaryStartRow + 1), 'Average Rate');
        $sheet->setCellValue("F" . ($summaryStartRow + 1), $summary['average_rate']);

        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
        foreach (range('A', 'R') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        $filename = $filename ?: 'nwc-report-' . now()->format('Ymd_His') . '-' . Str::random(4) . '.xlsx';
        $relativePath = 'reports/' . $filename;

        Storage::disk($disk)->makeDirectory('reports');
        $writer = new Xlsx($spreadsheet);
        $writer->save(Storage::disk($disk)->path($relativePath));

        return [
            'disk' => $disk,
            'path' => $relativePath,
            'filename' => $filename,
        ];
    }

    protected function sumPaymentAmounts(Collection $payments, array $methodMatches): float
    {
        $normalizedTargets = collect($methodMatches)
            ->filter()
            ->map(fn ($method) => Str::lower((string) $method))
            ->values()
            ->all();

        if (empty($normalizedTargets)) {
            return 0.0;
        }

        $totalKwacha = $payments
            ->filter(function ($payment) use ($normalizedTargets) {
                $method = $this->normalizeMethod($payment->method_of_payment);

                if (!$method) {
                    return false;
                }

                return in_array(Str::lower($method), $normalizedTargets, true);
            })
            ->sum('amount');

        return round($totalKwacha, 2);
    }

    protected function normalizeMethod(?string $method): ?string
    {
        if (!$method) {
            return null;
        }

        $slug = Str::of($method)->lower()->snake();

        if ($slug->contains('airtel')) {
            return 'airtel';
        }
        if ($slug->contains('mtn')) {
            return 'mtn';
        }
        if ($slug->contains('cash')) {
            return 'cash';
        }
        if ($slug->contains('invoice')) {
            return 'invoice';
        }
        if ($slug->contains('bank_transfer') || $slug->contains('bank')) {
            return 'bank_transfer';
        }
        if ($slug->contains('card')) {
            return 'card';
        }
        if ($slug->contains('zamtel')) {
            return 'zamtel';
        }
        if ($slug->contains('other')) {
            return 'other';
        }

        return (string) $slug;
    }

    protected function formatMethodLabel(?string $method): string
    {
        if (!$method) {
            return 'N/A';
        }

        return Str::of($method)
            ->replace('_', ' ')
            ->replace('-', ' ')
            ->title();
    }

    protected function resolveCashierName(?\App\Models\NwcReceipt $receipt): ?string
    {
        if (!$receipt) {
            return null;
        }

        if ($receipt->cashier_name) {
            return $receipt->cashier_name;
        }

        if ($receipt->relationLoaded('user')) {
            $user = $receipt->getRelation('user');
        } else {
            $user = $receipt->user()->first();
        }

        if ($user && $user->name) {
            return $user->name;
        }

        $auditLogs = $receipt->relationLoaded('auditLogs')
            ? $receipt->auditLogs
            : $receipt->auditLogs()->with('user')->get();

        if ($auditLogs->isEmpty()) {
            return null;
        }

        $createdLog = $auditLogs->firstWhere('event', 'created');
        if (!$createdLog) {
            $createdLog = $auditLogs->sortBy('created_at')->first();
        }

        return $createdLog?->user?->name;
    }
}
