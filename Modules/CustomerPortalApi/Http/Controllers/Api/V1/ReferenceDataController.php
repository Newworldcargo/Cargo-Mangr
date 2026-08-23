<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReferenceDataController extends PortalController
{
    public function show(Request $request)
    {
        $offices = DB::table('branches')
            ->where('is_archived', 0)
            ->orderBy('name')
            ->get(['id', 'name', 'address'])
            ->map(function ($office) {
                return [
                    'id' => (string) $office->id,
                    'name' => $office->name,
                    'address' => $office->address,
                    'detail' => $office->address,
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
