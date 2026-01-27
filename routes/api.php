<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FriendshipController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\QuickGameController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'tournamentLogin']);
Route::post('/register', [AuthController::class, 'register']);

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
    });

    Route::prefix('users')->group(function () {
        Route::get('/search', [FriendshipController::class, 'searchUsers']);
    });

    Route::prefix('quick-game')->group(function () {
        Route::post('/create', [QuickGameController::class, 'create']);
        Route::get('/active', [QuickGameController::class, 'getActive']);
        Route::post('/inProgress', [QuickGameController::class, 'setStatusInProgress']);
    });

});

