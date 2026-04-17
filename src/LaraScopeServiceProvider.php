<?php

namespace AmjadAH\LaraScope;

use AmjadAH\LaraScope\Console\Commands\PruneLogsCommand;
use AmjadAH\LaraScope\Http\Middleware\LaraScopeMiddleware;
use AmjadAH\LaraScope\Services\RequestLogger;
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

        if (config('larascope.enabled', true)) {
            $middlewareGroups = config('larascope.middleware_groups', ['web', 'api']);

            foreach ($middlewareGroups as $middlewareGroup) {
                $router->pushMiddlewareToGroup($middlewareGroup, LaraScopeMiddleware::class);
            }
        }
    }
}
