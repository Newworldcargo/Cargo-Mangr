<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReferenceDataController extends PortalController
{
    public function show(Request $request)
    {
        $offices = DB::table('branches as branches')
            ->leftJoin('countries as countries', 'countries.id', '=', 'branches.country_id')
            ->leftJoin('states as states', 'states.id', '=', 'branches.state_id')
            ->where('branches.is_archived', 0)
            ->orderBy('branches.name')
            ->get([
                'branches.id',
                'branches.name',
                'branches.address',
                'branches.country_code',
                'countries.name as country',
                'countries.iso2 as countryIso2',
                'states.name as city',
                'states.latitude as stateLatitude',
                'states.longitude as stateLongitude',
                'countries.latitude as countryLatitude',
                'countries.longitude as countryLongitude',
            ])
            ->map(function ($office) {
                return [
                    'id' => (string) $office->id,
                    'name' => $office->name,
                    'address' => $office->address,
                    'detail' => $office->address,
                    'country' => $office->country,
                    'countryCode' => strtoupper((string) ($office->countryIso2 ?: $office->country_code)),
                    'city' => $office->city,
                    'latitude' => $office->stateLatitude !== null ? (float) $office->stateLatitude : ($office->countryLatitude !== null ? (float) $office->countryLatitude : null),
                    'longitude' => $office->stateLongitude !== null ? (float) $office->stateLongitude : ($office->countryLongitude !== null ? (float) $office->countryLongitude : null),
                ];
            })->values()->all();

        $deliveryOptions = DB::table('delivery_time')
            ->orderBy('id')
            ->get()
            ->map(function ($option) {
                return [
                    'id' => (string) $option->id,
                    'name' => $option->name ?? 'Standard delivery',
                    'detail' => $option->hours ? $option->hours . ' hours' : null,
                    'eta' => $option->hours ? $option->hours . ' hours' : null,
                    'price' => [
                        'currency' => 'USD',
                        'amountMinor' => 0,
                    ],
                    'recommended' => false,
                ];
            })->values()->all();

        return $this->success($request, [
            'offices' => $offices,
            'deliveryOptions' => $deliveryOptions,
            'transportOptions' => [
                ['id' => 'air', 'name' => 'Air freight', 'detail' => 'Faster air cargo service.', 'eta' => null],
                ['id' => 'sea', 'name' => 'Sea freight', 'detail' => 'Economical sea cargo service.', 'eta' => null],
            ],
        ], 200, ['cacheSeconds' => 300]);
    }
}
