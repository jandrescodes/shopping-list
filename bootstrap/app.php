<?php

use App\Http\Middleware\Hsts;
use App\Http\Middleware\NoIndex;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'noindex' => NoIndex::class,
        ]);

        $middleware->append(Hsts::class);
    })
    ->booted(function (): void {
        // Per-IP request limits (constitution 5). Generous ceilings for
        // real family use; only a defensive cap against abuse. The browser
        // suite drives a long-lived `php artisan serve` whose limiter state
        // accumulates across the whole run, so it opts out with E2E_RELAXED_LIMITS
        // (set in playwright.config.js); the Pest suite keeps the real limits.
        $relaxed = (bool) env('E2E_RELAXED_LIMITS', false);

        RateLimiter::for('lists-create', fn (Request $request) => Limit::perHour($relaxed ? 10000 : 10)->by($request->ip()));
        RateLimiter::for('writes', fn (Request $request) => Limit::perMinute($relaxed ? 100000 : 120)->by($request->ip()));
        RateLimiter::for('sync', fn (Request $request) => Limit::perMinute($relaxed ? 100000 : 60)->by($request->ip()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A missing list — deleted or never created — and any unknown API
        // route answer with one identical generic 404, leaking no detail about
        // whether the list ever existed.
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Not Found'], 404);
            }

            return null;
        });
    })->create();
