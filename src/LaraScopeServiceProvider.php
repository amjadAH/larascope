<?php

namespace Amjad\LaraScope;

use Amjad\LaraScope\Console\Commands\PruneLogsCommand;
use Amjad\LaraScope\Http\Middleware\LaraScopeMiddleware;
use Amjad\LaraScope\Services\RequestLogger;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class LaraScopeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/larascope.php', 'larascope');

        // Bind as singletons so the same instance is used for both handle()
        // and terminate() — Laravel resolves a fresh instance for terminate()
        // unless the middleware is bound as a singleton.
        $this->app->singleton(RequestLogger::class);
        $this->app->singleton(LaraScopeMiddleware::class);
    }

    public function boot(): void
    {
        $this->publishAssets();
        $this->loadAssets();
        $this->registerMiddleware();

        if (config('larascope.dashboard.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([PruneLogsCommand::class]);
        }
    }

    private function publishAssets(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/larascope.php' => config_path('larascope.php'),
        ], 'larascope-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'larascope-migrations');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/larascope'),
        ], 'larascope-views');
    }

    private function loadAssets(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'larascope');
    }

    private function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('larascope', LaraScopeMiddleware::class);

        if (!config('larascope.enabled', true)) {
            return;
        }

        $middlewareGroups = config('larascope.middleware_groups', ['web', 'api']);
        $httpKernel       = $this->resolveHttpKernel();

        foreach ($middlewareGroups as $middlewareGroup) {
            // Prefer registering through the HTTP kernel: it owns the canonical
            // group list and re-syncs it onto the router — replacing each group
            // wholesale — whenever any package touches kernel middleware (Laravel
            // Sanctum does exactly that from its boot()). A middleware pushed
            // straight onto the router is silently dropped by that sync.
            if ($httpKernel instanceof HttpKernel) {
                $httpKernel->appendMiddlewareToGroup($middlewareGroup, LaraScopeMiddleware::class);

                continue;
            }

            $router->pushMiddlewareToGroup($middlewareGroup, LaraScopeMiddleware::class);
        }
    }

    /**
     * The HTTP kernel, or null when the application has no kernel to register
     * middleware with (a console-only or non-standard container).
     */
    private function resolveHttpKernel(): ?HttpKernel
    {
        if (!$this->app->bound(HttpKernelContract::class)) {
            return null;
        }

        $httpKernel = $this->app->make(HttpKernelContract::class);

        return $httpKernel instanceof HttpKernel ? $httpKernel : null;
    }
}
