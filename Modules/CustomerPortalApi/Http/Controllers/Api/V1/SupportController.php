<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\CustomerPortalApi\Http\Resources\SupportCaseResource;
use Modules\CustomerPortalApi\Models\PortalFile;
use Modules\Cargo\Entities\Support as SupportCase;

class SupportController extends PortalController
{
    public function index(Request $request)
    {
        $user = $this->customerContext->user();
        $cases = SupportCase::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return $this->success($request, $cases->map(function ($case) use ($request) {
            return (new SupportCaseResource($case))->resolve($request);
        })->values()->all());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category' => ['required', 'string', 'max:80'],
            'subject' => ['required', 'string', 'max:255'],
            'detail' => ['required', 'string', 'max:10000'],
            'shipmentNumber' => ['sometimes', 'nullable', 'string', 'max:255'],
            'attachmentFileId' => ['sometimes', 'nullable', 'uuid'],
        ]);

        if ($validator->fails()) {
            return $this->problem($request, 'VALIDATION_FAILED', 'Please correct the highlighted fields.', 422, $validator->errors()->toArray());
        }

        $user = $this->customerContext->user();
        $attachmentFileId = $request->input('attachmentFileId');
        if ($attachmentFileId) {
            $attachment = PortalFile::where('file_id', $attachmentFileId)
                ->where('client_id', $this->customerContext->requireClient()->id)
                ->where('purpose', 'support-attachment')
                ->where('status', 'scan_pending')
                ->first();

            if (!$attachment) {
                return $this->problem($request, 'VALIDATION_FAILED', 'Please correct the highlighted fields.', 422, [
                    'attachmentFileId' => ['The selected attachment is unavailable.'],
                ]);
            }
        }

        $case = new SupportCase();
        $case->user_id = $user->id;
        $case->category = $request->input('category');
        $case->subject = $request->input('subject');
        $case->priority = 'normal';
        $case->message = $request->input('detail');
        $case->shipment_number = $request->input('shipmentNumber');
        $case->status = 'open';
        $case->attachments = $attachmentFileId ? [$attachmentFileId] : null;
        $case->save();

        return $this->success($request, (new SupportCaseResource($case))->resolve($request), 201);
    }

    public function show(Request $request, $case)
    {
        $model = SupportCase::where('user_id', $this->customerContext->user()->id)
            ->whereKey($case)
            ->first();
        if (!$model) {
            return $this->problem($request, 'NOT_FOUND', 'Support case not found.', 404);
        }

        return $this->success($request, (new SupportCaseResource($model))->resolve($request));
    }
}
