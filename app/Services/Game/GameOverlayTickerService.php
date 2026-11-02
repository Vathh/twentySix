<?php

namespace App\Services\Game;

use App\Enums\BracketSide;
use App\Enums\GameKind;
use App\Enums\GameStage;
use App\Enums\GameStatus;
use App\Models\Game\Game;
use App\Models\PlayoffGame\PlayoffGame;
use App\Repositories\Game\GameRepository;
use App\Repositories\PlayoffGame\PlayoffGameRepository;
use App\Support\Tournament\PlayoffRoundLabel;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class GameOverlayTickerService
{
    /** Ile wynik końcowy zostaje na pasku, zanim wpis zniknie. */
    private const FINISHED_HOLD_MINUTES = 3;

    public function __construct(
        private GameRepository $games,
        private PlayoffGameRepository $playoffGames,
    ) {}

    /**
     * Mecze turnieju w trakcie i świeżo zakończone. Pasek jest jeden na turniej,
     * więc mecz ze streamu też na nim jest.
     *
     * @return list<array{
     *     key: string,
     *     label: string,
     *     player1: string,
     *     player2: string,
     *     score1: int,
     *     score2: int,
     *     setLegs1: ?int,
     *     setLegs2: ?int,
     *     finished: bool
     * }>
     */
    public function itemsForTournament(int $tournamentId): array
    {
        $finishedSince = now()->subMinutes(self::FINISHED_HOLD_MINUTES);
        $items = [];

        foreach ($this->games->listForOverlayTicker($tournamentId, $finishedSince) as $game) {
            $item = $this->itemFrom($game, 'group', 'Gr. '.(int) $game->group_number);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        foreach ($this->playoffGames->listForOverlayTicker($tournamentId, $finishedSince) as $game) {
            $item = $this->itemFrom($game, 'playoff', $this->playoffLabel($game));
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     player1: string,
     *     player2: string,
     *     score1: int,
     *     score2: int,
     *     setLegs1: ?int,
     *     setLegs2: ?int,
     *     finished: bool
     * }>
     */
    public function items(GameKind $kind, int $gameId): array
    {
        return $this->itemsForTournament($this->tournamentId($kind, $gameId));
    }

    public function tournamentIdOf(GameKind $kind, int $gameId): int
    {
        return $this->tournamentId($kind, $gameId);
    }

    private function tournamentId(GameKind $kind, int $gameId): int
    {
        $game = match ($kind) {
            GameKind::GROUP => $this->games->findModel($gameId),
            GameKind::PLAYOFF => $this->playoffGames->findModel($gameId),
            default => throw (new ModelNotFoundException)->setModel(Game::class, [$gameId]),
        };

        $tournamentId = (int) $game->tournament_id;
        if ($tournamentId < 1) {
            throw (new ModelNotFoundException)->setModel($game::class, [$gameId]);
        }

        return $tournamentId;
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     player1: string,
     *     player2: string,
     *     score1: int,
     *     score2: int,
     *     setLegs1: ?int,
     *     setLegs2: ?int,
     *     finished: bool
     * }|null
     */
    private function itemFrom(Game|PlayoffGame $game, string $kind, string $label): ?array
    {
        $player1 = $game->player1;
        $player2 = $game->player2;
        if ($player1 === null || $player2 === null || $player1->is_bye || $player2->is_bye) {
            return null;
        }

        $finished = $game->status === GameStatus::FINISHED;
        $multiSet = ! $finished && (int) $game->sets_to_win_match > 1;

        return [
            'key' => $kind.':'.$game->id,
            'label' => $label,
            'player1' => $player1->name,
            'player2' => $player2->name,
            'score1' => (int) $game->player1_score,
            'score2' => (int) $game->player2_score,
            'setLegs1' => $multiSet ? (int) $game->player1_legs_in_set : null,
            'setLegs2' => $multiSet ? (int) $game->player2_legs_in_set : null,
            'finished' => $finished,
        ];
    }

    private function playoffLabel(PlayoffGame $game): string
    {
        $side = $game->bracket_side instanceof BracketSide
            ? $game->bracket_side
            : BracketSide::tryFrom((string) $game->bracket_side);
        $core = $this->roundLabel((string) $game->round);

        if ($side === BracketSide::Consolation) {
            return 'Pocieszenie · '.$core;
        }

        return $core;
    }

    private function roundLabel(string $round): string
    {
        $stage = GameStage::tryFrom($round);
        if ($stage !== null) {
            return $stage->label();
        }
        if ($round === 'GF' || $round === 'GF1') {
            return 'Finał';
        }
        if ($round === 'GF2') {
            return 'Finał · reset';
        }
        if (preg_match('/^W(\d+)$/', $round, $matches) === 1) {
            return 'Wygrani · runda '.((int) $matches[1] + 1);
        }
        if (preg_match('/^L(\d+)$/', $round, $matches) === 1) {
            return 'Przegrani · runda '.((int) $matches[1] + 1);
        }

        return PlayoffRoundLabel::label($round);
    }
}
