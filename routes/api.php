<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FriendshipController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\OrganizationInvitationController;
use App\Http\Controllers\Api\PlayerProfileController;
use App\Http\Controllers\Api\PushTokenController;
use App\Http\Controllers\Api\QuickGameController;
use App\Http\Controllers\Api\QuickGameFfaController;
use App\Http\Controllers\Api\QuickGameLobbyController;
use App\Http\Controllers\Api\SeasonController;
use App\Http\Controllers\Api\TournamentCatalogController;
use App\Http\Controllers\Api\TournamentInvitationController;
use App\Http\Controllers\Api\TournamentJoinController;
use App\Http\Controllers\Api\GameScoring\GroupGameScoringController;
use App\Http\Controllers\Api\GameScoring\LeagueGameScoringController;
use App\Http\Controllers\Api\GameScoring\PlayoffGameScoringController;
use App\Http\Controllers\Api\LeagueCatalogController;
use App\Http\Controllers\Api\LeagueSeasonCatalogController;
use App\Http\Controllers\Api\LeagueGamePlayController;
use App\Http\Controllers\Api\MyCompetitionsController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'tournamentLogin']); // kod turnieju – do sędziowania
Route::post('/account/login', [AuthController::class, 'login']);   // email + hasło – konto gracza
Route::post('/register', [AuthController::class, 'register']);
Route::post('/email/verification-notification', [AuthController::class, 'resendVerificationEmail'])
    ->middleware('throttle:6,1');

Route::middleware(['auth:sanctum', 'not.banned'])->prefix('account')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/session/refresh', [AuthController::class, 'refreshSession']);
    Route::put('/password', [AuthController::class, 'changePassword']);
});

// Wynik quick game online finalizuje silnik FFA; ten endpoint tylko dla achievementów po meczu.

Route::middleware(['auth:sanctum', 'not.banned'])->group(function () {

    Route::put('/push-tokens', [PushTokenController::class, 'upsert']);
    Route::delete('/push-tokens', [PushTokenController::class, 'destroy']);

    Route::get('/me/competitions', [MyCompetitionsController::class, 'index']);

    Route::prefix('game')->group(function () {
        Route::post('/inProgress', [GameController::class, 'setStatusInProgress']);
        Route::post('/release', [GameController::class, 'releaseLock']);
        Route::post('/update', [GameController::class, 'update']);
        Route::get('/active', [GameController::class, 'getActiveGames']);
    });

    Route::prefix('group-games/{game}')->whereNumber('game')->group(function () {
        Route::get('/scoring/state', [GroupGameScoringController::class, 'state']);
        Route::post('/legs', [GroupGameScoringController::class, 'startLeg']);
        Route::post('/legs/{leg}/visits', [GroupGameScoringController::class, 'recordVisit'])->whereNumber('leg');
        Route::post('/legs/{leg}/visits/undo', [GroupGameScoringController::class, 'undoVisit'])->whereNumber('leg');
        Route::post('/legs/{leg}/close', [GroupGameScoringController::class, 'closeLeg'])->whereNumber('leg');
    });

    Route::prefix('playoff-games/{playoffGame}')->whereNumber('playoffGame')->group(function () {
        Route::get('/scoring/state', [PlayoffGameScoringController::class, 'state']);
        Route::post('/legs', [PlayoffGameScoringController::class, 'startLeg']);
        Route::post('/legs/{leg}/visits', [PlayoffGameScoringController::class, 'recordVisit'])->whereNumber('leg');
        Route::post('/legs/{leg}/visits/undo', [PlayoffGameScoringController::class, 'undoVisit'])->whereNumber('leg');
        Route::post('/legs/{leg}/close', [PlayoffGameScoringController::class, 'closeLeg'])->whereNumber('leg');
    });

    Route::get('/league-games/mine', [LeagueGamePlayController::class, 'mine']);
    Route::get('/league-games/invitations', [LeagueGamePlayController::class, 'invitations']);
    Route::prefix('league-games/{leagueGame}')->whereNumber('leagueGame')->group(function () {
        Route::get('/', [LeagueGamePlayController::class, 'show']);
        Route::post('/open-lobby', [LeagueGamePlayController::class, 'openLobby']);
        Route::post('/accept', [LeagueGamePlayController::class, 'accept']);
        Route::post('/reject', [LeagueGamePlayController::class, 'reject']);
        Route::post('/cancel', [LeagueGamePlayController::class, 'cancel']);
        Route::post('/start', [LeagueGamePlayController::class, 'start']);
        Route::get('/scoring/state', [LeagueGameScoringController::class, 'state']);
        Route::post('/legs', [LeagueGameScoringController::class, 'startLeg']);
        Route::post('/legs/{leg}/visits', [LeagueGameScoringController::class, 'recordVisit'])->whereNumber('leg');
        Route::post('/legs/{leg}/visits/undo', [LeagueGameScoringController::class, 'undoVisit'])->whereNumber('leg');
        Route::post('/legs/{leg}/close', [LeagueGameScoringController::class, 'closeLeg'])->whereNumber('leg');
    });

    Route::prefix('friends')->group(function () {
        Route::post('/add', [FriendshipController::class, 'addFriend']);
        Route::delete('/remove', [FriendshipController::class, 'removeFriend']);
        Route::get('/', [FriendshipController::class, 'getFriends']);
        Route::post('/invite', [FriendshipController::class, 'sendInvitation']);
        Route::post('/accept', [FriendshipController::class, 'acceptInvitation']);
        Route::post('/reject', [FriendshipController::class, 'rejectInvitation']);
        Route::get('/invitations/received', [FriendshipController::class, 'getReceivedInvitations']);
        Route::get('/invitations/sent', [FriendshipController::class, 'getSentInvitations']);
    });

    Route::prefix('users')->group(function () {
        Route::get('/search', [FriendshipController::class, 'searchUsers']);
    });

    Route::prefix('players')->group(function () {
        Route::get('/{player}', [PlayerProfileController::class, 'show'])->whereNumber('player');
        Route::put('/{player}', [PlayerProfileController::class, 'update'])->whereNumber('player');
        Route::get('/{player}/games', [PlayerProfileController::class, 'games'])->whereNumber('player');
    });

    Route::prefix('quick-game')->group(function () {
        Route::post('/update', [QuickGameController::class, 'update']);
    });

    Route::get('/organizations', [OrganizationController::class, 'index']);
    Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])->whereNumber('organization');
    Route::get('/leagues/{league}', [LeagueCatalogController::class, 'show'])->whereNumber('league');
    Route::get('/league-seasons/{leagueSeason}', [LeagueSeasonCatalogController::class, 'show'])->whereNumber('leagueSeason');
    Route::get('/seasons', [SeasonController::class, 'index']);
    Route::get('/seasons/{season}', [SeasonController::class, 'show'])->whereNumber('season');
    Route::get('/seasons/{season}/standings', [SeasonController::class, 'standings'])->whereNumber('season');
    Route::get('/tournaments', [TournamentCatalogController::class, 'index']);
    Route::get('/tournaments/{tournament}', [TournamentCatalogController::class, 'show'])->whereNumber('tournament');

    Route::prefix('tournaments/invitations')->group(function () {
        Route::get('/received', [TournamentInvitationController::class, 'received']);
        Route::post('/{invitationId}/accept', [TournamentInvitationController::class, 'accept'])->whereNumber('invitationId');
        Route::post('/{invitationId}/reject', [TournamentInvitationController::class, 'reject'])->whereNumber('invitationId');
        Route::post('/{invitationId}/withdraw', [TournamentInvitationController::class, 'withdraw'])->whereNumber('invitationId');
    });

    Route::prefix('organizations/invitations')->group(function () {
        Route::get('/received', [OrganizationInvitationController::class, 'received']);
        Route::post('/{invitationId}/accept', [OrganizationInvitationController::class, 'accept'])->whereNumber('invitationId');
        Route::post('/{invitationId}/reject', [OrganizationInvitationController::class, 'reject'])->whereNumber('invitationId');
    });

    Route::prefix('tournaments/join')->group(function () {
        Route::get('/{code}', [TournamentJoinController::class, 'preview'])->where('code', '[A-Za-z0-9]+');
        Route::post('/{code}', [TournamentJoinController::class, 'apply'])->where('code', '[A-Za-z0-9]+');
    });

    Route::prefix('quick-game/lobby')->group(function () {
        Route::post('/create', [QuickGameLobbyController::class, 'create']);
        Route::get('/invitations', [QuickGameLobbyController::class, 'myInvitations']);
        Route::get('/active-match', [QuickGameFfaController::class, 'activeMatch']);
        Route::post('/invitations/{invitationId}/reject', [QuickGameLobbyController::class, 'rejectInvitation']);
        Route::get('/{lobbyId}', [QuickGameLobbyController::class, 'get']);
        Route::patch('/{lobbyId}', [QuickGameLobbyController::class, 'updateSettings']);
        Route::post('/{lobbyId}/join', [QuickGameLobbyController::class, 'joinById']);
        Route::post('/{lobbyId}/leave', [QuickGameLobbyController::class, 'leave']);
        Route::post('/{lobbyId}/ready', [QuickGameLobbyController::class, 'setReady']);
        Route::post('/{lobbyId}/start', [QuickGameLobbyController::class, 'start']);
        Route::post('/{lobbyId}/invite', [QuickGameLobbyController::class, 'invite']);
        Route::post('/{lobbyId}/add-guest', [QuickGameLobbyController::class, 'addGuest']);
        Route::post('/{lobbyId}/rematch/intent', [QuickGameLobbyController::class, 'expressRematchIntent']);
        Route::post('/{lobbyId}/rematch', [QuickGameLobbyController::class, 'createRematch']);
        Route::get('/{lobbyId}/rematch', [QuickGameLobbyController::class, 'rematchStatus']);
        Route::get('/{lobbyId}/ffa/state', [QuickGameFfaController::class, 'state']);
        Route::post('/{lobbyId}/ffa/presence', [QuickGameFfaController::class, 'updatePresence']);
        Route::post('/{lobbyId}/ffa/visits', [QuickGameFfaController::class, 'recordVisit']);
        Route::post('/{lobbyId}/ffa/visits/undo', [QuickGameFfaController::class, 'undoVisit']);
        Route::post('/{lobbyId}/ffa/cricket/darts', [QuickGameFfaController::class, 'recordCricketDart']);
        Route::post('/{lobbyId}/ffa/cricket/darts/undo', [QuickGameFfaController::class, 'undoCricketDart']);
        Route::post('/{lobbyId}/ffa/bob27/darts', [QuickGameFfaController::class, 'recordBob27Dart']);
        Route::post('/{lobbyId}/ffa/bob27/darts/undo', [QuickGameFfaController::class, 'undoBob27Dart']);
        Route::post('/{lobbyId}/ffa/atc/visits', [QuickGameFfaController::class, 'recordAtcVisit']);
        Route::post('/{lobbyId}/ffa/atc/visits/undo', [QuickGameFfaController::class, 'undoAtcVisit']);
        Route::post('/{lobbyId}/ffa/catch40/visits', [QuickGameFfaController::class, 'recordCatch40Visit']);
        Route::post('/{lobbyId}/ffa/catch40/visits/undo', [QuickGameFfaController::class, 'undoCatch40Visit']);
        Route::post('/{lobbyId}/ffa/cricket56/visits', [QuickGameFfaController::class, 'recordCricket56Visit']);
        Route::post('/{lobbyId}/ffa/cricket56/visits/undo', [QuickGameFfaController::class, 'undoCricket56Visit']);
    });

});

