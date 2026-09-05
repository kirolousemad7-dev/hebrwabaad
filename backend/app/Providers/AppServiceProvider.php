<?php

namespace App\Providers;

use App\Models\ManagedFile;
use App\Services\Payments\CardPaymentGateway;
use App\Services\Payments\PayTabsCheckoutGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CardPaymentGateway::class, PayTabsCheckoutGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::bind('file', function (string $value): ManagedFile {
            return ManagedFile::query()->findOrFail($value);
        });

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('hebr-login', function (Request $request) {
            return $this->perMinute(
                5,
                Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip()),
            );
        });

        RateLimiter::for('hebr-password', function (Request $request) {
            return $this->perMinute(
                5,
                Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip()),
            );
        });

        RateLimiter::for('hebr-register', function (Request $request) {
            return $this->perMinute(5, (string) $request->ip());
        });

        RateLimiter::for('hebr-consultations', function (Request $request) {
            return $this->perMinute(20, (string) $request->ip());
        });

        RateLimiter::for('hebr-messages', function (Request $request) {
            $userId = $request->user()?->id;

            return $this->perMinute(30, $userId !== null ? 'user:'.$userId : (string) $request->ip());
        });

        RateLimiter::for('hebr-uploads', function (Request $request) {
            $userId = $request->user()?->id;

            return $this->perMinute(15, $userId !== null ? 'user:'.$userId : (string) $request->ip());
        });

        RateLimiter::for('hebr-payments', function (Request $request) {
            $userId = $request->user()?->id;

            return $this->perMinute(10, $userId !== null ? 'user:'.$userId : (string) $request->ip());
        });
    }

    private function perMinute(int $maxAttempts, string $by): Limit
    {
        if (app()->runningUnitTests() && config('testing.force_rate_limits') !== true) {
            return Limit::none();
        }

        return Limit::perMinute($maxAttempts)->by($by);
    }
}
