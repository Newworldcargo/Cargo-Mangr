<?php

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\SalesController;
use Modules\Billing\Http\Controllers\DocumentController;

Route::get('quotations', [SalesController::class, 'quotations'])->name('quotations.index');
Route::get('invoices', [SalesController::class, 'invoices'])->name('invoices.index');
Route::get('customers', [DocumentController::class, 'customers'])->name('customers');
Route::post('customers', [DocumentController::class, 'createCustomer'])->name('customers.store');
Route::get('shipments', [DocumentController::class, 'shipments'])->name('shipments');
Route::get('invoices/{id}/receipts/{payment}', [DocumentController::class, 'receipt'])->whereNumber('id')->whereNumber('payment')->name('documents.receipt');
Route::group(['where' => ['type' => 'invoices|quotations', 'id' => '[0-9]+']], function () {
    Route::get('{type}/create', [DocumentController::class, 'create'])->name('documents.create');
    Route::post('{type}', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('{type}/{id}/edit', [DocumentController::class, 'edit'])->name('documents.edit');
    Route::put('{type}/{id}', [DocumentController::class, 'store'])->name('documents.update');
    Route::get('{type}/{id}/print', [DocumentController::class, 'print'])->name('documents.print');
    Route::get('{type}/{id}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::post('{type}/{id}/delivery', [DocumentController::class, 'delivery'])->name('documents.delivery');
    Route::post('{type}/{id}/duplicate', [DocumentController::class, 'duplicate'])->name('documents.duplicate');
    Route::post('{type}/{id}/deliveries/{delivery}/cancel', [DocumentController::class, 'cancelDelivery'])->whereNumber('delivery')->name('documents.delivery.cancel');
    Route::get('{type}/{id}', [DocumentController::class, 'show'])->name('documents.show');
    Route::post('{type}/{id}/{action}', [DocumentController::class, 'action'])->name('documents.action');
});
