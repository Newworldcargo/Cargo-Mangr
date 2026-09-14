<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Modules\Cargo\Entities\ClientAddress;
use Modules\CustomerPortalApi\Http\Resources\AddressResource;

class SavedPlaceController extends PortalController
{
    public function index(Request $request)
    {
        $places = ClientAddress::where('client_id', $this->customerContext->requireClient()->id)
            ->where('is_archived', 0)
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->get();

        return $this->success($request, $places->map(function ($place) use ($request) {
            return $this->mobilePlace($place, $request);
        })->values()->all());
    }

    public function store(Request $request)
    {
        $validator = $this->validator($request);
        if ($validator->fails()) {
            return $this->problem($request, 'VALIDATION_FAILED', 'Please correct the highlighted fields.', 422, $validator->errors()->toArray());
        }

        $client = $this->customerContext->requireClient();
        $place = DB::transaction(function () use ($client, $request) {
            if ($request->boolean('isDefault')) {
                ClientAddress::where('client_id', $client->id)->update(['is_default' => 0]);
            }

            $model = new ClientAddress();
            $model->client_id = $client->id;
            $this->fillPlace($model, $request);
            $model->save();

            return $model;
        });

        return $this->success($request, $this->mobilePlace($place, $request), 201);
    }

    public function update(Request $request, $place)
    {
        $validator = $this->validator($request, true);
        if ($validator->fails()) {
            return $this->problem($request, 'VALIDATION_FAILED', 'Please correct the highlighted fields.', 422, $validator->errors()->toArray());
        }

        $model = $this->ownedPlace($place);
        if (!$model) {
            return $this->problem($request, 'NOT_FOUND', 'Saved place not found.', 404);
        }

        $this->fillPlace($model, $request, true);
        if (Schema::hasColumn('client_addresses', 'revision')) {
            $model->revision = ((int) ($model->revision ?: 1)) + 1;
        }
        $model->save();

        return $this->success($request, $this->mobilePlace($model->fresh(), $request));
    }

    public function destroy(Request $request, $place)
    {
        $model = $this->ownedPlace($place);
        if (!$model) {
            return $this->problem($request, 'NOT_FOUND', 'Saved place not found.', 404);
        }

        $model->is_archived = 1;
        if (Schema::hasColumn('client_addresses', 'revision')) {
            $model->revision = ((int) ($model->revision ?: 1)) + 1;
        }
        $model->save();

        return response()->noContent(204)->withHeaders([
            'X-Request-ID' => (string) $request->attributes->get('portal_request_id'),
        ]);
    }

    private function validator(Request $request, $partial = false)
    {
        $required = $partial ? 'sometimes' : 'required';

        return Validator::make($request->all(), [
            'label' => [$required, 'string', 'max:255'],
            'detail' => [$required, 'string', 'max:1000'],
            'isDefault' => ['sometimes', 'boolean'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'area' => ['sometimes', 'nullable', 'string', 'max:120'],
            'country' => ['sometimes', 'nullable', 'string', 'max:120'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ]);
    }

    private function fillPlace($model, Request $request, $partial = false)
    {
        if (!$partial || $request->has('label')) {
            if (Schema::hasColumn('client_addresses', 'label')) {
                $model->label = $request->input('label');
            } else {
                $model->client_url = 'portal-label:' . base64_encode((string) $request->input('label'));
            }
        }
        if (!$partial || $request->has('detail')) {
            $model->address = $request->input('detail');
            $model->client_street_address_map = $request->input('detail');
        }
        if (!$partial || $request->has('latitude')) {
            $model->client_lat = $request->input('latitude');
        }
        if (!$partial || $request->has('longitude')) {
            $model->client_lng = $request->input('longitude');
        }
        if ($request->filled('country')) {
            $model->country_id = (int) (DB::table('countries')->where('name', $request->input('country'))->value('id') ?: $model->country_id);
        }
        if ($request->filled('city')) {
            $model->state_id = (int) (DB::table('states')->where('country_id', $model->country_id ?: $this->defaultCountryId())->where('name', $request->input('city'))->value('id') ?: $model->state_id);
        }
        if (!$partial || $request->has('isDefault')) {
            $model->is_default = $request->boolean('isDefault');
        }

        $model->is_archived = 0;
        $model->country_id = $model->country_id ?: $this->defaultCountryId();
        $model->state_id = $model->state_id ?: $this->defaultStateId($model->country_id);
        if (Schema::hasColumn('client_addresses', 'revision')) {
            $model->revision = $model->revision ?: 1;
        }
    }

    private function mobilePlace($place, Request $request)
    {
        $resource = (new AddressResource($place))->resolve($request);

        return [
            'id' => $resource['id'],
            'label' => $resource['label'] ?: $this->legacyLabel($place) ?: 'Saved place',
            'detail' => $resource['address'] ?: $resource['streetAddressMap'] ?: 'Saved location',
            'address' => $resource['address'],
            'city' => optional($place->state)->name,
            'area' => optional($place->area)->name,
            'country' => optional($place->country)->name,
            'lat' => $resource['lat'],
            'lng' => $resource['lng'],
            'isDefault' => $resource['isDefault'],
            'revision' => $resource['revision'],
        ];
    }

    private function legacyLabel($place)
    {
        $value = (string) $place->client_url;
        if (!str_starts_with($value, 'portal-label:')) {
            return null;
        }

        return base64_decode(substr($value, strlen('portal-label:')), true) ?: null;
    }

    private function ownedPlace($id)
    {
        return ClientAddress::where('client_id', $this->customerContext->requireClient()->id)
            ->where('is_archived', 0)
            ->whereKey($id)
            ->first();
    }

    private function defaultCountryId()
    {
        return (int) (DB::table('countries')->where('iso2', 'ZM')->value('id') ?: DB::table('countries')->orderBy('id')->value('id') ?: 1);
    }

    private function defaultStateId($countryId)
    {
        return (int) (DB::table('states')->where('country_id', $countryId)->orderBy('id')->value('id') ?: DB::table('states')->orderBy('id')->value('id') ?: 1);
    }
}
