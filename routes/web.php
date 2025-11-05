<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LeagueController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\SeasonController;
use App\Http\Controllers\TournamentController;
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

    Route::get('/guests', [LeagueController::class, 'guests'])->name('leagues.guests');
    Route::post('/guests/add', [LeagueController::class, 'addGuest'])->name('leagues.guests.add');
    Route::delete('/guests/remove', [LeagueController::class, 'removeGuest'])->name('leagues.guests.remove');
});

Route::resource('seasons', SeasonController::class);
Route::prefix('seasons/{season}')->group(function () {
    Route::get('/relatedUsers', [SeasonController::class, 'relatedUsers'])->name('seasons.relatedUsers');
    Route::post('/relatedUsers/add', [SeasonController::class, 'addRelatedUser'])->name('seasons.relatedUsers.add');
    Route::delete('/relatedUsers/remove', [SeasonController::class, 'removeRelatedUser'])->name('seasons.relatedUsers.remove');

    Route::get('/admins', [SeasonController::class, 'admins'])->name('seasons.admins');
    Route::post('/admins/add', [SeasonController::class, 'addAdmin'])->name('seasons.admins.add');
    Route::delete('/admins/remove', [SeasonController::class, 'removeAdmin'])->name('seasons.admins.remove');

    Route::get('/guests', [SeasonController::class, 'guests'])->name('seasons.guests');
    Route::post('/guests/add', [SeasonController::class, 'addGuest'])->name('seasons.guests.add');
    Route::delete('/guests/remove', [SeasonController::class, 'removeGuest'])->name('seasons.guests.remove');
});

Route::resource('tournaments', TournamentController::class);
Route::prefix('tournaments/{tournament}')->group(function () {
   Route::get('/start', [TournamentController::class, 'start'])->name('tournaments.start');
   Route::post('/run', [TournamentController::class, 'runTournament'])->name('tournaments.run');
});
