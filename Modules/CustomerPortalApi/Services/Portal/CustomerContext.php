<?php

namespace Modules\CustomerPortalApi\Services\Portal;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

class CustomerContext
{
    public function __construct(private readonly PortalCustomerAccess $portalAccess)
    {
    }

    public function user()
    {
        return Auth::guard('web')->user();
    }

    public function client()
    {
        $user = $this->user();

        return $this->portalAccess->clientFor($user);
    }

    public function requireClient()
    {
        $client = $this->client();

        if (!$client) {
            throw new AuthorizationException('The authenticated account is not a customer portal account.');
        }

        return $client;
    }
}
