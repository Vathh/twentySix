<?php

namespace App\Providers;

use App\Services\Friends\FriendshipService;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Broadcast;
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
        Carbon::setLocale((string) config('app.locale'));

        // Tylko Sanctum (Bearer z telefonu), bez `web`/sesji. Musi być jedyne wywołanie Broadcast::routes —
        // patrz bootstrap/app.php (brak `channels:` w withRouting).
        Broadcast::routes([
            'middleware' => [Authenticate::using('sanctum')],
        ]);
        require base_path('routes/channels.php');

        View::composer('layouts.app', function ($view) {
            $friends = collect();
            $receivedFriendInvitations = collect();
            $sentFriendInvitations = collect();
            if (Auth::check()) {
                $friendshipService = app(FriendshipService::class);
                $friends = $friendshipService->getFriends(Auth::id());
                $receivedFriendInvitations = $friendshipService->getReceivedInvitations(Auth::id());
                $sentFriendInvitations = $friendshipService->getSentInvitations(Auth::id());
            }
            $view->with('friends', $friends);
            $view->with('receivedFriendInvitations', $receivedFriendInvitations);
            $view->with('sentFriendInvitations', $sentFriendInvitations);
        });

        Blade::if('canCreateOrganizations', function () {
            return auth()->check() && auth()->user()->can_create_organizations;
        });

        Blade::if('platformAdmin', function () {
            return auth()->check() && auth()->user()->isPlatformAdmin();
        });

        Blade::if('organizationAdmin', function ($organization) {
            return auth()->check() && in_array(auth()->id(), array_column($organization->admins, 'id'));
        });

        Blade::if('seasonAdmin', function ($season) {
            return auth()->check() && in_array(auth()->id(), array_column($season->admins, 'id'));
        });
    }
}









