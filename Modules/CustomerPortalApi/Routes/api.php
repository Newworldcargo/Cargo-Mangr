<?php

use Illuminate\Support\Facades\Route;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\AddressController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\AccountSettingsController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\AuthController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\HealthController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\InvoiceActionController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\InvoiceController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\InvoiceDocumentController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\MobileBookingQuoteController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\DraftQuoteController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\FileController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\NotificationController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\NotificationPreferenceController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\ReferenceDataController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\PaymentMethodController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\ShipmentController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\ProfileController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\PaymentController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\ShipmentActionController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\ShipmentDeliveryController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\PickupController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\ReturnController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\SavedPlaceController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\SupportController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\TelemetryController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\RecipientController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\SessionController;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\WalletController;
use Modules\CustomerPortalApi\Http\Middleware\PortalAuthenticate;
use Modules\CustomerPortalApi\Http\Middleware\PortalCsrfMiddleware;

Route::get('healthz', [HealthController::class, 'health']);
Route::get('readyz', [HealthController::class, 'ready']);
Route::get('reference-data', [ReferenceDataController::class, 'show'])
    ->middleware('throttle:customer-portal');
Route::get('auth/csrf', [AuthController::class, 'csrf'])
    ->middleware('throttle:customer-portal');
Route::post('telemetry/events', [TelemetryController::class, 'store'])
    ->middleware('throttle:customer-portal');

Route::post('auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:customer-portal');
Route::post('auth/register', [AuthController::class, 'register'])
    ->middleware('throttle:customer-portal');
Route::post('auth/password/forgot', [AuthController::class, 'requestPasswordReset'])
    ->middleware('throttle:customer-portal');
Route::post('auth/password/reset', [AuthController::class, 'resetPassword'])
    ->middleware('throttle:customer-portal');
Route::post('auth/verify', [AuthController::class, 'verify'])
    ->middleware([PortalCsrfMiddleware::class, 'throttle:customer-portal']);
Route::post('auth/verify/resend', [AuthController::class, 'resendVerification'])
    ->middleware([PortalCsrfMiddleware::class, 'throttle:customer-portal']);
Route::get('public/tracking/{trackingNumber}', [ShipmentController::class, 'publicTracking'])
    ->middleware('throttle:customer-portal-public-tracking')
    ->where('trackingNumber', '.*');

Route::middleware([PortalAuthenticate::class, 'throttle:customer-portal'])->group(function () {
    Route::get('session', [SessionController::class, 'show']);
    Route::post('auth/password/verify', [AuthController::class, 'verifyPassword']);
    Route::post('auth/password/change', [AuthController::class, 'changePassword']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('profile', [ProfileController::class, 'show']);
    Route::patch('profile', [ProfileController::class, 'update']);

    Route::get('account/settings', [AccountSettingsController::class, 'show']);
    Route::delete('account/devices/{device}', [AccountSettingsController::class, 'revokeDevice'])->whereNumber('device');
    Route::patch('account/devices/{device}/trust', [AccountSettingsController::class, 'trustDevice'])->whereNumber('device');
    Route::patch('account/marketing', [AccountSettingsController::class, 'marketing']);
    Route::post('account/data-export', [AccountSettingsController::class, 'requestDataExport']);
    Route::post('account/deletion-request', [AccountSettingsController::class, 'requestDeletion']);

    Route::get('addresses', [AddressController::class, 'index']);
    Route::post('addresses', [AddressController::class, 'store']);
    Route::get('addresses/{address}', [AddressController::class, 'show'])->whereNumber('address');
    Route::patch('addresses/{address}', [AddressController::class, 'update'])->whereNumber('address');
    Route::delete('addresses/{address}', [AddressController::class, 'destroy'])->whereNumber('address');

    Route::get('saved-places', [SavedPlaceController::class, 'index']);
    Route::post('saved-places', [SavedPlaceController::class, 'store']);
    Route::patch('saved-places/{place}', [SavedPlaceController::class, 'update'])->whereNumber('place');
    Route::delete('saved-places/{place}', [SavedPlaceController::class, 'destroy'])->whereNumber('place');

    Route::get('recipients', [RecipientController::class, 'index']);
    Route::post('recipients', [RecipientController::class, 'store']);
    Route::get('recipients/{recipient}', [RecipientController::class, 'show'])->whereNumber('recipient');
    Route::patch('recipients/{recipient}', [RecipientController::class, 'update'])->whereNumber('recipient');
    Route::delete('recipients/{recipient}', [RecipientController::class, 'destroy'])->whereNumber('recipient');

    Route::get('invoices', [InvoiceController::class, 'index']);
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->whereNumber('invoice');
    Route::get('invoices/{invoice}/receipt-document', [InvoiceDocumentController::class, 'receipt'])->whereNumber('invoice');
    Route::get('invoices/{invoice}/document', [InvoiceDocumentController::class, 'invoice'])->whereNumber('invoice');
    Route::patch('invoices/{invoice}/reminder', [InvoiceActionController::class, 'reminder'])->whereNumber('invoice');
    Route::post('invoices/{invoice}/disputes', [InvoiceActionController::class, 'dispute'])->whereNumber('invoice');
    Route::get('wallet', [WalletController::class, 'show']);
    Route::get('wallet/transactions', [WalletController::class, 'transactions']);
    Route::post('wallet/top-ups', [WalletController::class, 'topUp']);

    Route::post('files/upload-intents', [FileController::class, 'createIntent']);
    Route::put('files/{fileId}/content', [FileController::class, 'upload'])->whereUuid('fileId');
    Route::post('files/{fileId}/complete', [FileController::class, 'complete']);
    Route::get('files/{fileId}/download', [FileController::class, 'download'])->whereUuid('fileId');
    Route::post('payments/intents', [PaymentController::class, 'createIntent']);
    Route::get('payments/intents/{intent}', [PaymentController::class, 'showIntent']);
    Route::get('payment-methods', [PaymentMethodController::class, 'index']);
    Route::post('payment-methods', [PaymentMethodController::class, 'store']);
    Route::delete('payment-methods/{method}', [PaymentMethodController::class, 'destroy'])->whereNumber('method');
    Route::patch('payment-methods/{method}/default', [PaymentMethodController::class, 'makeDefault'])->whereNumber('method');

    Route::get('shipment-drafts', [DraftQuoteController::class, 'drafts']);
    Route::get('bookings', [ShipmentController::class, 'bookings']);
    Route::get('bookings/{booking}', [ShipmentController::class, 'booking'])->whereNumber('booking');
    Route::post('shipment-drafts', [DraftQuoteController::class, 'createDraft']);
    Route::get('shipment-drafts/{draft}', [DraftQuoteController::class, 'showDraft'])->whereNumber('draft');
    Route::put('shipment-drafts/{draft}', [DraftQuoteController::class, 'updateDraft'])->whereNumber('draft');
    Route::delete('shipment-drafts/{draft}', [DraftQuoteController::class, 'deleteDraft'])->whereNumber('draft');
    Route::post('shipment-drafts/{draft}/submit', [DraftQuoteController::class, 'submitDraft'])->whereNumber('draft');
    Route::post('quotes', [DraftQuoteController::class, 'createQuote']);
    Route::get('quotes/{quote}', [DraftQuoteController::class, 'showQuote'])->whereNumber('quote');
    Route::post('bookings/quote', [MobileBookingQuoteController::class, 'store']);

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'read']);
    Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
    Route::get('notifications/preferences', [NotificationPreferenceController::class, 'show']);
    Route::patch('notifications/preferences', [NotificationPreferenceController::class, 'update']);
    Route::post('notifications/push-token', [NotificationPreferenceController::class, 'registerToken']);
    Route::delete('notifications/push-token', [NotificationPreferenceController::class, 'revokeToken']);

    Route::get('support/cases', [SupportController::class, 'index']);
    Route::post('support/cases', [SupportController::class, 'store']);
    Route::post('support/cases/{case}/evidence', [SupportController::class, 'attachEvidence'])->whereNumber('case');
    Route::get('support/cases/{case}', [SupportController::class, 'show'])->whereNumber('case');

    Route::get('returns', [ReturnController::class, 'index']);
    Route::post('returns', [ReturnController::class, 'store']);
    Route::get('returns/{return}', [ReturnController::class, 'show'])->whereNumber('return');
    Route::post('returns/{return}/cancel', [ReturnController::class, 'cancel'])->whereNumber('return');

    Route::get('pickups/current', [PickupController::class, 'current']);
    Route::post('pickups', [PickupController::class, 'store']);
    Route::post('pickups/{pickup}/cancel', [PickupController::class, 'cancel'])->whereNumber('pickup');
    Route::patch('pickups/by-shipment/{shipment}/reschedule', [PickupController::class, 'rescheduleByShipment'])->whereNumber('shipment');
    Route::patch('pickups/by-shipment/{shipment}/cancel', [PickupController::class, 'cancelByShipment'])->whereNumber('shipment');
    Route::patch('pickups/by-shipment/{shipment}/help', [PickupController::class, 'helpByShipment'])->whereNumber('shipment');
    Route::patch('pickups/by-shipment/{shipment}/restore', [PickupController::class, 'restoreByShipment'])->whereNumber('shipment');

    Route::get('shipments', [ShipmentController::class, 'index']);
    Route::get('shipments/{shipment}', [ShipmentController::class, 'show'])->whereNumber('shipment');
    Route::post('shipments/{shipment}/actions', [ShipmentActionController::class, 'store'])->whereNumber('shipment');
    Route::get('shipments/{shipment}/delivery', [ShipmentDeliveryController::class, 'show'])->whereNumber('shipment');
    Route::patch('shipments/{shipment}/delivery', [ShipmentDeliveryController::class, 'update'])->whereNumber('shipment');
    Route::get('shipments/{shipment}/proof-of-delivery', [ShipmentDeliveryController::class, 'proofOfDelivery'])->whereNumber('shipment');
    Route::get('shipments/{shipment}/proof-of-delivery-document', [ShipmentDeliveryController::class, 'proofOfDeliveryDocument'])->whereNumber('shipment');
});
