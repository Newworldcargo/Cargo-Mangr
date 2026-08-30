<?php

namespace App\Services;

use App\Models\AuditLog;
use Modules\Cargo\Services\BranchAccessService;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    public function __construct(private readonly BranchAccessService $branchAccess)
    {
    }
    /**
     * Create an audit log entry.
     *
     * @param string $event
     * @param mixed $auditable  Model or ID
     * @param string|null $auditableType
     * @param array $oldValues
     * @param array $newValues
     * @param string|null $description
     * @return AuditLog
     */
    public function createLog(
        string $event,
        $auditable,
        ?string $auditableType = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $description = null
    ): AuditLog {
        $user = Auth::user();
        $branchId = $this->branchIdForAuditable($auditable) ?: $this->branchAccess->branchIdFor($user);

        return AuditLog::create([
            'user_id'        => $user?->id,
            'event'          => $event,
            'auditable_type' => $auditableType ?? (is_object($auditable) ? get_class($auditable) : null),
            'auditable_id'   => is_object($auditable) ? $auditable->id : $auditable,
            'branch_id'      => $branchId,
            'description'    => $description,
            'old_values'     => $oldValues,
            'new_values'     => $newValues,
            'ip_address'     => Request::ip(),
            'user_agent'     => Request::header('User-Agent'),
        ]);
    }

    private function branchIdForAuditable($auditable): ?int
    {
        if (is_object($auditable) && !empty($auditable->branch_id)) {
            return (int) $auditable->branch_id;
        }

        if (is_object($auditable) && isset($auditable->shipment) && !empty($auditable->shipment?->branch_id)) {
            return (int) $auditable->shipment->branch_id;
        }

        return null;
    }

    /**
     * Get logs for a specific model.
     *
     * @param mixed $auditable
     * @param string|null $auditableType
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLogsFor($auditable, ?string $auditableType = null)
    {
        return AuditLog::with('user')
            ->where('auditable_type', $auditableType ?? get_class($auditable))
            ->where('auditable_id', is_object($auditable) ? $auditable->id : $auditable)
            ->latest()
            ->get();
    }

    /**
     * Get all logs for a user.
     *
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLogsByUser(int $userId)
    {
        return AuditLog::where('user_id', $userId)
            ->latest()
            ->get();
    }

    /**
     * Get all logs (paginated).
     *
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getAllLogs(int $perPage = 20)
    {
        $query = AuditLog::with('user')->latest();

        // Branch users (role 3) only see logs that belong to their own branch:
        //  - shipment audit entries via shipments.branch_id
        //  - receipt audit entries via shipments.branch_id
        //  - logs performed by users of the same branch
        $user = auth()->user();
        if ($user && !$this->branchAccess->isTopAdmin($user)) {
            $branchId = $this->branchAccess->branchIdFor($user);
            if ($branchId) {
                $query->where(function ($q) use ($branchId) {
                    // New audit events carry their branch at write time. Keep the
                    // legacy relationship lookups below while historical rows are
                    // backfilled in a separate, reviewable operation.
                    $q->orWhere('branch_id', $branchId);
                    // Shipment audit entries (Modules\Cargo\Entities\Shipment)
                    $q->orWhere(function ($sub) use ($branchId) {
                        $sub->where('auditable_type', 'Modules\\Cargo\\Entities\\Shipment')
                            ->whereIn('auditable_id', function ($inner) use ($branchId) {
                                $inner->select('id')->from('shipments')->where('branch_id', $branchId);
                            });
                    });
                    // Receipt audit entries linked to shipments
                    $q->orWhere(function ($sub) use ($branchId) {
                        $sub->whereIn('auditable_type', [
                                'App\\Models\\NwcReceipt',
                                'App\\Models\\ShipmentPaymentReceipt',
                            ])
                            ->whereIn('auditable_id', function ($inner) use ($branchId) {
                                $inner->select('id')->from('nwc_receipts')
                                    ->whereIn('shipment_id', function ($s) use ($branchId) {
                                        $s->select('id')->from('shipments')->where('branch_id', $branchId);
                                    });
                            });
                    });
                    // Receipts stored in shipment_payment_receipts table if separate
                    $q->orWhere(function ($sub) use ($branchId) {
                        $sub->where('auditable_type', 'App\\Models\\ShipmentPaymentReceipt')
                            ->whereIn('auditable_id', function ($inner) use ($branchId) {
                                $inner->select('id')->from('shipment_payment_receipts')
                                    ->whereIn('shipment_id', function ($s) use ($branchId) {
                                        $s->select('id')->from('shipments')->where('branch_id', $branchId);
                                    });
                            });
                    });
                    // Logs performed by users belonging to this branch
                    $q->orWhere(function ($sub) use ($branchId) {
                        $sub->whereNotNull('user_id')
                            ->whereIn('user_id', function ($inner) use ($branchId) {
                                $inner->select('user_id')->from('branches')->where('id', $branchId);
                            });
                    });
                });
            }
        }

        return $query->paginate($perPage);
    }
}
