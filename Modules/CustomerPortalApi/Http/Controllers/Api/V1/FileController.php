<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\CustomerPortalApi\Http\Resources\PortalFileResource;
use Modules\CustomerPortalApi\Models\PortalFile;

class FileController extends PortalController
{
    public function createIntent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fileName' => ['required', 'string', 'max:255'],
            'contentType' => ['required', 'string', 'max:150'],
            'sizeBytes' => ['required', 'integer', 'min:1', 'max:20971520'],
            'purpose' => ['required', 'in:shipment-evidence,support-attachment,proof-of-delivery,profile-photo'],
        ]);
        if ($validator->fails()) {
            return $this->problem($request, 'VALIDATION_FAILED', 'The file metadata is invalid.', 422, $validator->errors()->toArray());
        }

        $allowed = [
            'image/jpeg', 'image/png', 'image/webp', 'application/pdf',
        ];
        if (!in_array(strtolower($request->input('contentType')), $allowed, true)) {
            return $this->problem($request, 'UNSUPPORTED_MEDIA_TYPE', 'This file type is not supported.', 415);
        }
        if ($request->input('purpose') === 'profile-photo' && $request->input('sizeBytes') > 5242880) {
            return $this->problem($request, 'PAYLOAD_TOO_LARGE', 'Profile photos must be 5 MB or smaller.', 413);
        }

        $client = $this->customerContext->requireClient();
        $fileId = (string) Str::uuid();
        $key = 'customer-portal/' . $client->id . '/' . $fileId . '/' . basename($request->input('fileName'));
        $diskName = config('filesystems.portal_disk', config('filesystems.default', 'local'));
        $disk = Storage::disk($diskName);
        if (!method_exists($disk, 'temporaryUploadUrl')) {
            return $this->problem($request, 'DEPENDENCY_UNAVAILABLE', 'File upload storage is not configured for this environment.', 503, [], true);
        }

        $file = PortalFile::create([
            'file_id' => $fileId,
            'client_id' => $client->id,
            'purpose' => $request->input('purpose'),
            'storage_key' => $key,
            'original_name' => basename($request->input('fileName')),
            'content_type' => strtolower($request->input('contentType')),
            'size_bytes' => (int) $request->input('sizeBytes'),
            'status' => 'pending_upload',
            'expires_at' => now()->addMinutes(15),
            'revision' => 1,
        ]);

        try {
            $upload = $disk->temporaryUploadUrl($key, now()->addMinutes(15), [
                'ContentType' => $file->content_type,
            ]);
        } catch (\Throwable $exception) {
            $file->delete();
            return $this->problem($request, 'DEPENDENCY_UNAVAILABLE', 'File upload storage is temporarily unavailable.', 503, [], true);
        }

        return $this->success($request, [
            'fileId' => $fileId,
            'uploadUrl' => is_array($upload) ? ($upload['url'] ?? null) : $upload,
            'headers' => is_array($upload) ? ($upload['headers'] ?? []) : ['Content-Type' => $file->content_type],
            'expiresAt' => $file->expires_at->toIso8601String(),
        ], 201);
    }

    public function complete(Request $request, $fileId)
    {
        $file = PortalFile::where('file_id', $fileId)
            ->where('client_id', $this->customerContext->requireClient()->id)
            ->first();
        if (!$file) return $this->problem($request, 'NOT_FOUND', 'File not found.', 404);
        if ($file->expires_at->isPast()) return $this->problem($request, 'FILE_UPLOAD_EXPIRED', 'The upload intent has expired.', 409);

        $diskName = config('filesystems.portal_disk', config('filesystems.default', 'local'));
        $disk = Storage::disk($diskName);
        if (!$disk->exists($file->storage_key)) {
            return $this->problem($request, 'FILE_NOT_UPLOADED', 'The upload could not be found.', 422);
        }
        if ((int) $disk->size($file->storage_key) > $file->size_bytes) {
            $file->status = 'rejected';
            $file->save();
            return $this->problem($request, 'PAYLOAD_TOO_LARGE', 'The uploaded file exceeds the declared size.', 413);
        }

        $file->status = 'scan_pending';
        $file->revision = ((int) ($file->revision ?: 1)) + 1;
        $file->save();
        return $this->success($request, (new PortalFileResource($file))->resolve($request), 202);
    }
}
