<?php

namespace Amjad\LaraScope\Tests\Feature;

use Amjad\LaraScope\Http\Middleware\LaraScopeMiddleware;
use Amjad\LaraScope\Tests\TestCase;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;

class MiddlewareRegistrationTest extends TestCase
{
    /** @return array<int, mixed> */
    private function middlewareGroup(string $group): array
    {
        return $this->app->make(Router::class)->getMiddlewareGroups()[$group] ?? [];
    }

    public function test_middleware_is_registered_in_the_configured_groups(): void
    {
        $this->assertContains(LaraScopeMiddleware::class, $this->middlewareGroup('web'));
        $this->assertContains(LaraScopeMiddleware::class, $this->middlewareGroup('api'));
    }

    public function test_middleware_survives_another_package_touching_kernel_middleware(): void
    {
        // Packages such as Laravel Sanctum call a kernel middleware mutator from
        // their own boot(). Every one of those calls re-syncs the kernel's group
        // list onto the router, replacing each group wholesale — which drops any
        // middleware that was only ever pushed onto the router.
        $this->app->make(HttpKernelContract::class)
            ->prependToMiddlewarePriority(SubstituteBindings::class);

        $this->assertContains(LaraScopeMiddleware::class, $this->middlewareGroup('web'));
        $this->assertContains(LaraScopeMiddleware::class, $this->middlewareGroup('api'));
    }
}
