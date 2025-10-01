<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
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
        Blade::if('admin', function () {
            return auth()->check() && auth()->user()->can_create_leagues;
        });

        Blade::if('leagueAdmin', function ($league) {
            return auth()->check() && $league->admins->contains(auth()->id());
        });
    }
}
