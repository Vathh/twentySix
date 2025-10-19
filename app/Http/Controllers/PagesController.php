<?php

namespace App\Http\Controllers;

use App\Models\League;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

class PagesController extends Controller
{
    public function showHomePage(): Factory|View
    {
        return view('pages.home');
    }

    public function showLoginPage(): Factory|View
    {
        return view('pages.login');
    }

    public function showRegisterPage(): Factory|View
    {
        return view('pages.register');
    }
    public function showTournamentsPage(): Factory|View
    {
        return view('tournament.tournaments');
    }
}
