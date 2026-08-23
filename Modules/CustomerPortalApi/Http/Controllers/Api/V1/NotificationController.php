<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Modules\CustomerPortalApi\Http\Resources\NotificationResource;

class NotificationController extends PortalController
{
    public function index(Request $request)
    {
        $user = $this->customerContext->user();
        $notifications = $user->notifications()->orderByDesc('created_at')->limit(100)->get();

        return $this->success($request, $notifications->map(function ($notification) use ($request) {
            return (new NotificationResource($notification))->resolve($request);
        })->values()->all());
    }

    public function read(Request $request, $notification)
    {
        $model = $this->ownedNotification($notification);
        if (!$model) {
            return $this->problem($request, 'NOT_FOUND', 'Notification not found.', 404);
        }

        if ($model->read_at === null) {
            $model->read_at = now();
            $model->save();
        }

        return $this->success($request, (new NotificationResource($model->fresh()))->resolve($request));
    }

    public function readAll(Request $request)
    {
        $user = $this->customerContext->user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent(204)->withHeaders([
            'X-Request-ID' => (string) $request->attributes->get('portal_request_id'),
        ]);
    }

    private function ownedNotification($id)
    {
        return $this->customerContext->user()->notifications()
            ->where('id', $id)
            ->first();
    }
}
