<?php

namespace Modules\Cargo\Services;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Cargo\Entities\Driver;
use Modules\Cargo\Entities\Shipment;
use Modules\Cargo\Entities\ShipmentDispatchEntry;

class ShipmentDispatchService
{
    public function canView(?User $user): bool
    {
        return $user && (app(BranchAccessService::class)->isTopAdmin($user)
            || $user->can('view-dispatch') || $user->can('manage-dispatch'));
    }

    public function canManage(?User $user, Shipment $shipment): bool
    {
        return $user && (app(BranchAccessService::class)->isTopAdmin($user)
            || ($user->can('manage-dispatch') && app(BranchAccessService::class)->canAccessBranch($user, (int) $shipment->branch_id)))
            && app(ShipmentOperationAccessService::class)->canOperate($user, $shipment, 'manage-dispatch');
    }

    public function eligible(Shipment $shipment): bool
    {
        return in_array((int) $shipment->status_id, [Shipment::APPROVED_STATUS, Shipment::CAPTAIN_ASSIGNED_STATUS,
            Shipment::RECIVED_STATUS, Shipment::PENDING_STATUS, Shipment::IN_STOCK_STATUS], true);
    }

    public function send(User $user, int $shipmentId, ?int $driverId): ShipmentDispatchEntry
    {
        return DB::transaction(function () use ($user, $shipmentId, $driverId) {
            // Lock the shipment as well as using a unique queue key to make repeat clicks harmless.
            $shipment = Shipment::whereKey($shipmentId)->lockForUpdate()->firstOrFail();
            abort_unless($this->canManage($user, $shipment), 403);
            if (!$this->eligible($shipment)) {
                throw ValidationException::withMessages(['shipment' => 'This shipment is not available for dispatch.']);
            }
            $entry = ShipmentDispatchEntry::where('shipment_id', $shipment->id)->first();
            if ($driverId && !Driver::whereKey($driverId)->where('branch_id', $shipment->branch_id)->where('is_archived', 0)->exists()) {
                throw ValidationException::withMessages(['driver_id' => 'Choose an active driver from the shipment branch.']);
            }
            $oldDriver = $shipment->captain_id;
            // An empty choice retains any existing assignment; missions own their driver changes.
            if ($driverId && (int) $oldDriver !== $driverId) {
                if ($shipment->mission_id) {
                    throw ValidationException::withMessages(['driver_id' => 'Change this driver through the assigned mission.']);
                }
                $shipment->captain_id = $driverId;
                $shipment->save();
            }
            if (!$entry) {
                $entry = ShipmentDispatchEntry::create(['shipment_id' => $shipment->id, 'requested_by' => $user->id]);
            }
            if ($entry->wasRecentlyCreated || (int) $oldDriver !== (int) $shipment->captain_id) {
                app(AuditLogService::class)->createLog('shipment_dispatch', $shipment, null,
                    ['captain_id' => $oldDriver], ['captain_id' => $shipment->captain_id, 'dispatch_entry_id' => $entry->id],
                    $entry->wasRecentlyCreated ? 'Shipment sent to dispatch queue.' : 'Dispatch driver updated.');
            }
            return $entry;
        });
    }
}
