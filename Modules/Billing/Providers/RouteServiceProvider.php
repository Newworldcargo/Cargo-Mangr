<?php

namespace Modules\Billing\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public function map()
    {
        Route::middleware(['web', 'auth'])
            ->prefix(env('PREFIX_ADMIN', 'admin') . '/sales')
            ->name('billing.')
            ->group(__DIR__ . '/../Routes/web.php');
    }
}
