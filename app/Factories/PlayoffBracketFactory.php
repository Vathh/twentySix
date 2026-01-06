<?php /** @noinspection PhpParamsInspection */

namespace App\Factories;

use App\Domain\Game\PlayoffGameDomain;
use App\Enums\PlayoffRound;
use App\Enums\PlayoffSlot;
use App\Enums\WinnerDestinationSlot;
use Illuminate\Support\Collection;

class PlayoffBracketFactory
{
    /**
     * @param int $tournamentId
     * @param array $playerIds
     * @return Collection<PlayoffGameDomain>
     */
    public function createFor16(int $tournamentId, array $playerIds): Collection
    {
        $games = collect([
                                    // 1/8 FINAL
            PlayoffGameDomain::createForBracket(tournamentId: $tournamentId,
                                    round: PlayoffRound::EIGHT,
                                    slot: PlayoffSlot::EIGHT_1,
                                    winnerDestinationSlot: WinnerDestinationSlot::QF_1_A),
            PlayoffGameDomain::createForBracket(tournamentId: $tournamentId,
                                    round: PlayoffRound::EIGHT,
                                    slot: PlayoffSlot::EIGHT_2,
                                    winnerDestinationSlot: WinnerDestinationSlot::QF_1_B),
            PlayoffGameDomain::createForBracket(tournamentId: $tournamentId,
                                    round: PlayoffRound::EIGHT,
                                    slot: PlayoffSlot::EIGHT_3,
                                    winnerDestinationSlot: WinnerDestinationSlot::QF_2_A),
            PlayoffGameDomain::createForBracket(tournamentId: $tournamentId,
                                    round: PlayoffRound::EIGHT,
                                    slot: PlayoffSlot::EIGHT_4,
                                    winnerDestinationSlot: WinnerDestinationSlot::QF_2_B),
            PlayoffGameDomain::createForBracket(tournamentId: $tournamentId,
                                    round: PlayoffRound::EIGHT,
                                    slot: PlayoffSlot::EIGHT_5,
                                    winnerDestinationSlot: WinnerDestinationSlot::QF_3_A),
            PlayoffGameDomain::createForBracket(tournamentId: $tournamentId,
                                    round: PlayoffRound::EIGHT,
                                    slot: PlayoffSlot::EIGHT_6,
                                    winnerDestinationSlot: WinnerDestinationSlot::QF_3_B),
            PlayoffGameDomain::createForBracket(tournamentId: $tournamentId,
                                    round: PlayoffRound::EIGHT,
                                    slot: PlayoffSlot::EIGHT_7,
                                    winnerDestinationSlot: WinnerDestinationSlot::QF_4_A),
            PlayoffGameDomain::createForBracket(tournamentId: $tournamentId,
                                    round: PlayoffRound::EIGHT,
                                    slot: PlayoffSlot::EIGHT_8,
                                    winnerDestinationSlot: WinnerDestinationSlot::QF_4_B),

                                    // 1/4 FINAL
            PlayoffGameDomain::createForBracket(tournamentId: $tournamentId,
                                    round: PlayoffRound::QUARTER,
                                    slot: PlayoffSlot::QF_1,
                                    winnerDestinationSlot: WinnerDestinationSlot::SEMI_1_A),
            PlayoffGameDomain::createForBracket(tournamentId: $tournamentId,
                                    round: PlayoffRound::QUARTER,
                                    slot: PlayoffSlot::QF_2,
                                    winnerDestinationSlot: WinnerDestinationSlot::SEMI_1_B),
            PlayoffGameDomain::createForBracket(tournamentId: $tournamentId,
                                    round: PlayoffRound::QUARTER,
                                    slot: PlayoffSlot::QF_3,
                                    winnerDestinationSlot: WinnerDestinationSlot::SEMI_2_A),
            PlayoffGameDomain::createForBracket(tournamentId: $tournamentId,
                                    round: PlayoffRound::QUARTER,
                                    slot: PlayoffSlot::QF_4,
                                    winnerDestinationSlot: WinnerDestinationSlot::SEMI_2_B),

                                        // 1/2 FINAL
            PlayoffGameDomain::createForBracket(tournamentId: $tournamentId,
                                    round: PlayoffRound::SEMI,
                                    slot: PlayoffSlot::SEMI_1,
                                    winnerDestinationSlot: WinnerDestinationSlot::FINAL_A),

            PlayoffGameDomain::createForBracket(tournamentId: $tournamentId,
                                    round: PlayoffRound::SEMI,
                                    slot: PlayoffSlot::SEMI_2,
                                    winnerDestinationSlot: WinnerDestinationSlot::FINAL_B),

                                        // FINAL & THIRD
            PlayoffGameDomain::createForBracket(tournamentId: $tournamentId,
                                    round: PlayoffRound::FINAL,
                                    slot: PlayoffSlot::FINAL),
            PlayoffGameDomain::createForBracket(tournamentId: $tournamentId,
                                    round: PlayoffRound::THIRD,
                                    slot: PlayoffSlot::THIRD)
        ]);

        $playersPool = collect($playerIds)->shuffle();

        $games = $games->map(function ($game) use ($playersPool) {
            if($game->round === PlayoffRound::EIGHT)
            {
                return $game->withPlayerIds(player1Id: $playersPool->shift(),
                                            player2Id: $playersPool->shift());
            }

            return $game;
        });

        return $games;
    }
}
