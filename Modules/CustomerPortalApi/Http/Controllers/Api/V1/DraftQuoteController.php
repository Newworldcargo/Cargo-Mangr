<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\CustomerPortalApi\Http\Resources\PortalQuoteResource;
use Modules\CustomerPortalApi\Http\Resources\ShipmentDraftResource;
use Modules\CustomerPortalApi\Models\PortalQuote;
use Modules\CustomerPortalApi\Models\PortalShipmentDraft;

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
