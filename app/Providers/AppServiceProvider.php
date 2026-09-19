<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Sanctum 4.x (Laravel 11+) no longer auto-loads its package
        // migration at all, so the ignoreMigrations() call previously here
        // (needed to avoid colliding with this app's own generated copy of
        // that migration) is now a no-op and was removed from the package.
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Named limiters, so each has its OWN counter per client. (Unnamed
        // `throttle:10,1` middleware shares one bucket per IP with the API
        // group's own limiter and every other unnamed throttle — ordinary
        // browsing would then use up a customer's order-submission allowance.)
        RateLimiter::for('order-submit', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
        RateLimiter::for('order-tracking', function (Request $request) {
            return Limit::perMinute(15)->by($request->ip());
        });
        // Public product reviews (guests review through the order-tracking flow).
        RateLimiter::for('review-submit', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });
    }
}
