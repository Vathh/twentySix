<?php

namespace App\Services\Player;

use App\Enums\AchievementType;
use App\Models\Player;
use App\Models\PlayerStat;
use App\Repositories\Player\PlayerStatRepository;
use Illuminate\Support\Collection;

class PlayerStatsService
{
    public function __construct(
        private PlayerStatRepository $playerStatRepository
    ) {
    }

    /**
     * Statystyki quick z cache (player_stats). Jeśli brak wpisu – przelicza i zapisuje.
     */
    public function getStoredQuickStats(Player $player): array
    {
        $stat = $this->playerStatRepository->findByPlayerId($player->id);
        if ($stat === null) {
            $this->recalculateAndSave($player->id);
            $stat = $this->playerStatRepository->findByPlayerId($player->id);
        }
        return $stat !== null ? $this->rowToQuickStatsArray($stat) : $this->emptyStatsArray();
    }

    /**
     * Statystyki turniejowe z cache (player_stats). Jeśli brak wpisu – przelicza i zapisuje.
     */
    public function getStoredTournamentStats(Player $player): array
    {
        $stat = $this->playerStatRepository->findByPlayerId($player->id);
        if ($stat === null) {
            $this->recalculateAndSave($player->id);
            $stat = $this->playerStatRepository->findByPlayerId($player->id);
        }
        return $stat !== null ? $this->rowToTournamentStatsArray($stat) : $this->emptyStatsArray();
    }

    /**
     * Przelicza statystyki gracza i zapisuje/aktualizuje w player_stats (przez Repository).
     */
    public function recalculateAndSave(int $playerId): void
    {
        $quick = $this->computeQuickStats($playerId);
        $tournament = $this->computeTournamentStats($playerId);
        $this->playerStatRepository->upsert($playerId, $quick, $tournament);
    }

    private function computeQuickStats(int $playerId): array
    {
        $data = $this->playerStatRepository->getDataForQuickStats($playerId);
        return $this->buildStatsArray($data['results'], $data['achievements']);
    }

    private function computeTournamentStats(int $playerId): array
    {
        $data = $this->playerStatRepository->getDataForTournamentStats($playerId);
        $stats = $this->buildStatsArray(null, $data['achievements']);
        $stats['matches'] = $data['matches_count'];
        if ($data['avg_from_legs'] !== null) {
            $stats['avg_three_darts'] = round($data['avg_from_legs'], 2);
        }
        return $stats;
    }

    private function rowToQuickStatsArray(PlayerStat $stat): array
    {
        return [
            'matches' => $stat->quick_matches,
            'avg_three_darts' => $stat->quick_avg_three_darts,
            'highest_hf' => $stat->quick_highest_hf,
            'fastest_qf' => $stat->quick_fastest_qf,
            'count_max' => $stat->quick_count_max,
            'count_170_plus' => $stat->quick_count_170_plus,
            'count_hf' => $stat->quick_count_hf,
            'count_qf' => $stat->quick_count_qf,
        ];
    }

    private function rowToTournamentStatsArray(PlayerStat $stat): array
    {
        return [
            'matches' => $stat->tournament_matches,
            'avg_three_darts' => $stat->tournament_avg_three_darts,
            'highest_hf' => $stat->tournament_highest_hf,
            'fastest_qf' => $stat->tournament_fastest_qf,
            'count_max' => $stat->tournament_count_max,
            'count_170_plus' => $stat->tournament_count_170_plus,
            'count_hf' => $stat->tournament_count_hf,
            'count_qf' => $stat->tournament_count_qf,
        ];
    }

    private function emptyStatsArray(): array
    {
        return [
            'matches' => 0,
            'avg_three_darts' => null,
            'highest_hf' => null,
            'fastest_qf' => null,
            'count_max' => 0,
            'count_170_plus' => 0,
            'count_hf' => 0,
            'count_qf' => 0,
        ];
    }

    /**
     * Buduje tablicę statystyk z surowych wyników i achievementów (logika biznesowa).
     *
     * @param object|null $results { matches: int, avg_average: ?float }
     * @param Collection<int, object> $achievements { type: string, value: ?int }
     */
    private function buildStatsArray(?object $results, Collection $achievements): array
    {
        $matches = $results ? (int) $results->matches : 0;
        $avgThreeDarts = $results && $results->avg_average !== null
            ? round((float) $results->avg_average, 2)
            : null;

        $max = 0;
        $oneSeventy = 0;
        $hf = 0;
        $qf = 0;
        $highestHf = null;
        $fastestQf = null;

        foreach ($achievements as $a) {
            $type = $a->type;
            $value = $a->value !== null ? (int) $a->value : null;
            if ($type === AchievementType::MAX->value) {
                $max++;
            } elseif ($type === AchievementType::ONE_SEVENTY->value) {
                $oneSeventy++;
            } elseif ($type === AchievementType::HF->value) {
                $hf++;
                if ($value !== null && ($highestHf === null || $value > $highestHf)) {
                    $highestHf = $value;
                }
            } elseif ($type === AchievementType::QF->value) {
                $qf++;
                if ($value !== null && ($fastestQf === null || $value < $fastestQf)) {
                    $fastestQf = $value;
                }
            }
        }

        return [
            'matches' => $matches,
            'avg_three_darts' => $avgThreeDarts,
            'highest_hf' => $highestHf,
            'fastest_qf' => $fastestQf,
            'count_max' => $max,
            'count_170_plus' => $oneSeventy,
            'count_hf' => $hf,
            'count_qf' => $qf,
        ];
    }
}











