<?php

namespace App\Providers;

use App\Contracts\SmsSender;
use App\Models\CalendarItem;
use App\Models\CommercialQuotation;
use App\Models\ContentMedia;
use App\Models\CrmCompany;
use App\Models\ManagedFile;
use App\Models\Meeting;
use App\Models\Payment;
use App\Models\PortfolioItem;
use App\Models\Project;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\SupplierPortfolioItem;
use App\Models\SupplierProduct;
use App\Models\SupplierProfileVersion;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkSubmission;
use App\Services\Customer\CustomerCommunicationService;
use App\Services\Delivery\DeliveryProviderManager;
use App\Services\Notifications\NotificationChannelManager;
use App\Services\Payments\CardPaymentGateway;
use App\Services\Payments\PayTabsCheckoutGateway;
use App\Services\Sms\SmsManager;
use App\Support\Calendar\CalendarOccurrenceReference;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CardPaymentGateway::class, PayTabsCheckoutGateway::class);
        $this->app->singleton(NotificationChannelManager::class);
        $this->app->singleton(DeliveryProviderManager::class);
        $this->app->singleton(SmsManager::class);
        $this->app->bind(SmsSender::class, fn ($app) => $app->make(SmsManager::class)->driver());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'work_submission' => WorkSubmission::class,
            'supplier' => Supplier::class,
            'supplier_portfolio_item' => SupplierPortfolioItem::class,
            'supplier_product' => SupplierProduct::class,
            'supplier_profile_version' => SupplierProfileVersion::class,
            'content_media' => ContentMedia::class,
            'customer' => User::class,
            'company' => CrmCompany::class,
            'product' => SupplierProduct::class,
            'service' => Service::class,
            'portfolio' => PortfolioItem::class,
            'project' => Project::class,
            'task' => Task::class,
            'quotation' => CommercialQuotation::class,
            'invoice' => Payment::class,
            'meeting' => Meeting::class,
        ]);

        Route::bind('file', function (string $value): ManagedFile {
            return ManagedFile::query()->findOrFail($value);
        });

        Route::bind('calendarItem', function (string $value) {
            try {
                $ref = CalendarOccurrenceReference::parse($value);
            } catch (\InvalidArgumentException) {
                throw new NotFoundHttpException;
            }

            $item = CalendarItem::query()->findOrFail($ref->itemId());

            if ($ref->isVirtual()) {
                $item->resolved_occurrence_at = $ref->occurrenceAt()?->toIso8601String();
            }

            return $item;
        });

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        $this->configureRateLimiting();
        $this->registerCapabilityAwareNotificationChannels();
    }

    private function registerCapabilityAwareNotificationChannels(): void
    {
        $this->app->booted(function (): void {
            $channels = $this->app->make(NotificationChannelManager::class);
            $communications = $this->app->make(CustomerCommunicationService::class);

            if ($communications->mailEnabled() && ! $channels->has('mail')) {
                $channels->register('mail');
            }
        });
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

        RateLimiter::for('hebr-supplier-otp', function (Request $request) {
            return $this->perMinute(
                5,
                Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip()),
            );
        });

        RateLimiter::for('hebr-email-verify', function (Request $request) {
            return $this->perMinute(20, (string) $request->ip());
        });

        RateLimiter::for('hebr-email-verify-resend', function (Request $request) {
            $userId = $request->user()?->id;

            return $this->perMinute(3, $userId !== null ? 'user:'.$userId : (string) $request->ip());
        });

        RateLimiter::for('hebr-phone-otp', function (Request $request) {
            $userId = $request->user()?->id;
            $key = $userId !== null ? 'user:'.$userId : (string) $request->ip();

            if (app()->runningUnitTests() && config('testing.force_rate_limits') !== true) {
                return Limit::none();
            }

            return [
                Limit::perMinute(3)->by('phone-otp-min:'.$key),
                Limit::perHour(10)->by('phone-otp-hour:'.$key),
            ];
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

        RateLimiter::for('hebr-contact', function (Request $request) {
            return $this->perMinute(8, (string) $request->ip());
        });

        RateLimiter::for('hebr-public-quote', function (Request $request) {
            $token = (string) $request->route('token', '');
            $hint = $token !== '' ? substr($token, -8) : 'none';

            return $this->perMinute(20, $hint.'|'.$request->ip());
        });

        RateLimiter::for('hebr-public-track', function (Request $request) {
            $token = (string) $request->route('token', '');
            $hint = $token !== '' ? substr($token, -8) : 'none';

            return $this->perMinute(30, 'track:'.$hint.'|'.$request->ip());
        });

        RateLimiter::for('hebr-inbound-webhooks', function (Request $request) {
            $integrationId = (string) $request->route('inboundWebhook', 'unknown');

            return $this->perMinute(60, 'inbound:'.$integrationId.'|'.$request->ip());
        });

        RateLimiter::for('hebr-quote-email', function (Request $request) {
            $userId = $request->user()?->id;
            $quoteId = (string) $request->route('printing_quotation', 'none');

            return $this->perMinute(10, 'quote-email:'.$quoteId.'|'.($userId !== null ? 'user:'.$userId : $request->ip()));
        });

        RateLimiter::for('hebr-public-portal', function (Request $request) {
            $token = (string) $request->route('token', '');
            $hint = $token !== '' ? substr($token, -8) : 'none';

            return $this->perMinute(30, 'portal:'.$hint.'|'.$request->ip());
        });

        RateLimiter::for('hebr-portal-magic-link', function (Request $request) {
            $email = Str::transliterate(Str::lower((string) $request->input('email')));

            return [
                Limit::perMinute(3)->by('magic-min:'.$email.'|'.$request->ip()),
                Limit::perHour(5)->by('magic-hour:'.$email.'|'.$request->ip()),
            ];
        });

        RateLimiter::for('hebr-portal-resend', function (Request $request) {
            $userId = $request->user()?->id;
            $customerId = (string) $request->route('user', 'none');

            return $this->perMinute(5, 'portal-resend:'.$customerId.'|'.($userId !== null ? 'user:'.$userId : $request->ip()));
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
