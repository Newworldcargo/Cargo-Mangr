<?php

namespace App\Services;

use App\Models\Transxn;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Modules\Cargo\Entities\Branch;
use Modules\Cargo\Entities\Staff;

/** Applies the visibility policy for the finance screen at /transactions. */
class FinancialTransactionScopeService
{
    public function apply(Builder $query, ?User $user): Builder
    {
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ((int) $user->role === User::ADMIN) {
            return $query;
        }

        if ((int) $user->role === 3) {
            return $this->forBranch($query, Branch::where('user_id', $user->id)->value('id'));
        }

        if (in_array((int) $user->role, [User::STAFF, 2], true)) {
            $branchId = Staff::where('user_id', $user->id)->value('branch_id');
            if ($user->can('manage-transactions') && $branchId) {
                return $this->forBranch($query, $branchId);
            }

            // Legacy rows lack reliable cashier attribution, so ordinary staff
            // never receive them. New rows are attributed at payment time.
            return $query->where('cashier_user_id', $user->id);
        }

        return $query->whereRaw('1 = 0');
    }

    private function forBranch(Builder $query, ?int $branchId): Builder
    {
        if (!$branchId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('shipment', function (Builder $shipmentQuery) use ($branchId) {
            $shipmentQuery->where('branch_id', $branchId);
        });
    }
}
