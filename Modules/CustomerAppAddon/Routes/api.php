<?php

use Illuminate\Http\Request;
use Modules\Cargo\Http\Controllers\Api\ShipmentController as ApiShipmentController;
use Modules\Cargo\Http\Controllers\ShipmentController as ShipmentController;
use Modules\Cargo\Http\Controllers\ClientController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Shipments Apis Routes
Route::post('admin/shipments/create', [ShipmentController::class, 'storeAPI']);
Route::get('shipments', [ShipmentController::class, 'getShipmentsAPI']);
Route::get('online-bookings', [ShipmentController::class, 'getOnlineBookingsAPI']);
Route::get('shipment-by-barcode', [ShipmentController::class, 'ajaxGetShipmentByBarcode']);

Route::get('ConfirmationTypeMission', [ApiShipmentController::class, 'getConfirmationTypeMission']);
Route::get('shipmentPackages', [ApiShipmentController::class, 'getShipmentPackages']);
Route::get('shipment-tracking', [ApiShipmentController::class, 'tracking']);
Route::get('shipment-setting', [ApiShipmentController::class, 'getSetting']);

// Missions Apis Routes
Route::post('createMission', [ShipmentController::class, 'createMissionAPI']);

// General Apis Routes
Route::get('packages', [ApiShipmentController::class, 'ajaxGetPackages']);
Route::get('DeliveryTimes', [ApiShipmentController::class, 'ajaxGetDeliveryTimes']);
Route::get('countries', [ApiShipmentController::class, 'countriesApi']);
Route::get('states', [ApiShipmentController::class, 'ajaxGetStates']);
Route::get('areas', [ApiShipmentController::class, 'ajaxGetAreas']);
Route::get('payment-types', [ApiShipmentController::class, 'getPaymentTypes']);

// Address Apis Routes
Route::post('addAddress', [ClientController::class, 'addNewAddressAPI']);
Route::get('getAddresses', [ClientController::class, 'getAddresses']);
