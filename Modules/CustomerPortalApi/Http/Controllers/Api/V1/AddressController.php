<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\Cargo\Entities\ClientAddress;
use Modules\CustomerPortalApi\Http\Resources\AddressResource;

class AddressController extends PortalController
{
    public function index(Request $request)
    {
        $client = $this->customerContext->requireClient();
        $addresses = ClientAddress::where('client_id', $client->id)
            ->where('is_archived', 0)
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->get();

        return $this->success($request, $addresses->map(function ($address) use ($request) {
            return (new AddressResource($address))->resolve($request);
        })->values()->all());
    }

    public function store(Request $request)
    {
        $validator = $this->validator($request);
        if ($validator->fails()) {
            return $this->problem($request, 'VALIDATION_FAILED', 'Please correct the highlighted fields.', 422, $validator->errors()->toArray());
        }

        $client = $this->customerContext->requireClient();
        $address = DB::transaction(function () use ($request, $client) {
            if ($request->boolean('isDefault')) {
                ClientAddress::where('client_id', $client->id)->update(['is_default' => 0]);
            }

            $address = new ClientAddress();
            $address->client_id = $client->id;
            $this->fillAddress($address, $request);
            $address->save();

            return $address;
        });

        return $this->success($request, (new AddressResource($address))->resolve($request), 201);
    }

    public function show(Request $request, $address)
    {
        $model = $this->ownedAddress($address);
        if (!$model) {
            return $this->problem($request, 'NOT_FOUND', 'Address not found.', 404);
        }

        return $this->success($request, (new AddressResource($model))->resolve($request));
    }

    public function update(Request $request, $address)
    {
        $validator = $this->validator($request, true);
        if ($validator->fails()) {
            return $this->problem($request, 'VALIDATION_FAILED', 'Please correct the highlighted fields.', 422, $validator->errors()->toArray());
        }

        $model = $this->ownedAddress($address);
        if (!$model) {
            return $this->problem($request, 'NOT_FOUND', 'Address not found.', 404);
        }

        $conflict = $this->checkRevision($request, $model);
        if ($conflict) {
            return $this->problem($request, 'REVISION_CONFLICT', 'The address has changed since it was loaded.', 409);
        }

        $client = $this->customerContext->requireClient();
        DB::transaction(function () use ($request, $model, $client) {
            if ($request->boolean('isDefault')) {
                ClientAddress::where('client_id', $client->id)->where('id', '!=', $model->id)->update(['is_default' => 0]);
            }

            $this->fillAddress($model, $request, true);
            $model->revision = ((int) ($model->revision ?: 1)) + 1;
            $model->save();
        });

        return $this->success($request, (new AddressResource($model->fresh()))->resolve($request));
    }

    public function destroy(Request $request, $address)
    {
        $model = $this->ownedAddress($address);
        if (!$model) {
            return $this->problem($request, 'NOT_FOUND', 'Address not found.', 404);
        }

        $conflict = $this->checkRevision($request, $model);
        if ($conflict) {
            return $this->problem($request, 'REVISION_CONFLICT', 'The address has changed since it was loaded.', 409);
        }

        $model->is_archived = 1;
        $model->revision = ((int) ($model->revision ?: 1)) + 1;
        $model->save();

        return response()->noContent(204)->withHeaders([
            'X-Request-ID' => (string) $request->attributes->get('portal_request_id'),
        ]);
    }

    private function ownedAddress($id)
    {
        $client = $this->customerContext->client();
        if (!$client) {
            return null;
        }

        return ClientAddress::where('client_id', $client->id)
            ->where('is_archived', 0)
            ->where('id', $id)
            ->first();
    }

    private function validator(Request $request, $partial = false)
    {
        $required = $partial ? 'sometimes' : 'required';

        return Validator::make($request->all(), [
            'address' => [$required, 'string', 'max:1000'],
            'countryId' => [$required, 'integer'],
            'stateId' => [$required, 'integer'],
            'areaId' => ['sometimes', 'nullable', 'integer'],
            'streetAddressMap' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'lat' => ['sometimes', 'nullable', 'string', 'max:100'],
            'lng' => ['sometimes', 'nullable', 'string', 'max:100'],
            'url' => ['sometimes', 'nullable', 'url', 'max:1000'],
            'isDefault' => ['sometimes', 'boolean'],
        ]);
    }

    private function fillAddress($address, Request $request, $partial = false)
    {
        $map = [
            'address' => 'address',
            'countryId' => 'country_id',
            'stateId' => 'state_id',
            'areaId' => 'area_id',
            'streetAddressMap' => 'client_street_address_map',
            'lat' => 'client_lat',
            'lng' => 'client_lng',
            'url' => 'client_url',
            'isDefault' => 'is_default',
        ];

        foreach ($map as $input => $column) {
            if (!$partial || $request->has($input)) {
                $address->{$column} = $request->input($input);
            }
        }
    }

    private function checkRevision(Request $request, $model)
    {
        $expected = $request->header('If-Match');
        if ($expected === null || $expected === '') {
            return false;
        }

        return (int) trim($expected, '"') !== (int) ($model->revision ?: 1);
    }
}
