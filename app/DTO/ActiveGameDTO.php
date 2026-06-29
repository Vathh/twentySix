<?php /** @noinspection PhpParamsInspection */

namespace App\DTO;

use App\Domain\Game\GroupGameDomain;
use App\Domain\Game\PlayoffGameDomain;
use App\Models\PlayoffGame\PlayoffGame;

class ActiveGameDTO
{

    public function __construct(
        public int $id,
        public int $tournamentId,
        public string $type,
        public array $player1,
        public array $player2,
        public ?int $groupNumber,
        public ?string $round = null,
        public ?string $roundLabel = null,
    )
    {
    }

    /**
     * @param GroupGameDomain $game
     * @return ActiveGameDTO
     */
    public static function fromGame(GroupGameDomain $game): ActiveGameDTO
    {
        return new self(
            id: $game->id,
            tournamentId: $game->tournament?->id ?? 0,
            type: 'group',
            player1: [
                'id' => $game->player1->id,
                'name' => $game->player1->name,
            ],
            player2: [
                'id' => $game->player2->id,
                'name' => $game->player2->name,
            ],
            groupNumber: $game->groupNumber,
            round: null,
            roundLabel: null,
        );
    }

    /**
     * @param PlayoffGame $game
     * @return ActiveGameDTO|null
     */
    public static function fromPlayoffGame(PlayoffGame $game): ?ActiveGameDTO
    {
        return self::fromPlayoffGameDomain(
            PlayoffGameDomain::fromEloquent($game, ['tournament', 'player1', 'player2']),
        );
    }

    /**
     * @param PlayoffGameDomain $game
     * @return ActiveGameDTO|null null gdy brak obu graczy (slot TBD w drabince)
     */
    public static function fromPlayoffGameDomain(PlayoffGameDomain $game): ?ActiveGameDTO
    {
        if ($game->player1 === null || $game->player2 === null) {
            return null;
        }

        return new self(
            id: $game->id,
            tournamentId: $game->tournamentId ?? $game->tournament?->id ?? 0,
            type: 'playoff',
            player1: [
                'id' => $game->player1->id,
                'name' => $game->player1->name,
            ],
            player2: [
                'id' => $game->player2->id,
                'name' => $game->player2->name,
            ],
            groupNumber: null,
            round: $game->round->value,
            roundLabel: $game->round->label(),
        );
    }
}

