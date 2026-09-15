<?php

namespace Modules\Cargo\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Cargo\Entities\Client;
use Modules\Cargo\Entities\Driver;

/**
 * Applies the effective transaction visibility policy in one place.
 *
 * A staff user without manage-transactions is a cashier: they may only see
 * transactions they created. The management permission broadens visibility to
 * the staff member's assigned branches; it never grants global visibility.
 */
class TransactionScopeService
{
    public function __construct(private readonly BranchAccessService $branchAccess)
    {
    }

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
                return $this->applyBranchScope($query, $this->branchAccess->branchIdsFor($user), $request);

            case 4:
                return $this->applyFilters($query->where('client_id', Client::where('user_id', $user->id)->value('id')), $request);

            case 5:
                return $this->applyFilters($query->where('captain_id', Driver::where('user_id', $user->id)->value('id')), $request);

            case User::STAFF:
            case 2:
                $branchIds = $this->branchAccess->branchIdsFor($user);
                if ($user->can('manage-transactions') && $branchIds->isNotEmpty()) {
                    return $this->applyBranchScope($query, $branchIds, $request);
                }

                return $this->applyFilters($query->where('created_by', $user->id), $request);

            default:
                return $query->whereRaw('1 = 0');
        }
    }

    private function applyBranchScope(Builder $query, $branchIds, ?Request $request): Builder
    {
        if ($branchIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $this->applyFilters($query->where(function (Builder $branchQuery) use ($branchIds) {
            $branchQuery->whereIn('branch_id', $branchIds)
                ->orWhereIn('branch_owner_id', $branchIds);
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
