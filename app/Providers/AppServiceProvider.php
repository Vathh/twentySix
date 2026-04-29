<?php

namespace App\Providers;

use App\Models\League;
use App\Policies\LeaguePolicy;
use App\Services\Friends\FriendshipService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
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
        View::composer('layouts.app', function ($view) {
            $friends = collect();
            if (Auth::check()) {
                $friends = app(FriendshipService::class)->getFriends(Auth::id());
            }
            $view->with('friends', $friends);
        });

        Blade::if('canCreateLeagues', function () {
            return auth()->check() && auth()->user()->can_create_leagues;
        });

        Blade::if('leagueAdmin', function ($league) {
            return auth()->check() && in_array(auth()->id(), array_column($league->admins, 'id'));
        });

        Blade::if('seasonAdmin', function ($season) {
            return auth()->check() && in_array(auth()->id(), array_column($season->admins, 'id'));
        });
    }
}









