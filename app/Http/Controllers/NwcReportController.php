<?php

namespace App\Http\Controllers;

use App\Mail\NwcReportMail;
use App\Services\Reports\NwcReportService;
use App\Services\OperationalScopeFilterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Twilio\Rest\Client as TwilioClient;

class NwcReportController extends Controller
{
    public function __construct(
        private readonly NwcReportService $reportService,
        private readonly OperationalScopeFilterService $scopeFilters,
    )
    {
    }

    public function index(Request $request)
    {
        [$scopeOptions, $selectedScope] = $this->globalScope($request);
        [$start, $end] = $this->reportService->resolveDateRange(
            $request->input('start_date'),
            $request->input('end_date')
        );

        $filters = [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'cashier' => $request->filled('cashier') ? trim((string) $request->input('cashier')) : null,
            'method' => $request->filled('method') ? Str::lower(trim((string) $request->input('method'))) : null,
            'cargo_type' => $request->filled('cargo_type') ? Str::lower(trim((string) $request->input('cargo_type'))) : null,
            'hawb_number' => $request->filled('hawb_number') ? trim((string) $request->input('hawb_number')) : null,
            'date' => $request->filled('date') ? trim((string) $request->input('date')) : null,
            'bill_order' => $request->filled('bill_order') ? trim((string) $request->input('bill_order')) : null,
            'branch_id' => $selectedScope['selectedBranchId'],
            'user_id' => $selectedScope['selectedUserId'],
        ];

        $pageRowLimit = 500;
        $baseRows = $this->reportService->getReportData([
            'start_date' => $filters['start_date'],
            'end_date' => $filters['end_date'],
            'branch_id' => $filters['branch_id'],
            'user_id' => $filters['user_id'],
        ], $request->user(), $pageRowLimit + 1);
        $reportTruncated = $baseRows->count() > $pageRowLimit;
        if ($reportTruncated) {
            $baseRows = $baseRows->take($pageRowLimit)->values();
        }
        $availableFilters = $this->reportService->availableFilterOptions($baseRows);

        if (!empty($filters['cashier']) && !in_array($filters['cashier'], $availableFilters['cashiers'], true)) {
            $availableFilters['cashiers'][] = $filters['cashier'];
            sort($availableFilters['cashiers']);
        }

        if (!empty($filters['hawb_number']) && !in_array($filters['hawb_number'], $availableFilters['hawb_numbers'], true)) {
            $availableFilters['hawb_numbers'][] = $filters['hawb_number'];
            sort($availableFilters['hawb_numbers']);
        }

        if (!empty($filters['method'])) {
            $hasMethod = collect($availableFilters['methods'])
                ->contains(fn (array $method) => $method['value'] === $filters['method']);

            if (!$hasMethod) {
                $availableFilters['methods'][] = [
                    'value' => $filters['method'],
                    'label' => Str::of($filters['method'])->replace('_', ' ')->replace('-', ' ')->title(),
                ];

                $availableFilters['methods'] = collect($availableFilters['methods'])
                    ->sortBy('label')
                    ->values()
                    ->all();
            }
        }

        $rows = $this->reportService->applyFilters($baseRows, $filters);
        $summary = $this->reportService->summarize($rows, $start, $end);

        return view('adminLte.pages.reports.nwc.index', [
            'reportRows' => $rows,
            'summary' => $summary,
            'filters' => $filters,
            'availableFilters' => $availableFilters,
            'scopeOptions' => $scopeOptions,
            'selectedScope' => $selectedScope,
            'reportTruncated' => $reportTruncated,
        ]);
    }

    public function export(Request $request)
    {
        [, $selectedScope] = $this->globalScope($request);
        [$start, $end] = $this->reportService->resolveDateRange(
            $request->input('start_date'),
            $request->input('end_date')
        );

        $filters = [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'branch_id' => $selectedScope['selectedBranchId'],
            'user_id' => $selectedScope['selectedUserId'],
        ];

        $rows = $this->reportService->getReportData($filters, $request->user());
        $rows = $this->reportService->applyFilters($rows, [
            'cashier' => $request->filled('cashier') ? trim((string) $request->input('cashier')) : null,
            'method' => $request->filled('method') ? Str::lower(trim((string) $request->input('method'))) : null,
            'cargo_type' => $request->filled('cargo_type') ? Str::lower(trim((string) $request->input('cargo_type'))) : null,
            'hawb_number' => $request->filled('hawb_number') ? trim((string) $request->input('hawb_number')) : null,
            'date' => $request->filled('date') ? trim((string) $request->input('date')) : null,
            'bill_order' => $request->filled('bill_order') ? trim((string) $request->input('bill_order')) : null,
        ] + $filters);

        if ($rows->isEmpty()) {
            return redirect()
                ->back()
                ->with('error', 'No transactions found for the selected period.');
        }

        $summary = $this->reportService->summarize($rows, $start, $end);
        $file = $this->reportService->generateExcel($rows, $summary);
        $downloadPath = Storage::disk($file['disk'])->path($file['path']);

        return response()->download($downloadPath, $file['filename'])->deleteFileAfterSend(true);
    }

    public function shareEmail(Request $request)
    {
        [, $selectedScope] = $this->globalScope($request);
        $request->validate([
            'email' => 'required|email',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        [$start, $end] = $this->reportService->resolveDateRange(
            $request->input('start_date'),
            $request->input('end_date')
        );

        $filters = [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'branch_id' => $selectedScope['selectedBranchId'],
            'user_id' => $selectedScope['selectedUserId'],
        ];

        $rows = $this->reportService->getReportData($filters, $request->user());
        $rows = $this->reportService->applyFilters($rows, [
            'cashier' => $request->filled('cashier') ? trim((string) $request->input('cashier')) : null,
            'method' => $request->filled('method') ? Str::lower(trim((string) $request->input('method'))) : null,
            'cargo_type' => $request->filled('cargo_type') ? Str::lower(trim((string) $request->input('cargo_type'))) : null,
            'hawb_number' => $request->filled('hawb_number') ? trim((string) $request->input('hawb_number')) : null,
            'date' => $request->filled('date') ? trim((string) $request->input('date')) : null,
            'bill_order' => $request->filled('bill_order') ? trim((string) $request->input('bill_order')) : null,
        ] + $filters);

        if ($rows->isEmpty()) {
            return redirect()
                ->back()
                ->with('error', 'No transactions found for the selected period. Nothing to share.');
        }

        $summary = $this->reportService->summarize($rows, $start, $end);
        $file = $this->reportService->generateExcel($rows, $summary);

        try {
            Mail::to($request->input('email'))
                ->send(new NwcReportMail($summary, $file));
        } catch (\Throwable $th) {
            Log::error('Failed to email NWC report', ['exception' => $th]);
            return redirect()
                ->back()
                ->with('error', 'Failed to send report via email: ' . $th->getMessage());
        } finally {
            Storage::disk($file['disk'])->delete($file['path']);
        }

        return redirect()
            ->back()
            ->with('status', 'Report emailed successfully.');
    }

    public function shareWhatsapp(Request $request)
    {
        [, $selectedScope] = $this->globalScope($request);
        $request->validate([
            'phone' => 'required|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        [$start, $end] = $this->reportService->resolveDateRange(
            $request->input('start_date'),
            $request->input('end_date')
        );

        $filters = [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'branch_id' => $selectedScope['selectedBranchId'],
            'user_id' => $selectedScope['selectedUserId'],
        ];

        $rows = $this->reportService->getReportData($filters, $request->user());
        $rows = $this->reportService->applyFilters($rows, [
            'cashier' => $request->filled('cashier') ? trim((string) $request->input('cashier')) : null,
            'method' => $request->filled('method') ? Str::lower(trim((string) $request->input('method'))) : null,
            'cargo_type' => $request->filled('cargo_type') ? Str::lower(trim((string) $request->input('cargo_type'))) : null,
            'hawb_number' => $request->filled('hawb_number') ? trim((string) $request->input('hawb_number')) : null,
            'date' => $request->filled('date') ? trim((string) $request->input('date')) : null,
            'bill_order' => $request->filled('bill_order') ? trim((string) $request->input('bill_order')) : null,
        ] + $filters);

        if ($rows->isEmpty()) {
            return redirect()
                ->back()
                ->with('error', 'No transactions found for the selected period. Nothing to share.');
        }

        $summary = $this->reportService->summarize($rows, $start, $end);

        $sid = config('services.twilio.sid', env('TWILIO_SID'));
        $token = config('services.twilio.token', env('TWILIO_AUTH_TOKEN'));
        $from = config('services.twilio.whatsapp_from', env('TWILIO_WHATSAPP_FROM'));

        if (!$sid || !$token || !$from) {
            return redirect()
                ->back()
                ->with('error', 'Twilio credentials are not configured. Unable to share via WhatsApp.');
        }

        $phoneString = Str::of($request->input('phone'))
            ->replace(' ', '')
            ->replace('-', '')
            ->replace('(', '')
            ->replace(')', '');

        if ($phoneString->startsWith('00')) {
            $phoneString = Str::of('+' . $phoneString->substr(2));
        }

        $normalizedPhone = $phoneString->value();

        if (!Str::startsWith($normalizedPhone, '+')) {
            $normalizedPhone = '+' . ltrim($normalizedPhone, '+');
        }

        $file = $this->reportService->generateExcel($rows, $summary, 'public');
        $publicUrl = Storage::disk('public')->url($file['path']);

        try {
            $client = new TwilioClient($sid, $token);
            $fromAddress = Str::of($from)->startsWith('whatsapp:')
                ? $from
                : 'whatsapp:' . ltrim($from, '+');

            $client->messages->create('whatsapp:' . $normalizedPhone, [
                'from' => $fromAddress,
                'body' => sprintf(
                    'NWC Report for %s to %s',
                    $start->toDateString(),
                    $end->toDateString()
                ),
                'mediaUrl' => [$publicUrl],
            ]);
            register_shutdown_function(static function () use ($file) {
                Storage::disk('public')->delete($file['path']);
            });
        } catch (\Throwable $th) {
            Log::error('Failed to share NWC report via WhatsApp', ['exception' => $th]);
            Storage::disk('public')->delete($file['path']);

            return redirect()
                ->back()
                ->with('error', 'Failed to send report via WhatsApp: ' . $th->getMessage());
        }

        return redirect()
            ->back()
            ->with('status', 'Report shared via WhatsApp successfully.');
    }

    private function globalScope(Request $request): array
    {
        $viewer = $request->user();
        $options = $this->scopeFilters->options($viewer, $viewer->can('manage-transactions') || (int) $viewer->role === 3);
        $selected = $this->scopeFilters->selected(
            $viewer,
            $options,
            (int) $request->input('branch_id') ?: null,
            $request->input('scope') === 'self' ? $viewer->id : ((int) $request->input('user_id') ?: null),
        );

        return [$options, $selected];
    }
}
