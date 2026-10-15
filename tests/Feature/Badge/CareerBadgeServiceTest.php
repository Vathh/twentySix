<?php

namespace Tests\Feature\Badge;

use App\Domain\Badge\Career\CareerCatalog;
use App\Enums\GameStatus;
use App\Enums\LeagueCalendarMode;
use App\Enums\LeagueGamePurpose;
use App\Enums\LeagueGameStatus;
use App\Enums\LeagueSeasonStatus;
use App\Enums\TournamentStatus;
use App\Models\Badge\PlayerBadge;
use App\Models\Badge\PlayerBadgeEvent;
use App\Models\Game\Game;
use App\Models\League\League;
use App\Models\League\LeagueGame;
use App\Models\League\LeagueSeason;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\QuickGame\QuickGame;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentResult;
use App\Services\Badge\CareerBadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CareerBadgeServiceTest extends TestCase
{
    use RefreshDatabase;

    private CareerBadgeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CareerBadgeService::class);
    }

    public function test_streak_counts_league_wins_and_ignores_quick_games(): void
    {
        [$player, $opponent] = $this->players();
        $tournament = $this->tournament(TournamentStatus::GROUP);

        $this->groupGame($tournament, $player, $opponent, $player, '2026-03-01 10:00:00');
        $this->quickGame($player, $opponent, $opponent);
        $this->leagueGame($player, $opponent, $player, '2026-03-01 12:00:00');
        $this->groupGame($tournament, $player, $opponent, $opponent, '2026-03-01 13:00:00');

        $this->service->syncWinStreak($player->id);
        $this->assertSame(2, $this->times($player->id, CareerCatalog::WIN_STREAK));

        $this->groupGame($tournament, $player, $opponent, $player, '2026-03-01 14:00:00');
        $this->service->syncWinStreak($player->id);
        $this->assertSame(2, $this->times($player->id, CareerCatalog::WIN_STREAK));
    }

    public function test_correcting_a_middle_league_win_lowers_the_record(): void
    {
        [$player, $opponent] = $this->players();
        $tournament = $this->tournament(TournamentStatus::GROUP);

        $this->groupGame($tournament, $player, $opponent, $player, '2026-04-01 10:00:00');
        $leagueGame = $this->leagueGame($player, $opponent, $player, '2026-04-01 11:00:00');
        $this->groupGame($tournament, $player, $opponent, $player, '2026-04-01 12:00:00');

        $this->service->syncWinStreak($player->id);
        $this->assertSame(3, $this->times($player->id, CareerCatalog::WIN_STREAK));

        $leagueGame->winner_id = $opponent->id;
        $leagueGame->save();

        $this->service->syncWinStreak($player->id);
        $this->service->syncWinStreak($opponent->id);

        $this->assertSame(1, $this->times($player->id, CareerCatalog::WIN_STREAK));
        $this->assertSame(1, $this->times($opponent->id, CareerCatalog::WIN_STREAK));
    }

    public function test_finished_tournament_awards_podium_and_a_second_sync_does_not_double(): void
    {
        $champion = Player::query()->create(['name' => 'Mistrz']);
        $finalist = Player::query()->create(['name' => 'Finalista']);
        $thirdA = Player::query()->create(['name' => 'Trzeci A']);
        $thirdB = Player::query()->create(['name' => 'Trzeci B']);
        $fourth = Player::query()->create(['name' => 'Czwarty']);
        $ghost = Player::query()->create(['name' => 'Widmo']);
        $filler = Player::query()->create(['name' => 'Wypełnienie']);

        $open = $this->tournament(TournamentStatus::PLAYOFF);
        $this->groupGame($open, $champion, $filler, $champion, '2026-05-01 10:00:00');
        $this->place($open, $champion, 1);
        $this->service->syncFinishedTournament($open->id);
        $this->assertSame(0, $this->times($champion->id, CareerCatalog::TITLES));

        $finished = $this->tournament(TournamentStatus::FINISHED);
        $this->groupGame($finished, $champion, $finalist, $champion, '2026-05-02 10:00:00');
        $this->groupGame($finished, $thirdA, $thirdB, $thirdA, '2026-05-02 11:00:00');
        $this->groupGame($finished, $fourth, $filler, $filler, '2026-05-02 12:00:00');
        $this->place($finished, $champion, 1);
        $this->place($finished, $finalist, 2);
        $this->place($finished, $thirdA, 3);
        $this->place($finished, $thirdB, 3);
        $this->place($finished, $fourth, 4);
        $this->place($finished, $ghost, 1);

        $this->service->syncFinishedTournament($finished->id);
        $this->service->syncFinishedTournament($finished->id);

        $this->assertSame(1, $this->times($champion->id, CareerCatalog::TITLES));
        $this->assertSame(1, $this->times($champion->id, CareerCatalog::PODIUM));
        $this->assertSame(1, $this->times($champion->id, CareerCatalog::APPEARANCES));
        $this->assertSame(0, $this->times($finalist->id, CareerCatalog::TITLES));
        $this->assertSame(1, $this->times($finalist->id, CareerCatalog::PODIUM));
        $this->assertSame(1, $this->times($thirdA->id, CareerCatalog::PODIUM));
        $this->assertSame(1, $this->times($thirdB->id, CareerCatalog::PODIUM));
        $this->assertSame(0, $this->times($thirdA->id, CareerCatalog::TITLES));
        $this->assertSame(0, $this->times($fourth->id, CareerCatalog::PODIUM));
        $this->assertSame(1, $this->times($fourth->id, CareerCatalog::APPEARANCES));
        $this->assertSame(0, $this->times($ghost->id, CareerCatalog::TITLES));
        $this->assertSame(0, $this->times($ghost->id, CareerCatalog::APPEARANCES));
        $this->assertSame(1, PlayerBadgeEvent::query()
            ->where('player_id', $champion->id)
            ->where('badge_key', CareerCatalog::TITLES)
            ->count());

        TournamentResult::query()
            ->where('tournament_id', $finished->id)
            ->where('player_id', $champion->id)
            ->update(['place' => 2]);
        TournamentResult::query()
            ->where('tournament_id', $finished->id)
            ->where('player_id', $finalist->id)
            ->update(['place' => 1]);

        $this->service->syncFinishedTournament($finished->id);

        $this->assertSame(0, $this->times($champion->id, CareerCatalog::TITLES));
        $this->assertSame(1, $this->times($champion->id, CareerCatalog::PODIUM));
        $this->assertSame(1, $this->times($finalist->id, CareerCatalog::TITLES));
        $this->assertSame(1, $this->times($finalist->id, CareerCatalog::PODIUM));
    }

    /**
     * @return array{0: Player, 1: Player}
     */
    private function players(): array
    {
        return [
            Player::query()->create(['name' => 'Anna']),
            Player::query()->create(['name' => 'Bartek']),
        ];
    }

    private function tournament(TournamentStatus $status): Tournament
    {
        $organization = Organization::query()->create([
            'name' => 'Klub '.uniqid(),
            'description' => 'Opis',
        ]);
        $season = Season::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Sezon '.uniqid(),
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        return Tournament::query()->create([
            'season_id' => $season->id,
            'name' => 'Puchar '.uniqid(),
            'date' => '2026-06-01',
            'status' => $status,
        ]);
    }

    private function groupGame(
        Tournament $tournament,
        Player $player1,
        Player $player2,
        Player $winner,
        string $finishedAt,
    ): Game {
        return Game::query()->create([
            'tournament_id' => $tournament->id,
            'player1_id' => $player1->id,
            'player2_id' => $player2->id,
            'player1_score' => $winner->id === $player1->id ? 2 : 0,
            'player2_score' => $winner->id === $player2->id ? 2 : 0,
            'winner_id' => $winner->id,
            'group_number' => 1,
            'status' => GameStatus::FINISHED,
            'finished_at' => $finishedAt,
        ]);
    }

    private function leagueGame(Player $player1, Player $player2, Player $winner, string $finishedAt): LeagueGame
    {
        $organization = Organization::query()->create([
            'name' => 'Liga org '.uniqid(),
            'description' => 'Opis',
        ]);
        $league = League::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Liga '.uniqid(),
        ]);
        $season = LeagueSeason::query()->create([
            'league_id' => $league->id,
            'name' => 'Sezon ligowy',
            'status' => LeagueSeasonStatus::IN_PROGRESS,
            'calendar_mode' => LeagueCalendarMode::DEADLINE,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        return LeagueGame::query()->create([
            'league_season_id' => $season->id,
            'purpose' => LeagueGamePurpose::REGULAR,
            'player1_id' => $player1->id,
            'player2_id' => $player2->id,
            'player1_score' => $winner->id === $player1->id ? 2 : 0,
            'player2_score' => $winner->id === $player2->id ? 2 : 0,
            'winner_id' => $winner->id,
            'status' => LeagueGameStatus::FINISHED,
            'starting_score' => 501,
            'legs_to_win_set' => 2,
            'sets_to_win_match' => 1,
            'finished_at' => $finishedAt,
        ]);
    }

    private function quickGame(Player $player1, Player $player2, Player $winner): void
    {
        QuickGame::query()->create([
            'player1_id' => $player1->id,
            'player2_id' => $player2->id,
            'player1_score' => $winner->id === $player1->id ? 2 : 0,
            'player2_score' => $winner->id === $player2->id ? 2 : 0,
            'winner_id' => $winner->id,
            'status' => GameStatus::FINISHED,
        ]);
    }

    private function place(Tournament $tournament, Player $player, int $place): void
    {
        TournamentResult::query()->create([
            'tournament_id' => $tournament->id,
            'player_id' => $player->id,
            'place' => $place,
        ]);
    }

    private function times(int $playerId, string $key): int
    {
        return (int) (PlayerBadge::query()
            ->where('player_id', $playerId)
            ->where('category', 'career')
            ->where('badge_key', $key)
            ->value('times_earned') ?? 0);
    }
}
