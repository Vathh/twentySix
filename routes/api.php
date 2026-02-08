<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FriendshipController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\QuickGameController;
use App\Http\Controllers\Api\QuickGameLobbyController;
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

    Route::prefix('quick-game/lobby')->group(function () {
        Route::post('/create', [QuickGameLobbyController::class, 'create']);
        Route::get('/{lobbyId}', [QuickGameLobbyController::class, 'get']);
        Route::post('/{lobbyId}/join', [QuickGameLobbyController::class, 'joinById']);
        Route::post('/{lobbyId}/leave', [QuickGameLobbyController::class, 'leave']);
        Route::post('/{lobbyId}/ready', [QuickGameLobbyController::class, 'setReady']);
        Route::post('/{lobbyId}/start', [QuickGameLobbyController::class, 'start']);
        Route::post('/{lobbyId}/invite', [QuickGameLobbyController::class, 'invite']);
        Route::post('/{lobbyId}/add-guest', [QuickGameLobbyController::class, 'addGuest']);
    });

});

