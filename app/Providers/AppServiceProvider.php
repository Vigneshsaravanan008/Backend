<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passport::enablePasswordGrant();

        RateLimiter::for('global', function (Request $request) {
            return Limit::perMinute(5)->response(function (Request $request, array $headers) {
                return response()->json(['status'=>429,"message"=>"Too Many Attempts,Please wait and try again after 1 Minute"]);
            });
        });
    }
}
