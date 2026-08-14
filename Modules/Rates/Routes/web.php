<?php

/*
|--------------------------------------------------------------------------
| Rates Module Web Routes
|--------------------------------------------------------------------------
|
| These routes hook into the existing currency settings page: the manual
| "Refresh" flow keeps working as before, and the module additionally
| exposes the current sync status used by the exchange-rate component.
|
*/

Route::group([
    'middleware' => ['auth'],
    'prefix' => env('PREFIX_ADMIN', 'admin'),
], function () {

    // Manual one-click refresh of the system rate from the API sources
    // (same behaviour as the browser-side Refresh button, but server-side
    // so it is not bound by browser localStorage cache or API rate limits).
    Route::post('/rates/refresh', 'RatesController@refresh')
        ->name('rates.refresh')
        ->middleware('can:edit-exchange-rates');

    // Sync status for the currency settings widget.
    Route::get('/rates/status', 'RatesController@status')
        ->name('rates.status');
});
