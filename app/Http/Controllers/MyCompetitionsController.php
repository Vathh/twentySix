<?php

namespace App\Http\Controllers;

use App\Services\User\UserCompetitionsService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class MyCompetitionsController extends Controller
{
    public function __construct(
        private UserCompetitionsService $userCompetitionsService,
    ) {
    }

    public function index(): Factory|View
    {
        return view('me.index', $this->userCompetitionsService->forUser(Auth::user(), withUrls: true));
    }
}
