<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        // ✅ Registrar alias de middleware (Laravel 12)
        app('router')->aliasMiddleware('agent.sign', \App\Http\Middleware\VerifyAgentSignature::class);
        app('router')->aliasMiddleware('admin.key', \App\Http\Middleware\VerifyAdminKey::class);
    }
}
