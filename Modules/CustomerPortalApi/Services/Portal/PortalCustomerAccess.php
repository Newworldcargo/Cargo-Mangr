<?php

namespace Modules\CustomerPortalApi\Services\Portal;

use App\Models\User;
use Modules\Cargo\Entities\Client;

class PortalCustomerAccess
{
    /**
     * A portal customer is an account with an active customer profile. This is
     * deliberately independent from the user's staff/admin role so one person
     * can safely act as both an employee and a company customer.
     */
    public function clientFor(?User $user): ?Client
    {
        if (!$user) {
            return null;
        }

        return Client::where('user_id', $user->id)
            ->where('is_archived', 0)
            ->first();
    }

    public function canAccess(?User $user): bool
    {
        return $this->clientFor($user) !== null;
    }
}
