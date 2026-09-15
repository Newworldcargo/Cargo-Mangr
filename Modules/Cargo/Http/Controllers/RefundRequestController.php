<?php

namespace Modules\Cargo\Http\Controllers;

use App\Models\GeneralSettings;
use App\Models\RefundRequest;
use App\Services\AuditLogService;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Cargo\Entities\Client;
use Modules\Cargo\Entities\Shipment;

class RefundRequestController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAccess();

        $status = $request->filled('status') ? trim((string) $request->status) : null;

        $query = RefundRequest::with([
            'shipment.client',
            'requester',
            'reviewer',
        ])->orderByDesc('created_at');

        if ($status) {
            $query->where('status', $status);
        }

        $refundRequests = $query->paginate(20);

        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return view('cargo::' . $adminTheme . '.pages.refund-requests.index', compact('refundRequests', 'status'));
    }

    public function show($id)
    {
        $this->authorizeAccess();

        $refundRequest = RefundRequest::with([
            'shipment.client',
            'requester',
            'reviewer',
        ])->findOrFail($id);

        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return view('cargo::' . $adminTheme . '.pages.refund-requests.show', compact('refundRequest'));
    }

    public function store(Request $request, AuditLogService $auditLogService)
    {
        $request->validate([
            'shipment_id' => 'required|exists:shipments,id',
            'refund_type' => 'required|in:full,partial',
            'amount' => 'nullable|numeric|min:0.01',
            'reason' => 'required|string|max:5000',
        ]);

        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Authentication required.'], 401);
        }

        $settings = app(GeneralSettings::class);
        if (!(bool) ($settings->enable_refund_payments ?? false)) {
            return response()->json(['success' => false, 'message' => 'Refunds are disabled.'], 403);
        }

        $allowClientRefunds = (bool) ($settings->allow_client_refunds ?? false);
        if ($user->role == 4 && !$allowClientRefunds) {
            return response()->json(['success' => false, 'message' => 'Client refunds are disabled.'], 403);
        }

        if ($user->role != 4) {
            $canRequest = app(\Modules\Cargo\Services\ShipmentOperationAccessService::class)->canCollectPayments($user);
            if (!$canRequest) {
                return response()->json(['success' => false, 'message' => 'You are not allowed to request refunds.'], 403);
            }
        }

        $shipment = Shipment::with('receipt')->findOrFail($request->shipment_id);
        if ($user->role == 4) {
            $client = Client::where('user_id', $user->id)->first();
            if (!$client || (int) $shipment->client_id !== (int) $client->id) {
                return response()->json(['success' => false, 'message' => 'You are not allowed to request refunds for this shipment.'], 403);
            }
        }
        if (!$shipment->paid) {
            return response()->json(['success' => false, 'message' => 'This shipment is not marked as paid.'], 400);
        }

        $transaction = $shipment->receipt;
        if (!$transaction) {
            return response()->json(['success' => false, 'message' => 'Transaction record not found.'], 404);
        }

        $pendingRequest = RefundRequest::where('shipment_id', $shipment->id)
            ->where('status', RefundRequest::STATUS_PENDING)
            ->first();
        if ($pendingRequest) {
            return response()->json(['success' => false, 'message' => 'A refund request is already pending.'], 409);
        }

        $total = (float) $transaction->total;
        $alreadyRefunded = (float) ($transaction->refunded_amount ?? 0);
        $remaining = max($total - $alreadyRefunded, 0.0);

        if ($remaining <= 0) {
            return response()->json(['success' => false, 'message' => 'This transaction has already been refunded.'], 400);
        }

        $refundType = $request->refund_type;
        $amount = $refundType === RefundRequest::TYPE_FULL ? $remaining : (float) $request->amount;

        if ($refundType === RefundRequest::TYPE_PARTIAL && ($amount <= 0 || $amount - $remaining > 0.01)) {
            return response()->json(['success' => false, 'message' => 'Refund amount exceeds the remaining balance.'], 422);
        }

        if (abs($amount - $remaining) < 0.01) {
            $refundType = RefundRequest::TYPE_FULL;
        }

        DB::transaction(function () use ($shipment, $transaction, $user, $refundType, $amount, $request, $auditLogService) {
            RefundRequest::create([
                'shipment_id' => $shipment->id,
                'transxn_id' => $transaction->id,
                'requested_by' => $user->id,
                'status' => RefundRequest::STATUS_PENDING,
                'refund_type' => $refundType,
                'amount' => $amount,
                'reason' => $request->reason,
            ]);

            $transaction->update(['status' => 'refund_requested']);

            $auditLogService->createLog(
                'refund_requested',
                $shipment,
                null,
                [],
                [
                    'refund_type' => $refundType,
                    'refund_amount' => $amount,
                    'refund_reason' => $request->reason,
                ],
                'Refund requested by ' . ($user->name ?? 'System')
            );
        });

        return response()->json(['success' => true, 'message' => 'Refund request submitted.']);
    }

    public function approve($id, RefundService $refundService, AuditLogService $auditLogService)
    {
        $this->authorizeAccess();

        $user = auth()->user();
        $refundRequest = RefundRequest::with(['shipment', 'transaction'])->findOrFail($id);

        if ($refundRequest->status !== RefundRequest::STATUS_PENDING) {
            return redirect()->back()->with(['message_alert' => 'This refund request has already been reviewed.']);
        }

        try {
            DB::transaction(function () use ($refundRequest, $refundService, $auditLogService, $user) {
                $shipment = $refundRequest->shipment;
                if (!$shipment || !$shipment->paid) {
                    throw new \RuntimeException('Shipment is not eligible for refund.');
                }

                $auditLogService->createLog(
                    'refund_approved',
                    $shipment,
                    null,
                    [],
                    [
                        'refund_type' => $refundRequest->refund_type,
                        'refund_amount' => (float) $refundRequest->amount,
                    ],
                    'Refund approved by ' . ($user->name ?? 'System')
                );

                $result = $refundService->applyRefund(
                    $shipment,
                    (float) $refundRequest->amount,
                    $refundRequest->refund_type,
                    $refundRequest->reason
                );

                $refundRequest->update([
                    'status' => RefundRequest::STATUS_APPROVED,
                    'reviewed_by' => $user?->id,
                    'reviewed_at' => now(),
                    'refunded_at' => now(),
                ]);

                $auditLogService->createLog(
                    'refund_processed',
                    $shipment,
                    null,
                    [],
                    [
                        'refund_type' => $refundRequest->refund_type,
                        'refund_amount' => $result['refund_amount'],
                        'refunded_total' => $result['refunded_total'],
                    ],
                    'Refund processed by ' . ($user->name ?? 'System')
                );
            });

            return redirect()->back()->with(['message_alert' => 'Refund request approved and processed.']);
        } catch (\Exception $e) {
            return redirect()->back()->with(['message_alert' => 'Failed to process refund request: ' . $e->getMessage()]);
        }
    }

    public function decline(Request $request, $id, AuditLogService $auditLogService)
    {
        $this->authorizeAccess();

        $request->validate([
            'review_notes' => 'required|string|max:5000',
        ]);

        $user = auth()->user();
        $refundRequest = RefundRequest::with(['shipment', 'transaction'])->findOrFail($id);

        if ($refundRequest->status !== RefundRequest::STATUS_PENDING) {
            return redirect()->back()->with(['message_alert' => 'This refund request has already been reviewed.']);
        }

        DB::transaction(function () use ($refundRequest, $request, $auditLogService, $user) {
            $refundRequest->update([
                'status' => RefundRequest::STATUS_DECLINED,
                'reviewed_by' => $user?->id,
                'reviewed_at' => now(),
                'review_notes' => $request->review_notes,
            ]);

            $transaction = $refundRequest->transaction;
            if ($transaction && $transaction->status === 'refund_requested') {
                $transaction->update([
                    'status' => ((float) ($transaction->refunded_amount ?? 0)) > 0 ? 'partially_refunded' : 'completed',
                ]);
            }

            $auditLogService->createLog(
                'refund_declined',
                $refundRequest->shipment,
                null,
                [],
                [
                    'refund_type' => $refundRequest->refund_type,
                    'refund_amount' => (float) $refundRequest->amount,
                    'refund_reason' => $refundRequest->reason,
                    'decline_reason' => $request->review_notes,
                ],
                'Refund request declined by ' . ($user->name ?? 'System')
            );
        });

        return redirect()->back()->with(['message_alert' => 'Refund request declined.']);
    }

    private function authorizeAccess(): void
    {
        $user = auth()->user();
        $canApprove = $user && $user->can('approve-refund-requests');

        if (!$canApprove) {
            abort(403, 'You are not allowed to manage refund requests.');
        }
    }
}
