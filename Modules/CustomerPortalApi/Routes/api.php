<?php

use Illuminate\Support\Facades\Route;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\AddressController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\AuthController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\HealthController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\ShipmentController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\ProfileController;
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
    Route::get('profile', [ProfileController::class, 'show']);
    Route::patch('profile', [ProfileController::class, 'update']);

    Route::get('addresses', [AddressController::class, 'index']);
    Route::post('addresses', [AddressController::class, 'store']);
    Route::get('addresses/{address}', [AddressController::class, 'show'])->whereNumber('address');
    Route::patch('addresses/{address}', [AddressController::class, 'update'])->whereNumber('address');
    Route::delete('addresses/{address}', [AddressController::class, 'destroy'])->whereNumber('address');

    Route::get('shipments', [ShipmentController::class, 'index']);
    Route::get('shipments/{shipment}', [ShipmentController::class, 'show'])->whereNumber('shipment');
});
