<?php

namespace App\Providers;

use App\Listeners\SyncRolesOnLogin;
use App\Services\LanCore\LanCoreClient;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LanCoreClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Login::class, SyncRolesOnLogin::class);
    }
}
