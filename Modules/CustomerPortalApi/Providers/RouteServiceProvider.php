<?php

namespace Modules\CustomerPortalApi\Providers;

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Modules\CustomerPortalApi\Http\Middleware\PortalContractMiddleware;
use Modules\CustomerPortalApi\Http\Middleware\PortalCsrfMiddleware;
use Modules\CustomerPortalApi\Http\Middleware\RequestIdMiddleware;

class RouteServiceProvider extends ServiceProvider
{
    protected $moduleNamespace = 'Modules\\CustomerPortalApi\\Http\\Controllers';

    public function boot()
    {
        parent::boot();
    }

    public function map()
    {
        if (env('INSTALLATION', false) === true) {
            $this->mapApiRoutes();
        }
    }

    protected function mapApiRoutes()
    {
        Route::prefix('api/v1')
            ->middleware([
                'web',
                'api',
                RequestIdMiddleware::class,
                PortalContractMiddleware::class,
                PortalCsrfMiddleware::class,
            ])
            ->withoutMiddleware(VerifyCsrfToken::class)
            ->namespace($this->moduleNamespace)
            ->group(module_path('CustomerPortalApi', '/Routes/api.php'));
    }
}
