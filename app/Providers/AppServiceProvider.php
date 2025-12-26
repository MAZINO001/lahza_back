<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
use App\Services\PaymentServiceInterface;
use App\Services\PaymentService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
            $this->app->bind(PaymentServiceInterface::class, PaymentService::class);

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            $frontend = config('app.frontend_url') ?? env('FRONTEND_URL') ?? config('app.url');
            return rtrim($frontend, '/') . "/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });
    }
}
