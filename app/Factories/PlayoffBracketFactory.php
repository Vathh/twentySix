<?php /** @noinspection PhpParamsInspection */

namespace App\Factories;

use App\Domain\PlayoffGameDomain;
use App\Enums\PlayoffRound;
use App\Enums\PlayoffSlot;
use Illuminate\Support\Collection;

class PlayoffBracketFactory
{
    public function createFor16(int $tournamentId, array $playerIds): Collection
    {
        $matches = collect([
            new PlayoffGameDomain(tournamentId: $tournamentId,
                                    round: PlayoffRound::EIGHT,
                                    slot: PlayoffSlot::EIGHT_1,
                                    winnerDestinationSlot: PlayoffSlot::QF_1_A),
            new PlayoffGameDomain(tournamentId: $tournamentId,
                round: PlayoffRound::EIGHT,
                slot: PlayoffSlot::EIGHT_1,
                winnerDestinationSlot: PlayoffSlot::QF_1_A),
            new PlayoffGameDomain(tournamentId: $tournamentId,
                round: PlayoffRound::EIGHT,
                slot: PlayoffSlot::EIGHT_1,
                winnerDestinationSlot: PlayoffSlot::QF_1_A),
            new PlayoffGameDomain(tournamentId: $tournamentId,
                round: PlayoffRound::EIGHT,
                slot: PlayoffSlot::EIGHT_1,
                winnerDestinationSlot: PlayoffSlot::QF_1_A),
        ])
    }
}
