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
Route::prefix('leagues/{league}')->group(function () {
    Route::get('/relatedUsers', [LeagueController::class, 'relatedUsers'])->name('leagues.relatedUsers');
    Route::post('/relatedUsers/add', [LeagueController::class, 'addRelatedUser'])->name('leagues.relatedUsers.add');
    Route::delete('/relatedUsers/remove', [LeagueController::class, 'removeRelatedUser'])->name('leagues.relatedUsers.remove');

    Route::get('/admins', [LeagueController::class, 'admins'])->name('leagues.admins');
    Route::post('/admins/add', [LeagueController::class, 'addAdmin'])->name('leagues.admins.add');
    Route::delete('/admins/remove', [LeagueController::class, 'removeAdmin'])->name('leagues.admins.remove');
});

Route::resource('seasons', SeasonController::class);

Route::get('/tournaments', [PagesController::class, 'showTournamentsPage'])->name('tournament.tournaments');
