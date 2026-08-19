<?php

use App\Services\QuickGame\QuickGameLobbyAuthorizationService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('quick-game-lobby.{lobbyId}', function ($user, $lobbyId) {
    return app(QuickGameLobbyAuthorizationService::class)->canSubscribe($user, $lobbyId);
});

Broadcast::channel('group-game.{gameId}', function () {
    return true;
});

Broadcast::channel('league-game.{leagueGameId}', function () {
    return true;
});

Broadcast::channel('tournament.{tournamentId}', function () {
    return true;
});

Broadcast::channel('quick-game.{quickGameId}', function () {
    return true;
});

Broadcast::channel('quick-game-ffa-lobby.{lobbyId}', function () {
    return true;
});
