<?php

namespace App\Http\Controllers;

use App\Support\Broadcasting\ReverbClientConfig;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Shell Blade dla sędziowania turnieju w przeglądarce.
 * Auth i scoring idą przez istniejące API (token z kodu tabletu).
 */
class TournamentRefereeController
{
    public function login(): Factory|View
    {
        return view('referee.login');
    }

    public function games(): Factory|View
    {
        return view('referee.games', [
            'reverb' => ReverbClientConfig::forWeb(),
        ]);
    }

    public function score(Request $request): Factory|View
    {
        $type = $request->query('type');
        $id = (int) $request->query('id');

        if (! in_array($type, ['group', 'playoff'], true) || $id < 1) {
            abort(404);
        }

        $channel = $type === 'playoff'
            ? 'playoff-game.'.$id
            : 'group-game.'.$id;

        return view('referee.score', [
            'gameType' => $type,
            'gameId' => $id,
            'channel' => $channel,
            'reverb' => ReverbClientConfig::forWeb(),
        ]);
    }
}
