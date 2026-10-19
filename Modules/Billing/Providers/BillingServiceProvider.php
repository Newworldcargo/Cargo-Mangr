<?php

namespace Modules\Billing\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Billing\Services\InvoiceLedger;

class BillingServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'billing');
        $this->app->register(RouteServiceProvider::class);
        $this->app->bind(InvoiceLedger::class, function ($app) {
            return new InvoiceLedger($app['db']->connection());
        });
    }

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'billing');
        $menu = view('billing::components.aside_menu');
        $menu['order'] = 11.5;
        app('hook')->set('aside_menu', $menu, 'array');
    }
}
