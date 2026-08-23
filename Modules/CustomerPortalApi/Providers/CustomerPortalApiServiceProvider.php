<?php

namespace Modules\CustomerPortalApi\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class CustomerPortalApiServiceProvider extends ServiceProvider
{
    protected $moduleName = 'CustomerPortalApi';
    protected $moduleNameLower = 'customerportalapi';

    public function boot()
    {
        $this->registerConfig();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        RateLimiter::for('customer-portal', function (Request $request) {
            return Limit::perMinute((int) config('customerportalapi.api_rate_limit', 60))
                ->by(optional($request->user('web'))->id ?: $request->ip());
        });

        RateLimiter::for('customer-portal-public-tracking', function (Request $request) {
            return Limit::perMinute((int) config('customerportalapi.public_tracking_per_minute', 30))
                ->by($request->ip());
        });
    }

    public function register()
    {
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerConfig()
    {
        $configPath = module_path($this->moduleName, 'Config/config.php');

        $this->publishes([
            $configPath => config_path($this->moduleNameLower . '.php'),
        ], 'config');

        $this->mergeConfigFrom($configPath, $this->moduleNameLower);
    }

    public function provides()
    {
        return [];
    }
}
