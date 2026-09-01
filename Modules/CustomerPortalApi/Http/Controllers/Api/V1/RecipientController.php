<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\Cargo\Entities\Receiver;
use Modules\CustomerPortalApi\Http\Resources\RecipientResource;

class RecipientController extends PortalController
{
    public function index(Request $request)
    {
        $user = $this->customerContext->user();
        $recipients = Receiver::where('user_id', $user->id)
            ->where('is_archived', 0)
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = trim((string) $request->input('q'));
                $query->where(function ($recipientQuery) use ($term) {
                    $recipientQuery->where('name', 'like', '%' . $term . '%')
                        ->orWhere('receiver_mobile', 'like', '%' . $term . '%')
                        ->orWhere('reciver_address', 'like', '%' . $term . '%');
                });
            })
            ->orderByDesc('updated_at')
            ->get();

        return $this->success($request, $recipients->map(function ($recipient) use ($request) {
            return (new RecipientResource($recipient))->resolve($request);
        })->values()->all());
    }

    public function store(Request $request)
    {
        $validator = $this->validator($request);
        if ($validator->fails()) {
            return $this->problem($request, 'VALIDATION_FAILED', 'Please correct the highlighted fields.', 422, $validator->errors()->toArray());
        }

        $user = $this->customerContext->user();
        $recipient = new Receiver();
        $recipient->user_id = $user->id;
        $recipient->branch_id = null;
        $recipient->is_archived = 0;
        $this->fillRecipient($recipient, $request);
        $recipient->save();

        return $this->success($request, (new RecipientResource($recipient))->resolve($request), 201);
    }

    public function show(Request $request, $recipient)
    {
        $model = $this->ownedRecipient($recipient);
        if (!$model) {
            return $this->problem($request, 'NOT_FOUND', 'Recipient not found.', 404);
        }

        return $this->success($request, (new RecipientResource($model))->resolve($request));
    }

    public function update(Request $request, $recipient)
    {
        $validator = $this->validator($request, true);
        if ($validator->fails()) {
            return $this->problem($request, 'VALIDATION_FAILED', 'Please correct the highlighted fields.', 422, $validator->errors()->toArray());
        }

        $model = $this->ownedRecipient($recipient);
        if (!$model) {
            return $this->problem($request, 'NOT_FOUND', 'Recipient not found.', 404);
        }

        if ($this->revisionConflict($request, $model)) {
            return $this->problem($request, 'REVISION_CONFLICT', 'The recipient has changed since it was loaded.', 409);
        }

        $this->fillRecipient($model, $request, true);
        $model->revision = ((int) ($model->revision ?: 1)) + 1;
        $model->save();

        return $this->success($request, (new RecipientResource($model->fresh()))->resolve($request));
    }

    public function destroy(Request $request, $recipient)
    {
        $model = $this->ownedRecipient($recipient);
        if (!$model) {
            return $this->problem($request, 'NOT_FOUND', 'Recipient not found.', 404);
        }

        if ($this->revisionConflict($request, $model)) {
            return $this->problem($request, 'REVISION_CONFLICT', 'The recipient has changed since it was loaded.', 409);
        }

        $model->is_archived = 1;
        $model->revision = ((int) ($model->revision ?: 1)) + 1;
        $model->save();

        return response()->noContent(204)->withHeaders([
            'X-Request-ID' => (string) $request->attributes->get('portal_request_id'),
        ]);
    }

    private function ownedRecipient($id)
    {
        $user = $this->customerContext->user();
        return Receiver::where('user_id', $user->id)
            ->where('is_archived', 0)
            ->where('id', $id)
            ->first();
    }

    private function validator(Request $request, $partial = false)
    {
        $required = $partial ? 'sometimes' : 'required';

        return Validator::make($request->all(), [
            'name' => [$required, 'string', 'max:255'],
            'countryCode' => ['sometimes', 'nullable', 'string', 'max:20'],
            'address' => [$required, 'string', 'max:1000'],
            'phone' => [$required, 'string', 'max:50'],
        ]);
    }

    private function fillRecipient($recipient, Request $request, $partial = false)
    {
        $map = [
            'name' => 'name',
            'countryCode' => 'country_code',
            'address' => 'reciver_address',
            'phone' => 'receiver_mobile',
        ];

        foreach ($map as $input => $column) {
            if (!$partial || $request->has($input)) {
                $recipient->{$column} = $request->input($input);
            }
        }
    }

    private function revisionConflict(Request $request, $model)
    {
        $expected = $request->header('If-Match');
        return $expected !== null && $expected !== '' && (int) trim($expected, '"') !== (int) ($model->revision ?: 1);
    }
}
