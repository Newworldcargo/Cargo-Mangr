<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Modules\CustomerPortalApi\Http\Resources\AuthUserResource;

class SessionController extends PortalController
{
    public function show(Request $request)
    {
        $user = $this->customerContext->user();
        $client = $this->customerContext->client();

        if (!$user || !$client || (int) $user->role !== 4) {
            return $this->problem($request, 'UNAUTHENTICATED', 'No valid customer session was found.', 401);
        }

        $user->setRelation('portalClient', $client);

        return $this->success($request, (new AuthUserResource($user))->resolve($request));
    }
}
