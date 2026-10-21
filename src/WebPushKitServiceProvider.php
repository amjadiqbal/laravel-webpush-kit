<?php

declare(strict_types=1);

namespace AmjadIqbal\WebPushKit;

use AmjadIqbal\WebPushKit\Console\PushTestCommand;
use AmjadIqbal\WebPushKit\Http\AssetController;
use AmjadIqbal\WebPushKit\Http\SubscriptionController;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class WebPushKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webpush-kit.php', 'webpush-kit');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webpush-kit');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webpush-kit');
        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/webpush-kit.php' => config_path('webpush-kit.php')], 'webpush-kit-config');
            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/webpush-kit')], 'webpush-kit-views');
            $this->publishes([__DIR__.'/../lang' => $this->app->langPath('vendor/webpush-kit')], 'webpush-kit-translations');
            $this->publishes([
                __DIR__.'/../resources/js/webpush-sw.js' => public_path(ltrim((string) config('webpush-kit.service_worker.path', 'webpush-sw.js'), '/')),
            ], 'webpush-kit-service-worker');
            $this->commands([PushTestCommand::class]);
        }

        AboutCommand::add('WebPush Kit', fn (): array => [
            'Version' => WebPushKit::version(),
            'VAPID keys' => WebPushKit::vapidConfigured() ? 'set' : '<fg=yellow;options=bold>missing (php artisan webpush:vapid)</>',
            'VAPID subject' => WebPushKit::subjectProblems() === [] ? 'ok' : '<fg=yellow;options=bold>check (php artisan push:test)</>',
            'Service worker' => '/'.ltrim((string) config('webpush-kit.service_worker.path', 'webpush-sw.js'), '/'),
            'Support' => WebPushKit::SUPPORT_URL,
        ]);
    }

    private function registerRoutes(): void
    {
        $prefix = trim((string) config('webpush-kit.routes.prefix', 'webpush'), '/');
        $domain = config('webpush-kit.routes.domain');
        $middleware = array_values(array_filter((array) config('webpush-kit.routes.middleware', ['web', 'auth']), 'is_string'));

        Route::group(['prefix' => $prefix, 'domain' => $domain, 'middleware' => $middleware], function (): void {
            Route::post('subscriptions', [SubscriptionController::class, 'store'])->name('webpush-kit.subscriptions.store');
            Route::delete('subscriptions', [SubscriptionController::class, 'destroy'])->name('webpush-kit.subscriptions.destroy');
            // The service worker has no CSRF token; the controller only accepts same-origin requests.
            Route::post('subscriptions/rotate', [SubscriptionController::class, 'rotate'])
                ->withoutMiddleware([ValidateCsrfToken::class, 'App\Http\Middleware\VerifyCsrfToken'])
                ->name('webpush-kit.subscriptions.rotate');
        });

        // Static assets: no session, no auth.
        Route::group(['prefix' => $prefix, 'domain' => $domain], function (): void {
            Route::get('webpush-kit.js', [AssetController::class, 'script'])->name('webpush-kit.script');
        });

        Route::group(['domain' => $domain], function (): void {
            Route::get(ltrim((string) config('webpush-kit.service_worker.path', 'webpush-sw.js'), '/'), [AssetController::class, 'serviceWorker'])
                ->name('webpush-kit.service-worker');

            Route::get('manifest.webmanifest', [AssetController::class, 'manifest'])->name('webpush-kit.manifest');
        });
    }
}
