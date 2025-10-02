<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LeagueController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\SeasonController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PagesController::class, 'showHomePage'])->name('pages.home');

Route::get('/register', [PagesController::class, 'showRegisterPage'])->name('pages.registerPanel');
Route::post('/register', [AuthController::class, 'register'])->name('register');

Route::get('/login', [PagesController::class, 'showLoginPage'])->name('pages.loginPanel');
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::resource('leagues', LeagueController::class);
Route::resource('seasons', SeasonController::class);

//Route::get('/leagues', [LeagueController::class, 'index'])->name('league.index');
//Route::post('/leagues', [LeagueController::class, 'store'])->name('league.store');
//Route::get('/leagueCreator', [LeagueController::class, 'create'])->name('league.create');
//Route::get('/leagues/{leagueId}', [LeagueController::class, 'show'])->name('league.show');

//Route::get('/seasons', [SeasonController::class, 'index'])->name('season.index');

Route::get('/tournaments', [PagesController::class, 'showTournamentsPage'])->name('tournament.tournaments');
