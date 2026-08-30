<?php

namespace Modules\Cargo\Services;

use App\Models\User;
use Modules\Cargo\Entities\Client;
use Modules\Cargo\Entities\Driver;
use Modules\Cargo\Entities\Shipment;

/**
 * Authorizes writes to a shipment without changing its shared read visibility.
 *
 * A branch account is the manager for its assigned branch. Staff need the
 * relevant Cargo permission (or manage-shipments) as well as that assignment.
 * This makes URL/API requests obey the same boundary as the intended UI.
 */
class ShipmentOperationAccessService
{
    public function __construct(private readonly BranchAccessService $branches)
    {
    }

    public function canCreateAtBranch(?User $user, ?int $branchId): bool
    {
        if (!$user || !$branchId) {
            return false;
        }

        if ($this->branches->isTopAdmin($user)) {
            return true;
        }

        // Client-created shipments are customer-owned at creation time. Their
        // later internal processing remains subject to canOperate().
        if ((int) $user->role === 4) {
            return true;
        }

        if ((int) $user->role === 3) {
            return $this->branches->branchIdFor($user) === $branchId;
        }

        return $this->branches->branchIdFor($user) === $branchId
            && $this->hasPermission($user, 'create-shipments');
    }

    public function canOperate(?User $user, Shipment $shipment, string $permission): bool
    {
        if (!$user) {
            return false;
        }

        if ($this->branches->isTopAdmin($user)) {
            return true;
        }

        // A client may only amend its own still-saved shipment. It never gains
        // internal branch operations through shared shipment visibility.
        if ((int) $user->role === 4) {
            $clientId = Client::where('user_id', $user->id)->value('id');

            return $permission === 'edit-shipments'
                && (int) $shipment->client_id === (int) $clientId
                && (int) $shipment->status_id === Shipment::SAVED_STATUS;
        }

        // Drivers may update only a shipment assigned to them, and only for
        // the delivery milestones exposed by the barcode workflow.
        if ((int) $user->role === 5) {
            $driverId = Driver::where('user_id', $user->id)->value('id');

            return (int) $shipment->captain_id === (int) $driverId
                && in_array($permission, ['received-shipments', 'deliverd-shipments'], true);
        }

        if ($this->branches->branchIdFor($user) !== (int) $shipment->branch_id) {
            return false;
        }

        // A branch account is the manager of the branch it owns. All other
        // internal users require an explicit capability, with manage-shipments
        // acting as the established manager-level umbrella permission.
        if ((int) $user->role === 3) {
            return true;
        }

        return $this->hasPermission($user, $permission);
    }

    public function canViewAuditTrail(?User $user, Shipment $shipment): bool
    {
        if (!$user) {
            return false;
        }

        return $this->branches->isTopAdmin($user)
            || ($this->branches->branchIdFor($user) === (int) $shipment->branch_id
                && $user->can('view-audit-logs'));
    }

    public function statusPermission(int $status): ?string
    {
        foreach (Shipment::status_info() as $statusInfo) {
            if ((int) $statusInfo['status'] === $status) {
                return $statusInfo['permissions'] ?? null;
            }
        }

        return null;
    }

    private function hasPermission(User $user, string $permission): bool
    {
        return $user->can('manage-shipments') || $user->can($permission);
    }
}
