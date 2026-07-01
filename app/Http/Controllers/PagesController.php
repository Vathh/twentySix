<?php

namespace App\Http\Controllers;

use App\Models\League\League;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class PagesController extends Controller
{
    public function showHomePage(): Factory|View
    {
        return view('pages.home');
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
}

