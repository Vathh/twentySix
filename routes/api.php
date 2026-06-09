<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FriendshipController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\QuickGameController;
use App\Http\Controllers\Api\TournamentInvitationController;
use App\Http\Controllers\Api\GameScoring\GroupGameScoringController;
use App\Http\Controllers\Api\GameScoring\PlayoffGameScoringController;
use App\Http\Controllers\Api\GameScoring\QuickGameScoringController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'tournamentLogin']); // kod turnieju – do sędziowania
Route::post('/account/login', [AuthController::class, 'login']);   // email + hasło – konto gracza
Route::post('/register', [AuthController::class, 'register']);

// Quick game update - może być bez auth (gracze tymczasowi)
Route::post('/quick-game/update', [QuickGameController::class, 'update']);

// Lobby endpoints - niektóre wymagają auth, niektóre nie
Route::get('/quick-game/lobby/code/{code}', [QuickGameLobbyController::class, 'getByCode']);
Route::post('/quick-game/lobby/join', [QuickGameLobbyController::class, 'join']); // Może być bez auth

Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('game')->group(function () {
        Route::post('/inProgress', [GameController::class, 'setStatusInProgress']);
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

    Route::prefix('quick-games/{quickGame}')->whereNumber('quickGame')->group(function () {
        Route::get('/scoring/state', [QuickGameScoringController::class, 'state']);
        Route::post('/legs', [QuickGameScoringController::class, 'startLeg']);
        Route::post('/legs/{leg}/visits', [QuickGameScoringController::class, 'recordVisit'])->whereNumber('leg');
        Route::post('/legs/{leg}/visits/undo', [QuickGameScoringController::class, 'undoVisit'])->whereNumber('leg');
        Route::post('/legs/{leg}/close', [QuickGameScoringController::class, 'closeLeg'])->whereNumber('leg');
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

    Route::prefix('quick-game')->group(function () {
        Route::post('/create', [QuickGameController::class, 'create']);
        Route::get('/active', [QuickGameController::class, 'getActive']);
        Route::post('/inProgress', [QuickGameController::class, 'setStatusInProgress']);
    });

    Route::prefix('tournaments/invitations')->group(function () {
        Route::get('/received', [TournamentInvitationController::class, 'received']);
        Route::post('/{invitationId}/accept', [TournamentInvitationController::class, 'accept'])->whereNumber('invitationId');
        Route::post('/{invitationId}/reject', [TournamentInvitationController::class, 'reject'])->whereNumber('invitationId');
        Route::post('/{invitationId}/withdraw', [TournamentInvitationController::class, 'withdraw'])->whereNumber('invitationId');
    });

    Route::prefix('quick-game/lobby')->group(function () {
        Route::post('/create', [QuickGameLobbyController::class, 'create']);
        Route::get('/invitations', [QuickGameLobbyController::class, 'myInvitations']);
        Route::post('/invitations/{invitationId}/reject', [QuickGameLobbyController::class, 'rejectInvitation']);
        Route::get('/{lobbyId}', [QuickGameLobbyController::class, 'get']);
        Route::patch('/{lobbyId}', [QuickGameLobbyController::class, 'updateSettings']);
        Route::post('/{lobbyId}/join', [QuickGameLobbyController::class, 'joinById']);
        Route::post('/{lobbyId}/leave', [QuickGameLobbyController::class, 'leave']);
        Route::post('/{lobbyId}/ready', [QuickGameLobbyController::class, 'setReady']);
        Route::post('/{lobbyId}/start', [QuickGameLobbyController::class, 'start']);
        Route::post('/{lobbyId}/invite', [QuickGameLobbyController::class, 'invite']);
        Route::post('/{lobbyId}/add-guest', [QuickGameLobbyController::class, 'addGuest']);
    });

});

