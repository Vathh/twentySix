<?php

namespace App\Http\Controllers;

use App\Services\Activity\FeedService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class PagesController extends Controller
{
    public function __construct(private FeedService $feed) {}

    public function showHomePage(): Factory|View|JsonResponse
    {
        if (request()->expectsJson()) {
            abort_unless(Auth::check(), 401);

            return response()->json($this->feed->home(Auth::user(), request()->query('cursor')));
        }

        $feed = Auth::check() ? $this->feed->home(Auth::user(), null) : null;

        return view('pages.home', [
            'feed' => $feed,
        ]);
    }

    public function showLoginPage(): Response
    {
        return $this->noCacheView('pages.login');
    }

    public function showRegisterPage(): Response
    {
        return $this->noCacheView('pages.register');
    }

    public function showVerifyEmailNoticePage(): Response
    {
        return $this->noCacheView('pages.verify-email-sent');
    }

    private function noCacheView(string $view): Response
    {
        return response()
            ->view($view)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function showTournamentsPage(): Factory|View
    {
        return view('tournament.tournaments');
    }

    public function showCheckoutWheel(): Factory|View
    {
        abort_unless(app()->environment('local'), 404);

        return view('dev.checkout-wheel');
    }
}
