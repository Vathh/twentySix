<?php

namespace App\Repositories\Tournament;

use App\Enums\GameStage;
use App\Models\Tournament\TournamentMatchFormat;
use App\Support\GameScoring\MatchFormat;
use Illuminate\Support\Collection;

class TournamentMatchFormatRepository
{
    /**
     * @param  array<string, array<string, mixed>>  $formatsByStage  stage value => format fields
     */
    public function saveForTournament(int $tournamentId, array $formatsByStage): void
    {
        foreach ($formatsByStage as $stage => $fields) {
            $format = MatchFormat::fromArray($fields);
            $format->validateForStage(GameStage::from($stage));

            TournamentMatchFormat::updateOrCreate(
                ['tournament_id' => $tournamentId, 'stage' => $stage],
                $format->toDatabaseColumns(),
            );
        }
    }

    public function getForTournament(int $tournamentId): Collection
    {
        return TournamentMatchFormat::where('tournament_id', $tournamentId)->get();
    }

    public function getForStage(int $tournamentId, GameStage $stage): MatchFormat
    {
        $row = TournamentMatchFormat::where('tournament_id', $tournamentId)
            ->where('stage', $stage->value)
            ->first();

        return $row ? $row->toMatchFormat() : MatchFormat::default();
    }
}
