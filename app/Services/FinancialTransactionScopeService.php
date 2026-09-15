<?php

namespace App\Services;

use App\Models\Transxn;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Modules\Cargo\Services\BranchAccessService;

/** Applies the visibility policy for the finance screen at /transactions. */
class FinancialTransactionScopeService
{
    public function __construct(private readonly BranchAccessService $branchAccess)
    {
    }

    public function apply(Builder $query, ?User $user): Builder
    {
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ((int) $user->role === User::ADMIN) {
            return $query;
        }

        if ((int) $user->role === 3) {
            return $this->forBranches($query, $this->branchAccess->branchIdsFor($user));
        }

        if (in_array((int) $user->role, [User::STAFF, 2], true)) {
            $branchIds = $this->branchAccess->branchIdsFor($user);
            if ($user->can('manage-transactions') && $branchIds->isNotEmpty()) {
                return $this->forBranches($query, $branchIds);
            }

            // Legacy rows lack reliable cashier attribution, so ordinary staff
            // never receive them. New rows are attributed at payment time.
            return $query->where('cashier_user_id', $user->id);
        }

        return $query->whereRaw('1 = 0');
    }

    private function forBranches(Builder $query, $branchIds): Builder
    {
        if ($branchIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $branchQuery) use ($branchIds) {
            $branchQuery->whereIn('collection_branch_id', $branchIds)
                ->orWhere(function (Builder $legacyQuery) use ($branchIds) {
                    $legacyQuery->whereNull('collection_branch_id')
                        ->whereHas('shipment', function (Builder $shipmentQuery) use ($branchIds) {
                            $shipmentQuery->whereIn('branch_id', $branchIds);
                        });
                });
        });
    }
}
