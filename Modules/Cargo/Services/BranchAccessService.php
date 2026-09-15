<?php

namespace Modules\Cargo\Services;

use App\Models\User;
use Modules\Cargo\Entities\Branch;
use Modules\Cargo\Entities\Staff;
use Modules\Currency\Entities\Currency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Cargo module's single source of truth for branch access.
 *
 * It is intentionally read-only: callers can inspect an effective scope but
 * cannot impersonate a user or change the active authentication session.
 */
class BranchAccessService
{
    public function isTopAdmin(?User $user): bool
    {
        return $user && (int) $user->role === User::ADMIN;
    }

    public function branchIdFor(?User $user): ?int
    {
        if (!$user || $this->isTopAdmin($user)) {
            return null;
        }

        // The branch-user relationship is authoritative. Some historical
        // branch accounts (including Zimbabwe) have role 0 instead of 3, so
        // role-only detection incorrectly removed both branch scope and the
        // branch's configured currency.
        $ownedBranchId = Branch::where('user_id', $user->id)->value('id');
        if ($ownedBranchId) {
            return (int) $ownedBranchId;
        }

        // Historical installs use both 0 and 2 for staff accounts. Treating
        // only one as staff silently removed the branch boundary for the
        // other in audit and operational checks.
        if (in_array((int) $user->role, [User::STAFF, 2], true)) {
            return Staff::where('user_id', $user->id)->value('branch_id');
        }

        return null;
    }

    /** Return every branch the user is allowed to manage. */
    public function branchIdsFor(?User $user): Collection
    {
        if (!$user) {
            return collect();
        }

        if ($this->isTopAdmin($user)) {
            return Branch::where('is_archived', 0)->pluck('id')->map(fn ($id) => (int) $id);
        }

        $ownedBranchId = Branch::where('user_id', $user->id)->value('id');
        if ($ownedBranchId) {
            return collect([(int) $ownedBranchId]);
        }

        if (!in_array((int) $user->role, [User::STAFF, 2], true)) {
            return collect();
        }

        $staff = Staff::where('user_id', $user->id)->first(['id', 'branch_id']);
        if (!$staff) {
            return collect();
        }

        $branchIds = collect([$staff->branch_id]);
        if (Schema::hasTable('staff_branch_access')) {
            $branchIds = $branchIds->merge(
                DB::table('staff_branch_access')->where('staff_id', $staff->id)->pluck('branch_id')
            );
        }

        return $branchIds->filter()->map(fn ($id) => (int) $id)->unique()->values();
    }

    public function canAccessBranch(?User $user, int $branchId): bool
    {
        return $this->branchIdsFor($user)->contains($branchId);
    }

    /**
     * Resolve the currency that an authenticated user should operate in.
     * An authenticated user's assignment takes precedence. Authenticated users
     * without an assignment use the system default unless they explicitly
     * selected a branch context (for example, a top-admin report filter).
     */
    public function currencyFor(?User $user, ?Branch $contextBranch = null, bool $useExplicitBranchContext = false): string
    {
        return $this->currencyContextFor($user, $contextBranch, $useExplicitBranchContext)['currency'];
    }

    public function currencyContextFor(?User $user, ?Branch $contextBranch = null, bool $useExplicitBranchContext = false): array
    {
        $branchId = $this->branchIdFor($user);
        if ($branchId) {
            $branch = Branch::find($branchId);

            return [
                'currency' => strtoupper($branch?->default_currency ?: $this->systemCurrency()),
                'source' => 'assigned_branch',
                'branch_id' => $branch?->id,
                'branch_name' => $branch?->name,
            ];
        }

        if ((!$user || $useExplicitBranchContext) && $contextBranch?->default_currency) {
            return [
                'currency' => strtoupper($contextBranch->default_currency),
                'source' => $useExplicitBranchContext ? 'selected_branch' : 'shipment_branch',
                'branch_id' => $contextBranch->id,
                'branch_name' => $contextBranch->name,
            ];
        }

        return [
            'currency' => $this->systemCurrency(),
            'source' => 'system_default',
            'branch_id' => null,
            'branch_name' => null,
        ];
    }

    public function systemCurrency(): string
    {
        $currency = Currency::query()
            ->where('default', 1)
            ->where('status', 1)
            ->value('code');

        return strtoupper($currency ?: 'USD');
    }

    public function preview(User $user): array
    {
        $branchId = $this->branchIdFor($user);
        $branch = $branchId ? Branch::find($branchId) : null;
        $branches = Branch::whereIn('id', $this->branchIdsFor($user))->orderBy('name')->get(['id', 'name']);

        return [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'email' => $user->email,
            'role' => $user->userRole,
            'branch_id' => $branchId,
            'branch_name' => $branch?->name,
            'branch_ids' => $branches->pluck('id')->all(),
            'branch_names' => $branches->pluck('name')->all(),
            'scope' => $this->isTopAdmin($user) ? 'all_branches' : ($branches->isNotEmpty() ? 'assigned_branches' : 'no_branch_access'),
            'permissions' => $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
        ];
    }
}
