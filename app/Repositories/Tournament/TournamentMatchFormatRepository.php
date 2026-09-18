<?php

namespace App\Repositories\Tournament;

use App\Domain\GameScoring\MatchFormat;
use App\Enums\BracketSide;
use App\Enums\GameStage;
use App\Models\Tournament\TournamentMatchFormat;
use Illuminate\Support\Collection;

class TournamentMatchFormatRepository
{
    /**
     * @param  array<string, array<string, mixed>>  $formatsByStage  stage value => format fields
     */
    public function saveForTournament(
        int $tournamentId,
        array $formatsByStage,
        BracketSide $bracketSide = BracketSide::Main,
    ): void {
        foreach ($formatsByStage as $stage => $fields) {
            $format = MatchFormat::fromArray($fields);
            $format->validateForStage(GameStage::from($stage));

            TournamentMatchFormat::updateOrCreate(
                [
                    'tournament_id' => $tournamentId,
                    'bracket_side' => $bracketSide,
                    'stage' => $stage,
                ],
                $format->toDatabaseColumns(),
            );
        }
    }

    public function getForTournament(int $tournamentId, ?BracketSide $bracketSide = null): Collection
    {
        $query = TournamentMatchFormat::where('tournament_id', $tournamentId);
        if ($bracketSide !== null) {
            $query->where('bracket_side', $bracketSide);
        }

        return $query->get();
    }

    public function getForStage(
        int $tournamentId,
        GameStage $stage,
        BracketSide $bracketSide = BracketSide::Main,
    ): MatchFormat {
        $row = TournamentMatchFormat::where('tournament_id', $tournamentId)
            ->where('bracket_side', $bracketSide)
            ->where('stage', $stage->value)
            ->first();

        return $row ? $row->toMatchFormat() : MatchFormat::default();
    }
}
