<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\CustomerPortalApi\Http\Resources\PortalQuoteResource;
use Modules\CustomerPortalApi\Http\Resources\ShipmentDraftResource;
use Modules\CustomerPortalApi\Models\PortalQuote;
use Modules\CustomerPortalApi\Models\PortalShipmentDraft;
use Modules\CustomerPortalApi\Http\Resources\ShipmentResource;
use Modules\Cargo\Entities\Branch;
use Modules\Cargo\Entities\Country;
use Modules\Cargo\Entities\Shipment;
use Modules\Cargo\Entities\ShipmentSetting;
use Modules\Cargo\Entities\State;

class DraftQuoteController extends PortalController
{
    public function drafts(Request $request)
    {
        $drafts = PortalShipmentDraft::where('client_id', $this->customerContext->requireClient()->id)
            ->whereIn('status', ['draft', 'quoted'])
            ->orderByDesc('updated_at')->limit(100)->get();
        return $this->success($request, $drafts->map(function ($draft) use ($request) {
            return (new ShipmentDraftResource($draft))->resolve($request);
        })->values()->all());
    }

    public function createDraft(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payload' => ['required', 'array'],
            'expiresAt' => ['sometimes', 'nullable', 'date'],
        ]);
        if ($validator->fails()) {
            return $this->problem($request, 'VALIDATION_FAILED', 'A valid draft payload is required.', 422, $validator->errors()->toArray());
        }

        $client = $this->customerContext->requireClient();
        $draft = PortalShipmentDraft::create([
            'client_id' => $client->id,
            'status' => 'draft',
            'payload' => $request->input('payload'),
            'expires_at' => $request->input('expiresAt'),
            'revision' => 1,
        ]);
        return $this->success($request, (new ShipmentDraftResource($draft))->resolve($request), 201);
    }

    public function showDraft(Request $request, $draft)
    {
        $model = $this->ownedDraft($draft);
        if (!$model) return $this->problem($request, 'NOT_FOUND', 'Draft not found.', 404);
        return $this->success($request, (new ShipmentDraftResource($model))->resolve($request));
    }

    public function updateDraft(Request $request, $draft)
    {
        $model = $this->ownedDraft($draft);
        if (!$model) return $this->problem($request, 'NOT_FOUND', 'Draft not found.', 404);
        if ($this->revisionConflict($request, $model)) return $this->problem($request, 'REVISION_CONFLICT', 'The draft has changed since it was loaded.', 409);

        $validator = Validator::make($request->all(), [
            'payload' => ['sometimes', 'array'],
            'expiresAt' => ['sometimes', 'nullable', 'date'],
        ]);
        if ($validator->fails()) return $this->problem($request, 'VALIDATION_FAILED', 'Please correct the draft fields.', 422, $validator->errors()->toArray());

        if ($request->has('payload')) $model->payload = $request->input('payload');
        if ($request->has('expiresAt')) $model->expires_at = $request->input('expiresAt');
        $model->revision = ((int) ($model->revision ?: 1)) + 1;
        $model->save();
        return $this->success($request, (new ShipmentDraftResource($model->fresh()))->resolve($request));
    }

    public function deleteDraft(Request $request, $draft)
    {
        $model = $this->ownedDraft($draft);
        if (!$model) return $this->problem($request, 'NOT_FOUND', 'Draft not found.', 404);
        if ($this->revisionConflict($request, $model)) return $this->problem($request, 'REVISION_CONFLICT', 'The draft has changed since it was loaded.', 409);
        $model->status = 'deleted';
        $model->revision = ((int) ($model->revision ?: 1)) + 1;
        $model->save();
        return response()->noContent(204)->withHeaders(['X-Request-ID' => (string) $request->attributes->get('portal_request_id')]);
    }

    public function submitDraft(Request $request, $draft)
    {
        $model = $this->ownedDraft($draft);
        if (!$model || !in_array($model->status, ['draft', 'quoted'], true)) return $this->problem($request, 'NOT_FOUND', 'Draft not found.', 404);
        if ($this->revisionConflict($request, $model)) return $this->problem($request, 'REVISION_CONFLICT', 'This request has changed. Refresh it and try again.', 409);

        $payload = (array) $model->payload;
        $form = (array) ($payload['form'] ?? []);
        $cargoRows = array_values(array_filter((array) ($payload['cargoRows'] ?? []), function ($row) {
            return is_array($row) && trim((string) ($row['name'] ?? '')) !== '' && (int) ($row['quantity'] ?? 0) > 0;
        }));
        $validator = Validator::make([
            'pickup' => $form['pickup'] ?? null,
            'recipient' => $form['recipient'] ?? null,
            'phone' => $form['phone'] ?? null,
            'cargoRows' => $cargoRows,
        ], [
            'pickup' => ['required', 'string', 'max:1000'],
            'recipient' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:60'],
            'cargoRows' => ['required', 'array', 'min:1'],
        ]);
        if ($validator->fails()) return $this->problem($request, 'VALIDATION_FAILED', 'Complete the shipment request before submitting it.', 422, $validator->errors()->toArray());

        $client = $this->customerContext->requireClient();
        $branch = Branch::where('is_archived', 0)->whereKey($form['pickupBranchId'] ?? null)->first()
            ?: Branch::where('is_archived', 0)->orderBy('id')->first();
        if (!$branch) return $this->problem($request, 'DEPENDENCY_UNAVAILABLE', 'No collection branch is available right now.', 503);

        try {
            $shipment = DB::transaction(function () use ($model, $payload, $form, $cargoRows, $client, $branch) {
                $origin = $this->locationProfile($branch->name . ' ' . $branch->address);
                $destination = $this->locationProfile((string) ($form['destination'] ?? ''));
                $shipment = Shipment::create([
                    'code' => '-',
                    'status_id' => Shipment::REQUESTED_STATUS,
                    'type' => Shipment::PICKUP,
                    'branch_id' => $branch->id,
                    'shipping_date' => now()->toDateString(),
                    'client_status' => Shipment::CLIENT_STATUS_CREATED,
                    'client_id' => $client->id,
                    'client_phone' => $client->responsible_mobile,
                    'client_address' => (string) $form['pickup'],
                    'reciver_name' => (string) $form['recipient'],
                    'reciver_phone' => (string) $form['phone'],
                    'reciver_address' => (string) ($form['destination'] ?? ''),
                    'from_country_id' => $origin['country']->id,
                    'from_state_id' => $origin['state']->id,
                    'to_country_id' => $destination['country']->id,
                    'to_state_id' => $destination['state']->id,
                    'payment_type' => Shipment::POSTPAID,
                    'order_id' => 'PORTAL-' . strtoupper(substr((string) \Illuminate\Support\Str::uuid(), 0, 12)),
                    'total_weight' => count($cargoRows),
                    'amount_to_be_collected' => 0,
                ]);
                $width = max(5, (int) (ShipmentSetting::getVal('shipment_code_count') ?: 5));
                $shipment->barcode = str_pad((string) $shipment->id, $width, '0', STR_PAD_LEFT);
                $shipment->code = (string) (ShipmentSetting::getVal('shipment_prefix') ?: 'NWC') . $shipment->barcode;
                $shipment->save();
                $payload['submittedShipmentId'] = $shipment->id;
                $model->payload = $payload;
                $model->status = 'submitted';
                $model->revision = ((int) ($model->revision ?: 1)) + 1;
                $model->save();
                return $shipment;
            });
        } catch (\Throwable $exception) {
            report($exception);
            return $this->problem($request, 'ORDER_SUBMISSION_FAILED', 'We could not create your shipment order. Your draft is still safe.', 500);
        }

        return $this->success($request, (new ShipmentResource($shipment->load(['consignment.trackingHistory', 'from_address', 'packages'])))->resolve($request), 201);
    }

    private function locationProfile(string $text): array
    {
        $value = strtolower($text);
        $countryName = str_contains($value, 'china') ? 'China' : (str_contains($value, 'zimbabwe') ? 'Zimbabwe' : 'Zambia');
        $country = Country::where('covered', 1)->where('name', $countryName)->firstOrFail();
        $preferredState = $countryName === 'China' ? 'Guangdong' : ($countryName === 'Zambia' && str_contains($value, 'kitwe') ? 'Copperbelt' : ($countryName === 'Zambia' ? 'Lusaka' : null));
        $stateQuery = State::where('covered', 1)->where('country_id', $country->id);
        $state = $preferredState ? (clone $stateQuery)->where('name', 'like', '%' . $preferredState . '%')->first() : null;
        return ['country' => $country, 'state' => $state ?: $stateQuery->orderBy('id')->firstOrFail()];
    }

    public function createQuote(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transportMode' => ['required', 'in:air,sea'],
            'deliveryOption' => ['required', 'string', 'max:50'],
            'snapshot' => ['required', 'array'],
        ]);
        if ($validator->fails()) return $this->problem($request, 'VALIDATION_FAILED', 'A valid quote request is required.', 422, $validator->errors()->toArray());

        $client = $this->customerContext->requireClient();
        $quote = PortalQuote::create([
            'client_id' => $client->id,
            'draft_id' => $request->input('draftId'),
            'transport_mode' => $request->input('transportMode'),
            'delivery_option' => $request->input('deliveryOption'),
            'snapshot' => $request->input('snapshot'),
            'currency' => 'USD',
            'amount_minor' => 0,
            'assumptions' => ['pricingStatus' => 'pending_operations_pricing'],
            'status' => 'active',
            'expires_at' => now()->addHours(24),
            'revision' => 1,
        ]);
        return $this->success($request, (new PortalQuoteResource($quote))->resolve($request), 201);
    }

    public function showQuote(Request $request, $quote)
    {
        $model = PortalQuote::where('client_id', $this->customerContext->requireClient()->id)->whereKey($quote)->first();
        if (!$model) return $this->problem($request, 'NOT_FOUND', 'Quote not found.', 404);
        return $this->success($request, (new PortalQuoteResource($model))->resolve($request));
    }

    private function ownedDraft($id)
    {
        return PortalShipmentDraft::where('client_id', $this->customerContext->requireClient()->id)->whereKey($id)->where('status', '!=', 'deleted')->first();
    }

    private function revisionConflict(Request $request, $model)
    {
        $expected = $request->header('If-Match');
        return $expected !== null && $expected !== '' && (int) trim($expected, '"') !== (int) ($model->revision ?: 1);
    }
}
