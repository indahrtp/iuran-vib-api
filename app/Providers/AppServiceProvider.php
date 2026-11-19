<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Auth\Notifications\ResetPassword;

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
        Model::shouldBeStrict(! $this->app->environment('production'));

        RateLimiter::for('login', function (Request $request) {
            $key = Str::lower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(5)->by($key)->response(function (Request $request, array $headers) {
                $seconds = $headers['Retry-After'] ?? 60;

                return response()->json([
                    'message' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
                ], 429, $headers);
            });
        });

        ResetPassword::createUrlUsing(function ($user, string $token) {
            $frontend = rtrim(explode(',', env('FRONTEND_URL', 'http://localhost:5173'))[0], '/');

            return "{$frontend}/reset-password?token={$token}&email=".urlencode($user->getEmailForPasswordReset());
        });
    }
}
