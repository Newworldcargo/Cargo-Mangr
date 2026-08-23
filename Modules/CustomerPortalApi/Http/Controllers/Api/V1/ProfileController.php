<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\CustomerPortalApi\Http\Resources\AuthUserResource;

class ProfileController extends PortalController
{
    public function show(Request $request)
    {
        $user = $this->customerContext->user();
        $client = $this->customerContext->client();

        if (!$user || !$client) {
            return $this->problem($request, 'FORBIDDEN', 'This account is not enabled for the customer portal.', 403);
        }

        $user->setRelation('portalClient', $client);

        return $this->success($request, (new AuthUserResource($user))->resolve($request));
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'firstName' => ['sometimes', 'required', 'string', 'min:2', 'max:80'],
            'lastName' => ['sometimes', 'nullable', 'string', 'max:80'],
            'phone' => ['sometimes', 'required', 'string', 'max:30'],
        ]);

        if ($validator->fails()) {
            return $this->problem($request, 'VALIDATION_FAILED', 'Please correct the highlighted fields.', 422, $validator->errors()->toArray());
        }

        $user = $this->customerContext->user();
        $client = $this->customerContext->requireClient();
        $name = trim(($request->has('firstName') ? $request->input('firstName') : $this->firstName($user)) . ' ' . ($request->has('lastName') ? $request->input('lastName') : $this->lastName($user)));

        DB::transaction(function () use ($request, $user, $client, $name) {
            $user->name = $name;
            if ($request->has('phone')) {
                $user->responsible_mobile = trim($request->input('phone'));
            }
            $user->save();

            $client->name = $name;
            $client->email = $user->email;
            if ($request->has('phone')) {
                $client->responsible_mobile = trim($request->input('phone'));
            }
            $client->save();
        });

        $user->setRelation('portalClient', $client->fresh());

        return $this->success($request, (new AuthUserResource($user->fresh()))->resolve($request));
    }

    private function firstName($user)
    {
        return preg_split('/\s+/', trim((string) $user->name), 2)[0] ?? '';
    }

    private function lastName($user)
    {
        $parts = preg_split('/\s+/', trim((string) $user->name), 2);
        return $parts[1] ?? '';
    }
}
