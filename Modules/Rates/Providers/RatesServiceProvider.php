<?php

namespace Modules\Rates\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;

class RatesServiceProvider extends ServiceProvider
{
    /**
     * Boot the application events.
     */
    public function boot()
    {
        $this->registerTranslations();
        $this->registerViews();
        $this->registerConfig();
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->app->register(RouteServiceProvider::class);

        // The command lives inside this module, so register it here.
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Rates\Console\Commands\SyncExchangeRatesCommand::class,
            ]);
        }
    }

    protected function registerTranslations()
    {
        $langPath = resource_path('lang/modules/rates');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'rates');
            $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'rates');
        } else {
            $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'rates');
        }
    }

    protected function registerViews()
    {
        $viewPath = resource_path('views/modules/rates');
        $sourcePath = __DIR__ . '/../Resources/views';

        $this->publishes([$sourcePath => $viewPath], 'rates-views');
        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), 'rates');
    }

    protected function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (\Config::get('view.paths') as $path) {
            $paths[] = $path . '/modules/rates';
        }
        return $paths;
    }

    protected function registerConfig()
    {
        $this->publishes([__DIR__ . '/../Config/config.php' => config_path('rates.php')], 'rates-config');
        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'rates');
    }
}
