<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LeagueController;
use App\Http\Controllers\PagesController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PagesController::class, 'showHomePage'])->name('pages.home');

Route::get('/register', [PagesController::class, 'showRegisterPage'])->name('pages.registerPanel');
Route::post('/register', [AuthController::class, 'register'])->name('register');

Route::get('/login', [PagesController::class, 'showLoginPage'])->name('pages.loginPanel');
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/leagues', [LeagueController::class, 'showLeaguesPage'])->name('league.leagues');
Route::post('/leagues', [LeagueController::class, 'createLeague'])->name('createLeague');
Route::get('/leagueCreator', [LeagueController::class, 'showLeagueCreatorPage'])->name('league.leagueCreator');
Route::get('/leagues/{leagueId}', [LeagueController::class, 'showLeagueDetailsPage'])->name('league.leagueDetails');

Route::get('/seasons', [PagesController::class, 'showSeasonsPage'])->name('season.seasons');

Route::get('/tournaments', [PagesController::class, 'showTournamentsPage'])->name('tournament.tournaments');
