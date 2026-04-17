<?php

use AmjadAH\LaraScope\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

$dashboardPath       = config('larascope.dashboard.path', 'larascope');
$dashboardMiddleware = config('larascope.dashboard.middleware', ['web']);

Route::middleware($dashboardMiddleware)
    ->prefix($dashboardPath)
    ->name('larascope.')
    ->group(function (): void {
        Route::get('/', [DashboardController::class, 'index'])->name('index');
        Route::get('/{requestLog}', [DashboardController::class, 'show'])->name('show');
    });
