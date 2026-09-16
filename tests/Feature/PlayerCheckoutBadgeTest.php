<?php

namespace Tests\Feature;

use App\Domain\Badge\BadgeCategory;
use App\Domain\Badge\Checkout\CheckoutCatalog;
use App\DTO\GameScoring\CloseLegPlayerStatsDTO;
use App\DTO\GameScoring\RecordVisitDTO;
use App\Enums\GameKind;
use App\Enums\GameStatus;
use App\Enums\LeagueCalendarMode;
use App\Enums\LeagueGamePurpose;
use App\Enums\LeagueGameStatus;
use App\Enums\LeagueSeasonStatus;
use App\Enums\TournamentStatus;
use App\Models\Badge\PlayerBadge;
use App\Models\Game\Game;
use App\Models\Game\GameLeg;
use App\Models\Game\GameVisit;
use App\Models\GroupStanding\GroupStanding;
use App\Models\League\League;
use App\Models\League\LeagueDivision;
use App\Models\League\LeagueGame;
use App\Models\League\LeagueSeason;
use App\Models\League\LeagueSeasonDivision;
use App\Models\League\LeagueSeasonParticipant;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\QuickGame\QuickGame;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Services\Badge\BadgeAwardService;
use App\Services\Badge\CheckoutWheelAssembler;
use App\Services\GameScoring\GameResultCorrectionService;
use App\Services\GameScoring\GameScoringService;
use App\Services\League\LeagueSeasonService;
use App\Services\Player\PlayerService;
use App\Support\GameScoring\GameScoringContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayerCheckoutBadgeTest extends TestCase
{
    use RefreshDatabase;

    private PlayerService $playerService;

    private BadgeAwardService $awardService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->playerService = app(PlayerService::class);
        $this->awardService = app(BadgeAwardService::class);
    }

    #[Test]
    public function scoring_finish_unlocks_121_and_second_hit_increments(): void
    {
        [$p1, $p2, $tournament] = $this->tournamentPair();
        $game = $this->createGroupGame($tournament, $p1, $p2);
        $this->finishGroupByScoring($game, $p1, $p2, checkout: 121, secondCheckout: 40);

        $this->assertDatabaseHas('player_badges', [
            'player_id' => $p1->id,
            'category' => BadgeCategory::Checkout->value,
            'badge_key' => '121',
            'times_earned' => 1,
        ]);

        $game2 = $this->createGroupGame($tournament, $p1, $p2);
        $this->finishGroupByScoring($game2, $p1, $p2, checkout: 121, secondCheckout: 40);

        $this->assertSame(2, (int) PlayerBadge::query()->where('player_id', $p1->id)->where('badge_key', '121')->value('times_earned'));
        $this->assertDatabaseCount('player_badge_events', 2);

        $item = collect(app(CheckoutWheelAssembler::class)->itemsForPlayer((int) $p1->id))->firstWhere('key', '121');
        $this->assertSame(2, $item['timesEarned']);
        $this->assertNotNull($item['lastEarnedAt']);
        $this->assertSame('group', $item['lastGame']['type']);
        $this->assertSame('Rywal', $item['lastGame']['opponents']);
    }

    #[Test]
    public function checkout_40_and_301_and_quick_do_not_award(): void
    {
        [$p1, $p2, $tournament] = $this->tournamentPair();

        $game40 = $this->createGroupGame($tournament, $p1, $p2);
        $this->finishGroupByScoring($game40, $p1, $p2, checkout: 40);
        $this->assertDatabaseCount('player_badges', 0);

        $game301 = $this->createGroupGame($tournament, $p1, $p2, startingScore: 301);
        $this->finishGroupByScoring($game301, $p1, $p2, checkout: 121);
        $this->assertDatabaseCount('player_badges', 0);

        $quick = QuickGame::create([
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'status' => GameStatus::FINISHED,
            'starting_score' => 501,
            'game_type' => 'x01',
            'player1_score' => 2,
            'player2_score' => 0,
            'winner_id' => $p1->id,
        ]);
        $leg = GameLeg::create([
            'quick_game_id' => $quick->id,
            'leg_number' => 1,
            'winner_id' => $p1->id,
            'player1_score' => 501,
            'player2_score' => 0,
            'finished_at' => now(),
        ]);
        $this->visit($leg, $p1->id, 121, true);
        $this->awardService->awardForFinishedGame(GameScoringContext::fromQuickGame($quick), $quick);
        $this->assertDatabaseCount('player_badges', 0);
        $this->assertDatabaseCount('badge_game_commits', 2);
    }

    #[Test]
    public function undo_before_finish_skips_checkout_and_retry_does_not_duplicate(): void
    {
        [$p1, $p2, $tournament] = $this->tournamentPair();
        $game = $this->createGroupGame($tournament, $p1, $p2);
        $scoring = app(GameScoringService::class);
        $context = GameScoringContext::fromGroupGame($game);

        $state = $scoring->startLeg($context, $game, false, false);
        $legId = (int) $state['currentLeg']['id'];
        $this->playDownTo($scoring, $context, $game, $legId, (int) $p1->id, 121, 501);
        $scoring->recordVisit($context, $game, $legId, $this->checkoutVisitDto($p1->id, 121));
        $scoring->undoLastVisit($context, $game, $legId);
        $scoring->recordVisit($context, $game, $legId, $this->openVisitDto($p1->id, 60, 121));
        $this->serviceCloseLeg($scoring, $context, $game, $legId, $p1, $p2);
        $this->closeScoringLegWithoutCheckout($scoring, $game->fresh(), $p1, $p2);

        $game->refresh();
        $this->assertSame(GameStatus::FINISHED, $game->status);
        $this->assertDatabaseCount('player_badges', 0);

        $this->awardService->awardForFinishedGame(GameScoringContext::fromGroupGame($game->fresh()), $game->fresh());
        $this->assertDatabaseCount('player_badge_events', 0);
        $this->assertSame(1, \App\Models\Badge\BadgeGameCommit::query()->count());
    }

    #[Test]
    public function guest_checkout_is_ignored(): void
    {
        $user = User::factory()->create();
        $this->playerService->create('Host', $user->id);
        $p1 = Player::query()->where('user_id', $user->id)->firstOrFail();
        $guest = Player::create(['name' => 'Gość']);
        $tournament = $this->tournament();
        $game = $this->createGroupGame($tournament, $guest, $p1);
        $this->seedFinishedCheckout($game, 'game_id', $guest->id, 121);
        $this->awardService->awardForFinishedGame(GameScoringContext::fromGroupGame($game->fresh()), $game->fresh());

        $this->assertDatabaseMissing('player_badges', ['player_id' => $guest->id]);
        $this->assertDatabaseCount('player_badges', 0);
    }

    #[Test]
    public function tournament_correction_retracts_and_locks_again(): void
    {
        [$p1, $p2, $tournament] = $this->tournamentPair();
        $game = $this->createGroupGame($tournament, $p1, $p2, status: GameStatus::FINISHED);
        $game->update(['player1_score' => 2, 'player2_score' => 0, 'winner_id' => $p1->id]);
        $this->seedFinishedCheckout($game, 'game_id', $p1->id, 121);
        $this->awardService->awardForFinishedGame(GameScoringContext::fromGroupGame($game->fresh()), $game->fresh());
        $this->assertSame(1, (int) PlayerBadge::query()->where('badge_key', '121')->value('times_earned'));

        app(GameResultCorrectionService::class)->applyFromWeb(GameKind::GROUP, $game->id, 2, 0);

        $this->assertDatabaseCount('player_badge_events', 0);
        $this->assertDatabaseCount('player_badges', 0);
        $this->assertDatabaseCount('badge_game_commits', 0);
    }

    #[Test]
    public function league_walkover_and_withdraw_retract(): void
    {
        $a = $this->registered('Ada');
        $b = $this->registered('Ben');
        $season = $this->openLeagueSeason($a, $b);
        $game = LeagueGame::query()->where('league_season_id', $season->id)->firstOrFail();
        $game->update([
            'status' => LeagueGameStatus::FINISHED,
            'starting_score' => 501,
            'game_type' => 'x01',
            'player1_score' => 2,
            'player2_score' => 0,
            'winner_id' => $a->id,
        ]);
        $this->seedFinishedCheckout($game, 'league_game_id', $a->id, 121);
        $this->awardService->awardForFinishedGame(GameScoringContext::fromLeagueGame($game->fresh()), $game->fresh());
        $this->assertDatabaseCount('player_badges', 1);

        app(LeagueSeasonService::class)->recordWalkover($game->id, 'single', $b->id);
        $this->assertDatabaseCount('player_badges', 0);

        $game2 = LeagueGame::create([
            'league_season_id' => $season->id,
            'league_season_division_id' => $season->divisions->first()->id,
            'purpose' => LeagueGamePurpose::REGULAR,
            'player1_id' => $a->id,
            'player2_id' => $b->id,
            'status' => LeagueGameStatus::FINISHED,
            'starting_score' => 501,
            'game_type' => 'x01',
            'legs_to_win_set' => 2,
            'sets_to_win_match' => 1,
            'player1_score' => 2,
            'player2_score' => 0,
            'winner_id' => $a->id,
        ]);
        $this->seedFinishedCheckout($game2, 'league_game_id', $a->id, 140);
        $this->awardService->awardForFinishedGame(GameScoringContext::fromLeagueGame($game2->fresh()), $game2->fresh());
        $this->assertDatabaseHas('player_badges', ['badge_key' => '140']);

        app(LeagueSeasonService::class)->withdraw($season->id, $a->id);
        $this->assertDatabaseCount('player_badges', 0);
    }

    #[Test]
    public function wheel_reads_12_of_64_from_read_model_and_profile_renders_it(): void
    {
        $player = $this->registered('Karol');
        $keys = array_values(array_unique([...array_slice(CheckoutCatalog::all(), 0, 11), 121]));
        foreach ($keys as $key) {
            PlayerBadge::query()->create([
                'player_id' => $player->id,
                'category' => BadgeCategory::Checkout->value,
                'badge_key' => (string) $key,
                'times_earned' => $key === 121 ? 7 : 1,
                'first_unlocked_at' => now(),
                'last_earned_at' => now(),
                'source_quality' => 'manual',
            ]);
        }

        $items = app(CheckoutWheelAssembler::class)->itemsForPlayer((int) $player->id);
        $this->assertCount(64, $items);
        $unlocked = array_values(array_filter($items, fn ($item) => $item['timesEarned'] > 0));
        $this->assertCount(12, $unlocked);
        $oneTwoOne = collect($items)->firstWhere('key', '121');
        $this->assertSame(7, $oneTwoOne['timesEarned']);
        $this->assertSame(7, $oneTwoOne['level']);
        $this->assertSame('bright', $oneTwoOne['levelName']);
        $this->assertNull($oneTwoOne['lastGame']);

        $this->get(route('players.show', $player))
            ->assertOk()
            ->assertSee('Checkouty 100+')
            ->assertSee('12 / 64')
            ->assertSee('data-checkout-wheel', false);
    }

    /**
     * @return array{0: Player, 1: Player, 2: Tournament}
     */
    private function tournamentPair(): array
    {
        $p1 = $this->registered('Host');
        $p2 = $this->registered('Rywal');
        $tournament = $this->tournament();
        GroupStanding::create(['tournament_id' => $tournament->id, 'group_number' => 1, 'player_id' => $p1->id]);
        GroupStanding::create(['tournament_id' => $tournament->id, 'group_number' => 1, 'player_id' => $p2->id]);
        Game::create([
            'tournament_id' => $tournament->id,
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'group_number' => 1,
            'status' => GameStatus::SCHEDULED,
            'starting_score' => 501,
            'game_type' => 'x01',
        ]);

        return [$p1, $p2, $tournament];
    }

    private function registered(string $name): Player
    {
        $user = User::factory()->create();
        $this->playerService->create($name, $user->id);

        return Player::query()->where('user_id', $user->id)->firstOrFail();
    }

    private function tournament(): Tournament
    {
        $organization = Organization::create(['name' => 'Klub', 'description' => '']);
        $season = Season::create([
            'name' => 'S',
            'organization_id' => $organization->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        return Tournament::create([
            'name' => 'T',
            'season_id' => $season->id,
            'date' => '2026-06-01',
            'status' => TournamentStatus::GROUP,
        ]);
    }

    private function createGroupGame(
        Tournament $tournament,
        Player $p1,
        Player $p2,
        int $groupNumber = 1,
        int $startingScore = 501,
        GameStatus $status = GameStatus::SCHEDULED,
    ): Game {
        return Game::create([
            'tournament_id' => $tournament->id,
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'group_number' => $groupNumber,
            'status' => $status,
            'starting_score' => $startingScore,
            'game_type' => 'x01',
            'legs_to_win_set' => 2,
            'sets_to_win_match' => 1,
        ]);
    }

    private function finishGroupByScoring(Game $game, Player $p1, Player $p2, int $checkout, ?int $secondCheckout = null): void
    {
        $scoring = app(GameScoringService::class);
        $this->closeCheckoutLeg($scoring, $game, $p1, $p2, $checkout);
        $this->closeCheckoutLeg($scoring, $game->fresh(), $p1, $p2, $secondCheckout ?? $checkout);
        $game->refresh();
        $this->assertSame(GameStatus::FINISHED, $game->status);
    }

    private function closeCheckoutLeg(GameScoringService $scoring, Game $game, Player $p1, Player $p2, int $checkout): void
    {
        $context = GameScoringContext::fromGroupGame($game);
        $state = $scoring->startLeg($context, $game, false, false);
        $legId = (int) $state['currentLeg']['id'];
        $starting = (int) ($game->starting_score ?: 501);
        $this->playDownTo($scoring, $context, $game, $legId, (int) $p1->id, $checkout, $starting);
        $scoring->recordVisit($context, $game, $legId, $this->checkoutVisitDto($p1->id, $checkout));
        $this->serviceCloseLeg($scoring, $context, $game, $legId, $p1, $p2);
    }

    private function closeScoringLegWithoutCheckout(GameScoringService $scoring, Game $game, Player $p1, Player $p2): void
    {
        $context = GameScoringContext::fromGroupGame($game);
        $state = $scoring->startLeg($context, $game, false, false);
        $legId = (int) $state['currentLeg']['id'];
        $starting = (int) ($game->starting_score ?: 501);
        $scoring->recordVisit($context, $game, $legId, $this->openVisitDto($p1->id, 60, $starting));
        $this->serviceCloseLeg($scoring, $context, $game, $legId, $p1, $p2);
    }

    private function serviceCloseLeg(
        GameScoringService $scoring,
        GameScoringContext $context,
        Game $game,
        int $legId,
        Player $p1,
        Player $p2,
    ): void {
        $scoring->closeLeg($context, $game, $legId, (int) $p1->id, [
            new CloseLegPlayerStatsDTO((int) $p1->id, false, null, null),
            new CloseLegPlayerStatsDTO((int) $p2->id, false, null, null),
        ]);
    }

    private function playDownTo(
        GameScoringService $scoring,
        GameScoringContext $context,
        Game $game,
        int $legId,
        int $playerId,
        int $targetRemaining,
        int $starting,
    ): void {
        $remaining = $starting;
        while ($remaining - $targetRemaining > 180) {
            $scoring->recordVisit($context, $game, $legId, $this->openVisitDto($playerId, 180, $remaining));
            $remaining -= 180;
        }
        $gap = $remaining - $targetRemaining;
        if ($gap > 0) {
            $scoring->recordVisit($context, $game, $legId, $this->openVisitDto($playerId, $gap, $remaining));
        }
    }

    private function checkoutVisitDto(int $playerId, int $checkout): RecordVisitDTO
    {
        return new RecordVisitDTO(
            playerId: $playerId,
            score: $checkout,
            remainingBefore: $checkout,
            remainingAfter: 0,
            dartsInVisit: 3,
            closedLeg: true,
            bust: false,
            clientVisitId: (string) Str::uuid(),
        );
    }

    private function openVisitDto(int $playerId, int $score, int $remainingBefore): RecordVisitDTO
    {
        return new RecordVisitDTO(
            playerId: $playerId,
            score: $score,
            remainingBefore: $remainingBefore,
            remainingAfter: $remainingBefore - $score,
            dartsInVisit: 3,
            closedLeg: false,
            bust: false,
            clientVisitId: (string) Str::uuid(),
        );
    }

    private function seedFinishedCheckout(Game|LeagueGame $game, string $fk, int $playerId, int $score): void
    {
        $leg = GameLeg::create([
            $fk => $game->id,
            'leg_number' => 1,
            'winner_id' => $playerId,
            'player1_score' => 501,
            'player2_score' => 0,
            'finished_at' => now(),
        ]);
        $this->visit($leg, $playerId, $score, true);
    }

    private function visit(GameLeg $leg, int $playerId, int $score, bool $closed, bool $voided = false): void
    {
        GameVisit::create([
            'game_leg_id' => $leg->id,
            'player_id' => $playerId,
            'visit_number' => 1,
            'score' => $score,
            'remaining_before' => $score,
            'remaining_after' => $closed ? 0 : 400,
            'darts_in_visit' => 3,
            'closed_leg' => $closed,
            'bust' => false,
            'is_voided' => $voided,
            'client_visit_id' => (string) Str::uuid(),
        ]);
    }

    private function openLeagueSeason(Player $a, Player $b): LeagueSeason
    {
        $organization = Organization::create(['name' => 'Klub L', 'description' => '']);
        $league = League::create(['organization_id' => $organization->id, 'name' => 'Liga', 'description' => '']);
        $div = LeagueDivision::create([
            'league_id' => $league->id,
            'position' => 0,
            'name' => 'Liga',
            'capacity' => 8,
            'starting_score' => 501,
            'legs_to_win_set' => 2,
            'sets_to_win_match' => 1,
        ]);
        $season = LeagueSeason::create([
            'league_id' => $league->id,
            'name' => 'Sezon',
            'status' => LeagueSeasonStatus::IN_PROGRESS,
            'calendar_mode' => LeagueCalendarMode::DEADLINE,
            'rounds_each' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-01',
        ]);
        $seasonDiv = LeagueSeasonDivision::create([
            'league_season_id' => $season->id,
            'league_division_id' => $div->id,
            'position' => 0,
            'name' => 'Liga',
            'capacity' => 8,
            'starting_score' => 501,
            'legs_to_win_set' => 2,
            'sets_to_win_match' => 1,
        ]);
        LeagueSeasonParticipant::create([
            'league_season_id' => $season->id,
            'league_season_division_id' => $seasonDiv->id,
            'player_id' => $a->id,
        ]);
        LeagueSeasonParticipant::create([
            'league_season_id' => $season->id,
            'league_season_division_id' => $seasonDiv->id,
            'player_id' => $b->id,
        ]);
        LeagueGame::create([
            'league_season_id' => $season->id,
            'league_season_division_id' => $seasonDiv->id,
            'purpose' => LeagueGamePurpose::REGULAR,
            'player1_id' => $a->id,
            'player2_id' => $b->id,
            'status' => LeagueGameStatus::SCHEDULED,
            'starting_score' => 501,
            'game_type' => 'x01',
            'legs_to_win_set' => 2,
            'sets_to_win_match' => 1,
        ]);

        return $season->fresh(['divisions']);
    }
}
