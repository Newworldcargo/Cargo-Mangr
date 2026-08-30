<?php

namespace Modules\Cargo\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Cargo\Entities\Branch;
use Modules\Cargo\Entities\Client;
use Modules\Cargo\Entities\Driver;
use Modules\Cargo\Entities\Staff;

/**
 * Applies the effective transaction visibility policy in one place.
 *
 * A staff user without manage-transactions is a cashier: they may only see
 * transactions they created. The management permission broadens visibility to
 * the staff member's assigned branch; it never grants global visibility.
 */
class TransactionScopeService
{
    public function apply(Builder $query, ?User $user, ?Request $request = null): Builder
    {
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ((int) $user->role === User::ADMIN) {
            return $this->applyFilters($query, $request);
        }

        switch ((int) $user->role) {
            case 3:
                $branchId = Branch::where('user_id', $user->id)->value('id');
                return $this->applyBranchScope($query, $branchId, $request);

            case 4:
                return $this->applyFilters($query->where('client_id', Client::where('user_id', $user->id)->value('id')), $request);

            case 5:
                return $this->applyFilters($query->where('captain_id', Driver::where('user_id', $user->id)->value('id')), $request);

            case User::STAFF:
            case 2:
                $staff = Staff::where('user_id', $user->id)->first();

                if ($user->can('manage-transactions') && $staff?->branch_id) {
                    return $this->applyBranchScope($query, (int) $staff->branch_id, $request);
                }

                return $this->applyFilters($query->where('created_by', $user->id), $request);

            default:
                return $query->whereRaw('1 = 0');
        }
    }

    private function applyBranchScope(Builder $query, ?int $branchId, ?Request $request): Builder
    {
        if (!$branchId) {
            return $query->whereRaw('1 = 0');
        }

        return $this->applyFilters($query->where(function (Builder $branchQuery) use ($branchId) {
            $branchQuery->where('branch_id', $branchId)
                ->orWhere('branch_owner_id', $branchId);
        }), $request);
    }

    private function applyFilters(Builder $query, ?Request $request): Builder
    {
        if (!$request) {
            return $query;
        }

        foreach (['captain_id', 'branch_id', 'client_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        return $query;
    }
}
