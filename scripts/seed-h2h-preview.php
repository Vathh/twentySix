<?php

use App\Domain\GameScoring\MatchFormat;
use App\Enums\GameStatus;
use App\Enums\LeagueCalendarMode;
use App\Enums\LeagueGamePurpose;
use App\Enums\LeagueGameStatus;
use App\Enums\LeagueSeasonStatus;
use App\Enums\TournamentStatus;
use App\Models\Game\Game;
use App\Models\Game\GameLeg;
use App\Models\League\League;
use App\Models\League\LeagueGame;
use App\Models\League\LeagueSeason;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\QuickGame\QuickGame;
use App\Models\QuickGame\QuickGameResult;
use App\Models\Tournament\Tournament;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$annaName = 'Anna Podgląd';
$bartekName = 'Bartek Podgląd';

DB::transaction(function () use ($annaName, $bartekName) {
    $existing = Player::query()->whereIn('name', [$annaName, $bartekName])->pluck('id');
    if ($existing->isNotEmpty()) {
        $ids = $existing->all();
        $gameIds = Game::query()
            ->where(function ($query) use ($ids) {
                $query->whereIn('player1_id', $ids)->orWhereIn('player2_id', $ids);
            })
            ->pluck('id');
        $playoffIds = PlayoffGame::query()
            ->where(function ($query) use ($ids) {
                $query->whereIn('player1_id', $ids)->orWhereIn('player2_id', $ids);
            })
            ->pluck('id');
        $leagueIds = LeagueGame::query()
            ->where(function ($query) use ($ids) {
                $query->whereIn('player1_id', $ids)->orWhereIn('player2_id', $ids);
            })
            ->pluck('id');
        $quickIds = QuickGame::query()
            ->where(function ($query) use ($ids) {
                $query->whereIn('player1_id', $ids)->orWhereIn('player2_id', $ids);
            })
            ->pluck('id');

        GameLeg::query()->whereIn('game_id', $gameIds)
            ->orWhereIn('playoff_game_id', $playoffIds)
            ->orWhereIn('league_game_id', $leagueIds)
            ->orWhereIn('quick_game_id', $quickIds)
            ->delete();
        QuickGameResult::query()->whereIn('quick_game_id', $quickIds)->delete();
        Game::query()->whereIn('id', $gameIds)->delete();
        PlayoffGame::query()->whereIn('id', $playoffIds)->delete();
        LeagueGame::query()->whereIn('id', $leagueIds)->delete();
        QuickGame::query()->whereIn('id', $quickIds)->delete();
        Player::query()->whereIn('id', $ids)->delete();
    }

    Tournament::query()->where('name', 'Puchar Podglądu')->forceDelete();
    $leagueIds = League::withTrashed()->where('name', 'Liga Podglądu')->pluck('id');
    if ($leagueIds->isNotEmpty()) {
        $seasonIds = LeagueSeason::query()->whereIn('league_id', $leagueIds)->pluck('id');
        LeagueGame::query()->whereIn('league_season_id', $seasonIds)->delete();
        LeagueSeason::query()->whereIn('id', $seasonIds)->delete();
        League::withTrashed()->whereIn('id', $leagueIds)->forceDelete();
    }
    Organization::withTrashed()->where('name', 'Podgląd H2H')->forceDelete();

    $anna = Player::create(['name' => $annaName]);
    $bartek = Player::create(['name' => $bartekName]);
    $organization = Organization::create(['name' => 'Podgląd H2H', 'description' => 'Dane tylko do podglądu sekcji rywalizacji.']);
    $tournament = Tournament::create([
        'name' => 'Puchar Podglądu',
        'organization_id' => $organization->id,
        'date' => '2026-04-02',
        'status' => TournamentStatus::FINISHED,
    ]);
    $league = League::create([
        'organization_id' => $organization->id,
        'name' => 'Liga Podglądu',
        'description' => '',
    ]);
    $season = LeagueSeason::create([
        'league_id' => $league->id,
        'name' => '2026',
        'status' => LeagueSeasonStatus::IN_PROGRESS,
        'calendar_mode' => LeagueCalendarMode::DEADLINE,
        'rounds_each' => 1,
        'start_date' => '2023-01-01',
        'end_date' => '2026-12-31',
    ]);
    $format = MatchFormat::default()->toDatabaseColumns();

    $stamp = function ($game, string $at): void {
        $game->forceFill(['finished_at' => $at])->save();
    };

    $legs = function (array $owner, int $homeId, int $awayId, array $winnerIds, float $homeAvg, float $awayAvg, string $at): void {
        foreach ($winnerIds as $index => $winnerId) {
            GameLeg::create(array_merge($owner, [
                'leg_number' => $index + 1,
                'winner_id' => $winnerId,
                'player1_score' => 0,
                'player2_score' => 0,
                'player1_average' => $homeAvg,
                'player2_average' => $awayAvg,
                'player1_darts_thrown' => 21,
                'player2_darts_thrown' => 24,
                'finished_at' => $at,
            ]));
        }
    };

    $group = function (Player $home, Player $away, Player $winner, int $homeScore, int $awayScore, string $at, array $winnerIds, float $homeAvg, float $awayAvg) use ($tournament, $format, $stamp, $legs): void {
        $game = Game::create(array_merge($format, [
            'tournament_id' => $tournament->id,
            'player1_id' => $home->id,
            'player2_id' => $away->id,
            'group_number' => 1,
            'status' => GameStatus::FINISHED,
            'player1_score' => $homeScore,
            'player2_score' => $awayScore,
            'winner_id' => $winner->id,
        ]));
        $stamp($game, $at);
        $legs(['game_id' => $game->id], $home->id, $away->id, $winnerIds, $homeAvg, $awayAvg, $at);
    };

    $leagueGame = function (Player $home, Player $away, Player $winner, int $homeScore, int $awayScore, string $at, array $winnerIds, float $homeAvg, float $awayAvg) use ($season, $format, $stamp, $legs): void {
        $game = LeagueGame::create(array_merge($format, [
            'league_season_id' => $season->id,
            'purpose' => LeagueGamePurpose::REGULAR,
            'player1_id' => $home->id,
            'player2_id' => $away->id,
            'player1_score' => $homeScore,
            'player2_score' => $awayScore,
            'winner_id' => $winner->id,
            'status' => LeagueGameStatus::FINISHED,
        ]));
        $stamp($game, $at);
        $legs(['league_game_id' => $game->id], $home->id, $away->id, $winnerIds, $homeAvg, $awayAvg, $at);
    };

    $quick = function (Player $home, Player $away, Player $winner, int $homeScore, int $awayScore, string $at, float $homeAvg, float $awayAvg, array $winnerIds) use ($format, $legs): void {
        $game = QuickGame::create(array_merge($format, [
            'player1_id' => $home->id,
            'player2_id' => $away->id,
            'player1_score' => $homeScore,
            'player2_score' => $awayScore,
            'winner_id' => $winner->id,
            'status' => GameStatus::FINISHED,
        ]));
        QuickGame::query()->whereKey($game->id)->update(['created_at' => $at, 'updated_at' => $at]);
        QuickGameResult::create([
            'quick_game_id' => $game->id,
            'player_id' => $home->id,
            'score' => $homeScore,
            'place' => $winner->id === $home->id ? 1 : 2,
            'average' => $homeAvg,
        ]);
        QuickGameResult::create([
            'quick_game_id' => $game->id,
            'player_id' => $away->id,
            'score' => $awayScore,
            'place' => $winner->id === $away->id ? 1 : 2,
            'average' => $awayAvg,
        ]);
        $legs(['quick_game_id' => $game->id], $home->id, $away->id, $winnerIds, $homeAvg, $awayAvg, $at);
    };

    $leagueGame($bartek, $anna, $bartek, 2, 0, '2023-02-10 18:00:00', [$bartek->id, $bartek->id], 68.40, 55.20);
    $group($bartek, $anna, $anna, 1, 2, '2023-09-01 18:00:00', [$anna->id, $bartek->id, $anna->id], 61.10, 84.70);
    $quick($anna, $bartek, $bartek, 0, 2, '2024-03-12 19:00:00', 58.30, 91.40, [$bartek->id, $bartek->id]);
    $quick($anna, $bartek, $anna, 2, 1, '2024-07-20 19:00:00', 96.20, 73.50, [$anna->id, $bartek->id, $anna->id]);
    $group($anna, $bartek, $anna, 2, 0, '2024-11-02 17:00:00', [$anna->id, $anna->id], 88.00, 64.40);
    $leagueGame($anna, $bartek, $anna, 2, 1, '2025-02-14 18:30:00', [$bartek->id, $anna->id, $anna->id], 79.60, 70.10);
    $quick($bartek, $anna, $bartek, 2, 0, '2025-06-08 20:00:00', 86.70, 60.20, [$bartek->id, $bartek->id]);
    $group($anna, $bartek, $anna, 2, 0, '2025-10-19 16:00:00', [$anna->id, $anna->id], 102.40, 77.80);
    $quick($anna, $bartek, $anna, 2, 1, '2026-01-22 19:30:00', 93.15, 81.05, [$anna->id, $anna->id, $bartek->id]);

    $current = PlayoffGame::create(array_merge($format, [
        'tournament_id' => $tournament->id,
        'round' => 'FINAL',
        'slot' => 'FINAL-0',
        'player1_id' => $anna->id,
        'player2_id' => $bartek->id,
        'status' => GameStatus::FINISHED,
        'player1_score' => 2,
        'player2_score' => 1,
        'winner_id' => $anna->id,
    ]));
    $stamp($current, '2026-04-02 18:00:00');
    $legs(
        ['playoff_game_id' => $current->id],
        $anna->id,
        $bartek->id,
        [$anna->id, $bartek->id, $anna->id],
        90.50,
        74.25,
        '2026-04-02 18:00:00',
    );

    echo $current->id;
});
