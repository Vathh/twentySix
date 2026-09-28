<?php

namespace App\ViewModels;

use App\Domain\Game\GroupGameDomain;
use App\Domain\Game\PlayoffGameDomain;
use App\Domain\GroupStandingDomain;
use App\Domain\PlayerDomain;
use App\Domain\SeasonDomain;
use App\Domain\Tournament\TournamentDomain;
use App\Domain\Tournament\TournamentResultDomain;
use App\Models\Player\Player;
use App\Models\Tournament\Tournament;
use App\Queries\TournamentVisitHighlightsQuery;
use Illuminate\Support\Collection;

class TournamentDataViewModel
{
    public function __construct(
        public Tournament $tournament
    ) {}

    /**
     * @return array<GroupStandingDomain>
     */
    public function groupStandings(): array
    {
        $result = [];

        $standingsDomains = $this->tournament
            ->groupStandings
            ->map(fn ($standing) => GroupStandingDomain::fromEloquent($standing, ['player']));

        foreach ($standingsDomains as $standing) {
            $result[$standing->groupNumber][$standing->player->id] = $standing;
        }

        return $result;
    }

    /**
     * @return array<GroupGameDomain>
     */
    public function games(): array
    {
        $result = [];

        $gameDomains = $this->tournament
            ->games->map(function ($game) {
                $with = ['player1', 'player2', 'winner'];
                if ($game->relationLoaded('referee')) {
                    $with[] = 'referee';
                }

                return GroupGameDomain::fromEloquent($game, $with);
            });

        foreach ($gameDomains as $game) {
            $result[$game->groupNumber][$game->player1->id][$game->player2->id] = $game;
        }

        foreach ($gameDomains as $game) {
            $result[$game->groupNumber][$game->player2->id][$game->player1->id] = $game;
        }

        return $result;
    }

    public function playoffGames(?\App\Enums\BracketSide $side = null): array
    {
        $result = [];

        $playoffGameDomains = $this->tournament
            ->playoffGames
            ->map(fn ($game) => PlayoffGameDomain::fromEloquent($game, ['player1', 'player2', 'winner']));

        foreach ($playoffGameDomains as $game) {
            if ($side !== null) {
                if ($game->bracketSide !== $side) {
                    continue;
                }
            } elseif ($game->bracketSide === \App\Enums\BracketSide::Consolation) {
                continue;
            }
            $result[$game->round][] = $game;
        }

        return $result;
    }

    public function hasConsolationPlayoff(): bool
    {
        return $this->tournament->playoffGames
            ->contains(fn ($game) => ($game->bracket_side?->value ?? (string) $game->bracket_side) === 'consolation');
    }

    /**
     * @return array<PlayerDomain>
     */
    public function players(): array
    {
        $result = [];

        foreach ($this->tournament->groupStandings as $standing) {
            $result[$standing->group_number][] = PlayerDomain::fromEloquent($standing->player);
        }

        return $result;
    }

    public function groupNumbers(): Collection
    {
        return $this->tournament
            ->groupStandings
            ->map(fn ($standing) => $standing->group_number)
            ->unique()
            ->sort()
            ->collect();
    }

    /**
     * Plakietka PLAYOFF dopiero gdy grupa jest rozegrana do końca.
     * W trakcie grupy miejsca się zmieniają, więc nikt nie jest wyróżniony.
     *
     * @return array<int, array{complete: bool, advanceCount: int, advancingPlayerIds: list<int>}>
     */
    public function groupPlayoffHighlights(): array
    {
        $advancesList = $this->tournament->group_advances;
        if (! is_array($advancesList) || $advancesList === []) {
            return [];
        }

        $gamesByGroup = $this->games();
        $standingsByGroup = $this->groupStandings();
        $result = [];

        foreach ($this->groupNumbers() as $groupNumber) {
            $advanceCount = (int) ($advancesList[$groupNumber - 1] ?? $advancesList[(string) ($groupNumber - 1)] ?? 0);
            $complete = $this->isGroupFinished($gamesByGroup[$groupNumber] ?? []);
            $advancingPlayerIds = [];

            if ($complete && $advanceCount > 0) {
                foreach ($standingsByGroup[$groupNumber] ?? [] as $playerId => $standing) {
                    if (
                        $standing->gamesPlayed > 0
                        && $standing->place > 0
                        && $standing->place <= $advanceCount
                    ) {
                        $advancingPlayerIds[] = (int) $playerId;
                    }
                }
            }

            $result[(int) $groupNumber] = [
                'complete' => $complete,
                'advanceCount' => $advanceCount,
                'advancingPlayerIds' => $advancingPlayerIds,
            ];
        }

        return $result;
    }

    /**
     * @param  array<int, array<int, GroupGameDomain>>  $pairMatrix
     */
    private function isGroupFinished(array $pairMatrix): bool
    {
        $seen = [];
        $hasGames = false;

        foreach ($pairMatrix as $opponents) {
            foreach ($opponents as $game) {
                if (isset($seen[$game->id])) {
                    continue;
                }
                $seen[$game->id] = true;
                $hasGames = true;
                if (! $game->isFinished()) {
                    return false;
                }
            }
        }

        return $hasGames;
    }

    public function tournament(): TournamentDomain
    {
        return TournamentDomain::fromEloquent($this->tournament, ['pointScheme', 'season']);
    }

    public function season(): ?SeasonDomain
    {
        if ($this->tournament->season === null) {
            return null;
        }

        return SeasonDomain::fromEloquent($this->tournament->season, ['organization', 'admins']);
    }

    /**
     * @return Collection<int, array{player: PlayerDomain, max: int, one_seventy: int, qf: list<AchievementDomain|\stdClass>, hf: list<AchievementDomain>}>
     */
    public function achievements(): Collection
    {
        $result = [];

        $this->applyVisitHighlights($result);

        $qfFromScoring = (new TournamentVisitHighlightsQuery)
            ->quickFinishesForTournaments([(int) $this->tournament->id]);
        if ($qfFromScoring !== []) {
            $playersById = Player::query()
                ->whereIn('id', array_keys($qfFromScoring))
                ->get()
                ->keyBy('id');

            foreach ($qfFromScoring as $playerId => $dartCounts) {
                if (! isset($result[$playerId]['player'])) {
                    $player = $playersById->get($playerId);
                    if ($player === null) {
                        continue;
                    }
                    $result[$playerId]['player'] = PlayerDomain::fromEloquent($player);
                    $result[$playerId]['max'] = 0;
                    $result[$playerId]['one_seventy'] = 0;
                    $result[$playerId]['hf'] = [];
                }

                $result[$playerId]['qf'] = array_map(
                    static fn (int $darts) => (object) ['value' => $darts],
                    $dartCounts,
                );
            }
        }

        foreach ($result as $playerId => $row) {
            $empty = ($row['max'] ?? 0) === 0
                && ($row['one_seventy'] ?? 0) === 0
                && ($row['qf'] ?? []) === []
                && ($row['hf'] ?? []) === [];
            if ($empty) {
                unset($result[$playerId]);
            }
        }

        return collect($result);
    }

    /**
     * @param  array<int, array{player?: PlayerDomain, max: int, one_seventy: int, qf: list, hf: list}>  $result
     */
    private function applyVisitHighlights(array &$result): void
    {
        $highlights = (new TournamentVisitHighlightsQuery)->forTournaments([(int) $this->tournament->id]);
        $byPlayer = $highlights['byPlayer'];
        if ($byPlayer === []) {
            return;
        }

        $missingPlayerIds = [];
        foreach ($byPlayer as $playerId => $stats) {
            if (isset($result[$playerId]['player'])) {
                continue;
            }
            if ($stats['max'] === 0 && $stats['one_seventy'] === 0 && $stats['hf'] === []) {
                continue;
            }
            $missingPlayerIds[] = $playerId;
        }

        $playersById = $missingPlayerIds === []
            ? collect()
            : Player::query()->whereIn('id', $missingPlayerIds)->get()->keyBy('id');

        foreach ($byPlayer as $playerId => $stats) {
            if (! isset($result[$playerId]['player'])) {
                $player = $playersById->get($playerId);
                if ($player === null) {
                    continue;
                }
                if ($stats['max'] === 0 && $stats['one_seventy'] === 0 && $stats['hf'] === []) {
                    continue;
                }
                $result[$playerId]['player'] = PlayerDomain::fromEloquent($player);
                $result[$playerId]['qf'] = [];
            }

            $result[$playerId]['max'] = $stats['max'];
            $result[$playerId]['one_seventy'] = $stats['one_seventy'];
            $result[$playerId]['hf'] = array_map(
                static fn (int $score) => (object) ['value' => $score],
                $stats['hf'],
            );
        }
    }

    public function results(): Collection
    {
        $resultDomains = $this->tournament
            ->results
            ->map(fn ($result) => TournamentResultDomain::fromEloquent($result, ['player']));

        return $resultDomains
            ->sortBy(fn ($result) => $result->place ?? PHP_INT_MAX)
            ->values()
            ->map(fn ($result) => [
                'player' => $result->player,
                'place' => $result->place,
                'points' => $result->points,
                'stage' => $result->eliminationStage,
                'stageLabel' => $this->resultStageLabel($result),
            ]);
    }

    private function resultStageLabel(\App\Domain\Tournament\TournamentResultDomain $result): ?string
    {
        $stage = $result->eliminationStage;
        if ($stage === null) {
            return null;
        }

        $mainSize = $this->tournament->playoff_bracket_size;
        $isConsolation = (bool) $this->tournament->has_consolation_bracket
            && $mainSize !== null
            && $result->place !== null
            && $result->place > (int) $mainSize
            && $stage !== \App\Enums\GameStage::GROUP;

        return \App\Support\Tournament\PlayoffRoundLabel::resultLabel(
            $stage->value,
            $isConsolation ? \App\Enums\BracketSide::Consolation : \App\Enums\BracketSide::Main,
        );
    }
}
