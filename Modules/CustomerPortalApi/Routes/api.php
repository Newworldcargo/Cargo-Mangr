<?php

use Illuminate\Support\Facades\Route;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\AuthController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\HealthController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\ShipmentController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\SessionController;
use Modules\CustomerPortalApi\Http\Middleware\PortalAuthenticate;

Route::get('healthz', [HealthController::class, 'health']);
Route::get('readyz', [HealthController::class, 'ready']);

Route::post('auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:customer-portal');
Route::post('auth/register', [AuthController::class, 'register'])
    ->middleware('throttle:customer-portal');
Route::post('auth/verify', [AuthController::class, 'verify'])
    ->middleware('throttle:customer-portal');

Route::get('public/tracking/{trackingNumber}', [ShipmentController::class, 'publicTracking'])
    ->middleware('throttle:customer-portal-public-tracking')
    ->where('trackingNumber', '[A-Za-z0-9_-]+');

Route::middleware([PortalAuthenticate::class, 'throttle:customer-portal'])->group(function () {
    Route::get('session', [SessionController::class, 'show']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::get('shipments', [ShipmentController::class, 'index']);
    Route::get('shipments/{shipment}', [ShipmentController::class, 'show'])->whereNumber('shipment');
});
