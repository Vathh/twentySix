<?php

namespace App\Providers;

use App\Models\Career\TrainingGame;
use App\Models\Game\Game;
use App\Models\League\LeagueGame;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\QuickGame\QuickGame;
use App\Services\Friends\FriendshipService;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\RateLimiter;
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

        Relation::morphMap([
            'game' => Game::class,
            'playoff_game' => PlayoffGame::class,
            'quick_game' => QuickGame::class,
            'league_game' => LeagueGame::class,
            'training_game' => TrainingGame::class,
        ]);

        RateLimiter::for('account-login', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('tablet-login', function (Request $request) {
            return Limit::perMinute(60)->by((string) $request->ip());
        });

        // Tylko Sanctum (Bearer z telefonu), bez `web`/sesji. Musi być jedyne wywołanie Broadcast::routes —
        // patrz bootstrap/app.php (brak `channels:` w withRouting).
        Broadcast::routes([
            'middleware' => [Authenticate::using('sanctum')],
        ]);
        require base_path('routes/channels.php');

        View::composer('layouts.app', function ($view) {
            $friendsCount = 0;
            if (Auth::check()) {
                $friendsCount = app(FriendshipService::class)->countFriends(Auth::id());
            }
            $view->with('friendsCount', $friendsCount);
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
