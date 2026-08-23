<?php

namespace Modules\CustomerPortalApi\Providers;

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Modules\CustomerPortalApi\Http\Middleware\PortalContractMiddleware;
use Modules\CustomerPortalApi\Http\Middleware\PortalCsrfMiddleware;
use Modules\CustomerPortalApi\Http\Middleware\RequestIdMiddleware;
use Modules\CustomerPortalApi\Http\Controllers\Api\V1\BffSessionController;

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
            $this->mapBffRoutes();
            $this->mapApiRoutes();
        }
    }

    protected function mapBffRoutes()
    {
        Route::post('internal/bff/session-exchange', [BffSessionController::class, 'exchange'])
            ->middleware(['api', RequestIdMiddleware::class, PortalContractMiddleware::class]);
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
