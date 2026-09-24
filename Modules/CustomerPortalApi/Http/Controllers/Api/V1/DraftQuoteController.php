<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\CustomerPortalApi\Http\Resources\PortalQuoteResource;
use Modules\CustomerPortalApi\Http\Resources\ShipmentDraftResource;
use Modules\CustomerPortalApi\Http\Resources\OnlineBookingResource;
use Modules\CustomerPortalApi\Models\PortalFile;
use Modules\CustomerPortalApi\Models\PortalQuote;
use Modules\CustomerPortalApi\Models\PortalShipmentDraft;
use Modules\CustomerPortalApi\Services\Pricing\MobileBookingQuoteSigner;
use Modules\CustomerPortalApi\Http\Resources\ShipmentResource;
use Modules\Cargo\Entities\Branch;
use Modules\Cargo\Entities\Country;
use Modules\Cargo\Entities\Package;
use Modules\Cargo\Entities\PackageShipment;
use Modules\Cargo\Entities\Shipment;
use Modules\Cargo\Entities\ShipmentSetting;
use Modules\Cargo\Entities\State;
use Modules\Cargo\Services\OnlineBookingService;

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
        try {
            app(\Modules\CustomerPortalApi\Services\BookingServiceArea::class)->validate((string) ($payload['service'] ?? ''),
                ['latitude' => $form['pickupLatitude'] ?? null, 'longitude' => $form['pickupLongitude'] ?? null],
                ['latitude' => $form['destinationLatitude'] ?? null, 'longitude' => $form['destinationLongitude'] ?? null]);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return $this->problem($request, 'OUTSIDE_SERVICE_AREA', collect($exception->errors())->flatten()->first(), 422, $exception->errors());
        }
        $cargoRows = $this->normaliseCargoRows((array) ($payload['cargoRows'] ?? []));
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
        $quote = null;
        $service = (string) ($payload['service'] ?? '');
        $requiresSignedQuote = app(\Modules\Cargo\Services\MobilePricingSettings::class)->advancePricingEnabled($service);
        if ($requiresSignedQuote) {
            try {
                $quote = app(MobileBookingQuoteSigner::class)->requireValidQuote(
                    (array) ($payload['pricing'] ?? []),
                    (int) $client->id,
                    $service
                );
                if (($quote->assumptions['pricingStatus'] ?? '') !== 'priced') {
                    throw new \InvalidArgumentException('A priced quote is required.');
                }
            } catch (\InvalidArgumentException $exception) {
                return $this->problem($request, 'QUOTE_REQUIRED', 'Refresh your price before submitting this booking.', 422, [], true);
            }
        }
        $branch = Branch::where('is_archived', 0)->whereKey($form['pickupBranchId'] ?? null)->first()
            ?: Branch::where('is_archived', 0)->orderBy('id')->first();
        if (!$branch) return $this->problem($request, 'DEPENDENCY_UNAVAILABLE', 'No collection branch is available right now.', 503);

        try {
            $booking = app(OnlineBookingService::class)->createFromPortalDraft(
                $model, $client, $branch, $payload, $cargoRows, $quote
            );
        } catch (\DomainException $exception) {
            return $this->problem($request, 'REVISION_CONFLICT', $exception->getMessage(), 409);
        } catch (\Throwable $exception) {
            report($exception);
            return $this->problem($request, 'ORDER_SUBMISSION_FAILED', 'We could not create your booking request. Your draft is still safe.', 500);
        }

        return $this->success($request, (new OnlineBookingResource($booking))->resolve($request), 201);
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

    private function normaliseCargoRows(array $rows): array
    {
        return collect($rows)
            ->filter(fn ($row) => is_array($row) && trim((string) ($row['name'] ?? $row['description'] ?? '')) !== '')
            ->map(function (array $row) {
                $quantity = max(1, (int) ($row['quantity'] ?? $row['qty'] ?? 1));
                $weight = $this->decimalFromMixed($row['weight'] ?? $row['totalWeight'] ?? null);
                $amount = $this->decimalFromMixed($row['amount'] ?? $row['price'] ?? $row['bill'] ?? null);
                return [
                    'name' => trim((string) ($row['name'] ?? $row['description'] ?? 'Cargo item')),
                    'quantity' => $quantity,
                    'weight' => $weight,
                    'amount' => $amount,
                ];
            })
            ->values()
            ->all();
    }

    private function cargoTotals(array $rows): array
    {
        $weight = collect($rows)->sum(fn ($row) => (float) ($row['weight'] ?? 0));
        $amount = collect($rows)->sum(fn ($row) => (float) ($row['amount'] ?? 0));

        return [
            'weight' => $weight > 0 ? $weight : max(1, count($rows)),
            'amount' => max(0, $amount),
        ];
    }

    private function createShipmentPackageRows(Shipment $shipment, array $rows): void
    {
        $packageId = Package::orderBy('id')->value('id');
        if (!$packageId) return;

        foreach ($rows as $row) {
            PackageShipment::create([
                'package_id' => $packageId,
                'shipment_id' => $shipment->id,
                'description' => $row['name'],
                'weight' => $row['weight'] ?: null,
                'length' => 1,
                'width' => 1,
                'height' => 1,
                'qty' => $row['quantity'],
            ]);
        }
    }

    private function decimalFromMixed($value): float
    {
        if ($value === null || $value === '') return 0.0;
        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);
        return is_numeric($clean) ? (float) $clean : 0.0;
    }

    private function shipmentEvidenceFromPayload(array $payload, int $clientId): array
    {
        $draft = (array) ($payload['draft'] ?? []);
        $attachments = array_merge((array) ($draft['cargoPhotos'] ?? []), array_filter([$draft['supportingDocument'] ?? null]));
        $fileIds = collect($attachments)
            ->map(fn ($attachment) => is_array($attachment) ? ($attachment['fileId'] ?? null) : null)
            ->filter()
            ->unique()
            ->values();
        if ($fileIds->isEmpty()) return [];

        return PortalFile::where('client_id', $clientId)
            ->whereIn('file_id', $fileIds->all())
            ->where('status', 'scan_pending')
            ->get()
            ->map(fn (PortalFile $file) => [
                'file_id' => (string) $file->file_id,
                'name' => (string) $file->original_name,
                'content_type' => (string) $file->content_type,
                'size_bytes' => (int) $file->size_bytes,
                'purpose' => (string) $file->purpose,
            ])
            ->values()
            ->all();
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
