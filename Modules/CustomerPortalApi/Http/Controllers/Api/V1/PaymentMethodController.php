<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\CustomerPortalApi\Http\Resources\PaymentMethodResource;
use Modules\CustomerPortalApi\Models\PortalPaymentMethod;

class PaymentMethodController extends PortalController
{
    public function index(Request $request)
    {
        $methods = PortalPaymentMethod::where('client_id', $this->customerContext->requireClient()->id)
            ->whereNull('archived_at')
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->get();

        return $this->success($request, $methods->map(function ($method) use ($request) {
            return (new PaymentMethodResource($method))->resolve($request);
        })->values()->all());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'method' => ['required', 'string', 'in:mobile,card'],
            'label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'detail' => ['sometimes', 'nullable', 'string', 'max:255'],
            'isDefault' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->problem($request, 'VALIDATION_FAILED', 'Please correct the highlighted fields.', 422, $validator->errors()->toArray());
        }

        $client = $this->customerContext->requireClient();
        $method = DB::transaction(function () use ($client, $request) {
            $makeDefault = $request->boolean('isDefault') || !PortalPaymentMethod::where('client_id', $client->id)->whereNull('archived_at')->exists();
            if ($makeDefault) {
                PortalPaymentMethod::where('client_id', $client->id)->update(['is_default' => false]);
            }

            return PortalPaymentMethod::create([
                'client_id' => $client->id,
                'method' => $request->input('method'),
                'label' => $request->input('label') ?: $this->defaultLabel($request->input('method')),
                'detail' => $request->input('detail') ?: 'Saved for faster checkout',
                'is_default' => $makeDefault,
                'revision' => 1,
            ]);
        });

        return $this->success($request, (new PaymentMethodResource($method))->resolve($request), 201);
    }

    public function destroy(Request $request, $method)
    {
        $model = $this->ownedMethod($method);
        if (!$model) {
            return $this->problem($request, 'NOT_FOUND', 'Payment method not found.', 404);
        }

        $model->archived_at = now();
        $model->is_default = false;
        $model->revision = ((int) ($model->revision ?: 1)) + 1;
        $model->save();

        return response()->noContent(204)->withHeaders([
            'X-Request-ID' => (string) $request->attributes->get('portal_request_id'),
        ]);
    }

    public function makeDefault(Request $request, $method)
    {
        $model = $this->ownedMethod($method);
        if (!$model) {
            return $this->problem($request, 'NOT_FOUND', 'Payment method not found.', 404);
        }

        DB::transaction(function () use ($model) {
            PortalPaymentMethod::where('client_id', $model->client_id)->update(['is_default' => false]);
            $model->is_default = true;
            $model->revision = ((int) ($model->revision ?: 1)) + 1;
            $model->save();
        });

        return $this->index($request);
    }

    private function ownedMethod($id)
    {
        return PortalPaymentMethod::where('client_id', $this->customerContext->requireClient()->id)
            ->whereNull('archived_at')
            ->whereKey($id)
            ->first();
    }

    private function defaultLabel($method)
    {
        return $method === 'card' ? 'Bank card' : 'Mobile money';
    }
}
