<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

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
        Gate::define('viewVantage', function ($user = null) {
            // Only allow admins
            return optional($user)->isAdmin();

            // Or any other custom logic
            // return $user && $user->hasRole('developer');
        });
    }
}
