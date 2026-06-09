<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LeagueController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\SeasonController;
use App\Http\Controllers\TournamentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PagesController::class, 'showHomePage'])->name('pages.home');

Route::get('/matches/{type}/{id}', [MatchController::class, 'show'])
    ->where('type', 'group|playoff|quick')
    ->whereNumber('id')
    ->name('matches.show');
Route::post('/matches/{type}/{id}/result', [MatchController::class, 'updateResult'])
    ->where('type', 'group|playoff')
    ->whereNumber('id')
    ->middleware('auth')
    ->name('matches.result.update');
Route::get('/matches/{type}/{id}/live', [MatchController::class, 'live'])
    ->where('type', 'group|playoff|quick')
    ->whereNumber('id')
    ->name('matches.live');
Route::get('/matches/{type}/{id}/live/state', [MatchController::class, 'liveState'])
    ->where('type', 'group|playoff|quick')
    ->whereNumber('id')
    ->name('matches.live.state');

Route::get('/register', [PagesController::class, 'showRegisterPage'])->name('pages.registerPanel');
Route::post('/register', [AuthController::class, 'register'])->name('register');

Route::get('/login', [PagesController::class, 'showLoginPage'])->name('pages.loginPanel');
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/players/search', [PlayerController::class, 'search'])->name('players.search');
Route::get('/players/{player}', [PlayerController::class, 'show'])->name('players.show');
Route::get('/players/{player}/matches', [PlayerController::class, 'matchHistory'])->name('players.matches');
Route::post('/players/{player}/add-friend', [PlayerController::class, 'addFriend'])->name('players.add-friend')->middleware('auth');

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
   Route::post('/invitations/send', [TournamentController::class, 'sendInvitation'])->name('tournaments.invitations.send');
   Route::post('/invitations/bulk', [TournamentController::class, 'sendBulkInvitations'])->name('tournaments.invitations.bulk');
   Route::post('/invitations/{invitation}/cancel', [TournamentController::class, 'cancelInvitation'])->name('tournaments.invitations.cancel');
   Route::post('/invitations/{invitation}/remove', [TournamentController::class, 'removeParticipant'])->name('tournaments.invitations.remove');
   Route::post('/participants/guests/add', [TournamentController::class, 'addGuestParticipant'])->name('tournaments.participants.guests.add');
   Route::delete('/participants/guests/remove', [TournamentController::class, 'removeGuestParticipant'])->name('tournaments.participants.guests.remove');
});
