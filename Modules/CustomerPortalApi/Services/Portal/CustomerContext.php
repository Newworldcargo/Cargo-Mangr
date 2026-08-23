<?php

namespace Modules\CustomerPortalApi\Services\Portal;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Modules\Cargo\Entities\Client;

class CustomerContext
{
    public function user()
    {
        return Auth::guard('web')->user();
    }

    public function client()
    {
        $user = $this->user();

        if (!$user || (int) $user->role !== 4) {
            return null;
        }

        return Client::where('user_id', $user->id)->first();
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
