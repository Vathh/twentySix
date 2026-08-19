<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\FriendInvitationController;
use App\Http\Controllers\MyCompetitionsController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\LeagueController;
use App\Http\Controllers\LeagueSeasonController;
use App\Http\Controllers\GameViewController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\QuickGameFfaLiveController;
use App\Http\Controllers\SeasonController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\TournamentJoinLandingController;
use App\Http\Controllers\TournamentRefereeController;
use App\Http\Controllers\TournamentTabletLoginLandingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PagesController::class, 'showHomePage'])->name('pages.home');
Route::get('/join-tournament/{code}', [TournamentJoinLandingController::class, 'show'])
    ->where('code', '[A-Za-z0-9]+')
    ->name('tournaments.join-landing');
Route::get('/tablet-login/{code}', [TournamentTabletLoginLandingController::class, 'show'])
    ->where('code', '[A-Za-z0-9]+')
    ->name('tournaments.tablet-login-landing');

Route::prefix('referee')->name('referee.')->group(function () {
    Route::get('/login', [TournamentRefereeController::class, 'login'])->name('login');
    Route::get('/games', [TournamentRefereeController::class, 'games'])->name('games');
    Route::get('/score', [TournamentRefereeController::class, 'score'])->name('score');
});

Route::get('/games/{type}/{id}', [GameViewController::class, 'show'])
    ->where('type', 'group|playoff|quick')
    ->whereNumber('id')
    ->name('games.show');
Route::post('/games/{type}/{id}/result', [GameViewController::class, 'updateResult'])
    ->where('type', 'group|playoff')
    ->whereNumber('id')
    ->middleware('auth')
    ->name('games.result.update');
Route::get('/games/{type}/{id}/live', [GameViewController::class, 'live'])
    ->where('type', 'group|playoff|quick')
    ->whereNumber('id')
    ->name('games.live');
Route::get('/games/{type}/{id}/live/state', [GameViewController::class, 'liveState'])
    ->where('type', 'group|playoff|quick')
    ->whereNumber('id')
    ->name('games.live.state');

Route::get('/quick-game/lobby/{lobbyId}/live', [QuickGameFfaLiveController::class, 'live'])
    ->whereNumber('lobbyId')
    ->name('quick-game.ffa.live');
Route::get('/quick-game/lobby/{lobbyId}/live/state', [QuickGameFfaLiveController::class, 'liveState'])
    ->whereNumber('lobbyId')
    ->name('quick-game.ffa.live.state');

Route::get('/register', [PagesController::class, 'showRegisterPage'])->name('pages.registerPanel');
Route::post('/register', [AuthController::class, 'register'])->name('register');
Route::get('/email/verify/sent', [PagesController::class, 'showVerifyEmailNoticePage'])->name('verification.notice');
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');
Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
    ->middleware('throttle:6,1')
    ->name('verification.send');

Route::get('/login', [PagesController::class, 'showLoginPage'])->name('pages.loginPanel');
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth', 'platform.admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [\App\Http\Controllers\PlatformAdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/users', [\App\Http\Controllers\PlatformAdminController::class, 'users'])->name('users');
    Route::get('/users/{user}', [\App\Http\Controllers\PlatformAdminController::class, 'showUser'])
        ->whereNumber('user')
        ->name('users.show');
    Route::post('/users/{user}/can-create-organizations', [\App\Http\Controllers\PlatformAdminController::class, 'updateCanCreateOrganizations'])
        ->whereNumber('user')
        ->name('users.can-create-organizations');
    Route::post('/users/{user}/ban', [\App\Http\Controllers\PlatformAdminController::class, 'updateBanned'])
        ->whereNumber('user')
        ->name('users.ban');
});

Route::middleware('auth')->prefix('settings')->name('settings.')->group(function () {
    Route::get('/', [SettingsController::class, 'index'])->name('index');
    Route::get('/password', [SettingsController::class, 'editPassword'])->name('password.edit');
    Route::put('/password', [SettingsController::class, 'updatePassword'])->name('password.update');
});

Route::middleware('auth')->get('/me', [MyCompetitionsController::class, 'index'])->name('me.index');

Route::get('/players/search', [PlayerController::class, 'search'])->name('players.search');
Route::get('/players/{player}/edit', [PlayerController::class, 'edit'])->middleware('auth')->name('players.edit');
Route::put('/players/{player}', [PlayerController::class, 'update'])->middleware('auth')->name('players.update');
Route::get('/players/{player}', [PlayerController::class, 'show'])->name('players.show');
Route::get('/players/{player}/games', [PlayerController::class, 'gameHistory'])->name('players.games');
Route::post('/players/{player}/add-friend', [PlayerController::class, 'addFriend'])->name('players.add-friend')->middleware('auth');
Route::post('/friends/invitations/{invitation}/accept', [FriendInvitationController::class, 'accept'])->name('friends.invitations.accept')->middleware('auth');
Route::post('/friends/invitations/{invitation}/reject', [FriendInvitationController::class, 'reject'])->name('friends.invitations.reject')->middleware('auth');

Route::resource('organizations', OrganizationController::class);
Route::prefix('organizations/{organization}')->group(function () {
    Route::get('/relatedUsers', [OrganizationController::class, 'relatedUsers'])->name('organizations.relatedUsers');
    Route::post('/relatedUsers/add', [OrganizationController::class, 'addRelatedUser'])->name('organizations.relatedUsers.add');
    Route::post('/relatedUsers/invitations/{invitation}/cancel', [OrganizationController::class, 'cancelRelatedUserInvitation'])
        ->whereNumber('invitation')
        ->name('organizations.relatedUsers.invitations.cancel');
    Route::delete('/relatedUsers/remove', [OrganizationController::class, 'removeRelatedUser'])->name('organizations.relatedUsers.remove');

    Route::get('/admins', [OrganizationController::class, 'admins'])->name('organizations.admins');
    Route::post('/admins/add', [OrganizationController::class, 'addAdmin'])->name('organizations.admins.add');
    Route::delete('/admins/remove', [OrganizationController::class, 'removeAdmin'])->name('organizations.admins.remove');

    Route::get('/guests', [OrganizationController::class, 'guests'])->name('organizations.guests');
    Route::post('/guests/add', [OrganizationController::class, 'addGuest'])->name('organizations.guests.add');
    Route::delete('/guests/remove', [OrganizationController::class, 'removeGuest'])->name('organizations.guests.remove');

    Route::get('/leagues/create', [LeagueController::class, 'create'])->name('leagues.create')->middleware('auth');
    Route::post('/leagues', [LeagueController::class, 'store'])->name('leagues.store')->middleware('auth');
});

Route::get('/leagues/{league}', [LeagueController::class, 'show'])->name('leagues.show');
Route::get('/leagues/{league}/edit', [LeagueController::class, 'edit'])->name('leagues.edit')->middleware('auth');
Route::put('/leagues/{league}', [LeagueController::class, 'update'])->name('leagues.update')->middleware('auth');
Route::get('/leagues/{league}/roster', [LeagueController::class, 'roster'])->name('leagues.roster')->middleware('auth');
Route::post('/leagues/{league}/roster', [LeagueController::class, 'assignPlayer'])->name('leagues.roster.assign')->middleware('auth');
Route::delete('/leagues/{league}/roster', [LeagueController::class, 'removePlayer'])->name('leagues.roster.remove')->middleware('auth');
Route::patch('/leagues/{league}/roster/capacity', [LeagueController::class, 'updateDivisionCapacity'])->name('leagues.roster.capacity')->middleware('auth');
Route::get('/leagues/{league}/relatedUsers', [LeagueController::class, 'relatedUsers'])->name('leagues.relatedUsers')->middleware('auth');
Route::post('/leagues/{league}/relatedUsers/add', [LeagueController::class, 'addRelatedUser'])->name('leagues.relatedUsers.add')->middleware('auth');
Route::delete('/leagues/{league}/relatedUsers/remove', [LeagueController::class, 'removeRelatedUser'])->name('leagues.relatedUsers.remove')->middleware('auth');
Route::get('/leagues/{league}/guests', [LeagueController::class, 'guests'])->name('leagues.guests')->middleware('auth');
Route::post('/leagues/{league}/guests/add', [LeagueController::class, 'addGuest'])->name('leagues.guests.add')->middleware('auth');
Route::delete('/leagues/{league}/guests/remove', [LeagueController::class, 'removeGuest'])->name('leagues.guests.remove')->middleware('auth');

Route::get('/leagues/{league}/seasons/create', [LeagueSeasonController::class, 'create'])->name('league-seasons.create')->middleware('auth');
Route::post('/leagues/{league}/seasons', [LeagueSeasonController::class, 'store'])->name('league-seasons.store')->middleware('auth');
Route::get('/league-seasons/{leagueSeason}', [LeagueSeasonController::class, 'show'])->name('league-seasons.show');
Route::post('/league-seasons/{leagueSeason}/start', [LeagueSeasonController::class, 'start'])->name('league-seasons.start')->middleware('auth');
Route::post('/league-seasons/{leagueSeason}/advance', [LeagueSeasonController::class, 'advance'])->name('league-seasons.advance')->middleware('auth');
Route::post('/league-seasons/{leagueSeason}/withdraw', [LeagueSeasonController::class, 'withdraw'])->name('league-seasons.withdraw')->middleware('auth');
Route::post('/league-seasons/{leagueSeason}/cancel', [LeagueSeasonController::class, 'cancel'])->name('league-seasons.cancel')->middleware('auth');

Route::get('/league-games/{leagueGame}', [LeagueSeasonController::class, 'showGame'])->name('league-games.show');
Route::post('/league-games/{leagueGame}/result', [LeagueSeasonController::class, 'updateResult'])->name('league-games.result')->middleware('auth');
Route::post('/league-games/{leagueGame}/walkover', [LeagueSeasonController::class, 'walkover'])->name('league-games.walkover')->middleware('auth');
Route::post('/league-games/{leagueGame}/extend', [LeagueSeasonController::class, 'extend'])->name('league-games.extend')->middleware('auth');

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
   Route::get('/groups-live', [TournamentController::class, 'groupsLive'])->name('tournaments.groups-live');
   Route::get('/join-requests-live', [TournamentController::class, 'joinRequestsLive'])->name('tournaments.join-requests-live');
   Route::get('/admins', [TournamentController::class, 'admins'])->name('tournaments.admins');
   Route::post('/admins/add', [TournamentController::class, 'addAdmin'])->name('tournaments.admins.add');
   Route::delete('/admins/remove', [TournamentController::class, 'removeAdmin'])->name('tournaments.admins.remove');
   Route::get('/start', [TournamentController::class, 'start'])->name('tournaments.start');
   Route::post('/run', [TournamentController::class, 'runTournament'])->name('tournaments.run');
   Route::get('/invitations/search', [TournamentController::class, 'searchInvitationUsers'])->name('tournaments.invitations.search');
   Route::post('/invitations/send', [TournamentController::class, 'sendInvitation'])->name('tournaments.invitations.send');
   Route::post('/invitations/bulk', [TournamentController::class, 'sendBulkInvitations'])->name('tournaments.invitations.bulk');
   Route::post('/invitations/{invitation}/cancel', [TournamentController::class, 'cancelInvitation'])->name('tournaments.invitations.cancel');
   Route::post('/invitations/{invitation}/remove', [TournamentController::class, 'removeParticipant'])->name('tournaments.invitations.remove');
   Route::post('/join-code/regenerate', [TournamentController::class, 'regenerateJoinCode'])->name('tournaments.join-code.regenerate');
   Route::post('/join-code/toggle', [TournamentController::class, 'toggleJoinCode'])->name('tournaments.join-code.toggle');
   Route::post('/tablet-login-code/regenerate', [TournamentController::class, 'regenerateTabletLoginCode'])->name('tournaments.tablet-login-code.regenerate');
   Route::post('/join-requests/{joinRequest}/approve', [TournamentController::class, 'approveJoinRequest'])->name('tournaments.join-requests.approve');
   Route::post('/join-requests/{joinRequest}/reject', [TournamentController::class, 'rejectJoinRequest'])->name('tournaments.join-requests.reject');
   Route::post('/participants/guests/add', [TournamentController::class, 'addGuestParticipant'])->name('tournaments.participants.guests.add');
   Route::post('/participants/guests/create', [TournamentController::class, 'createGuestParticipant'])->name('tournaments.participants.guests.create');
   Route::delete('/participants/guests/remove', [TournamentController::class, 'removeGuestParticipant'])->name('tournaments.participants.guests.remove');
});
