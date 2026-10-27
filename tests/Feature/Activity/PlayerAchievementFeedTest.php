<?php

namespace Tests\Feature\Activity;

use App\Enums\GameKind;
use App\Enums\GameStatus;
use App\Enums\TournamentStatus;
use App\Models\Activity\Activity;
use App\Models\Badge\PlayerBadgeEvent;
use App\Models\Career\PlayerGameSnapshot;
use App\Models\Game\Game;
use App\Models\Game\GameLeg;
use App\Models\Game\GameLegPlayerStat;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Repositories\Friends\FriendshipRepository;
use App\Services\Activity\PlayerAchievementPublisher;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerAchievementFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_badge_levels_from_one_match_are_one_card_for_friends(): void
    {
        [$viewer, $andrzej, $opponent] = $this->players();
        $game = $this->finishedGame($andrzej, $opponent, '2026-06-01 12:00:00');
        $this->badgeEvent($andrzej, $game, 'high_visit', '180');
        $this->badgeEvent($andrzej, $game, 'qf', '12');

        app(PlayerAchievementPublisher::class)->syncMatch(GameKind::GROUP, $game->id);

        $sentence = 'Andrzej właśnie zdobył odznaki: 180 — Żelazo, QF 12 — Żelazo.';
        $this->assertSame(1, Activity::query()->where('type', 'player.badges')->count());

        $later = $this->finishedGame($andrzej, $opponent, '2026-06-02 12:00:00');
        $this->badgeEvent($andrzej, $later, 'high_visit', '180');
        app(PlayerAchievementPublisher::class)->syncMatch(GameKind::GROUP, $later->id);

        $this->assertSame(1, Activity::query()->where('type', 'player.badges')->count());

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/feed')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.sentence', $sentence)
            ->assertJsonPath('items.0.kind.label', 'Odznaka')
            ->assertJsonPath('items.0.actor.name', 'Andrzej');

        $this->actingAs($viewer, 'sanctum')->postJson('/api/feed/mutes', [
            'subjectType' => 'user',
            'subjectKey' => (string) $andrzej->user_id,
        ])->assertOk();

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/feed')
            ->assertJsonCount(0, 'items');

        $this->get(route('players.show', $andrzej))
            ->assertOk()
            ->assertSee($sentence, false);
    }

    public function test_a_match_can_raise_finish_and_fastest_leg_together_and_average_only_after_the_first(): void
    {
        [, $andrzej, $opponent] = $this->players();
        $first = $this->finishedGame($andrzej, $opponent, '2026-06-01 12:00:00');
        $this->snapshot($andrzej, $first, 40.0, '2026-06-01 12:00:00');
        app(PlayerAchievementPublisher::class)->syncMatch(GameKind::GROUP, $first->id);
        $this->assertSame(0, Activity::query()->where('type', 'player.personal_best')->count());

        $second = $this->finishedGame($andrzej, $opponent, '2026-06-03 18:00:00');
        $this->snapshot($andrzej, $second, 62.5, '2026-06-03 18:00:00');
        $leg = GameLeg::query()->create([
            'game_id' => $second->id,
            'leg_number' => 1,
            'winner_id' => $andrzej->id,
            'player1_score' => 501,
            'player2_score' => 0,
            'finished_at' => '2026-06-03 17:40:00',
        ]);
        GameLegPlayerStat::query()->create([
            'game_leg_id' => $leg->id,
            'player_id' => $andrzej->id,
            'highest_finish' => 141,
            'darts_thrown' => 15,
        ]);

        app(PlayerAchievementPublisher::class)->syncMatch(GameKind::GROUP, $second->id);

        $this->assertSame(1, Activity::query()->where('type', 'player.personal_best')->count());

        $activity = Activity::query()->where('type', 'player.personal_best')->firstOrFail();
        $this->assertSame(
            'Andrzej właśnie poprawił życiowe rekordy: średnia meczowa 62,50, najwyższy finish 141, najszybsza lotka 15.',
            app(\App\Domain\Activity\ActivityRegistry::class)->sentence($activity->type, $activity->payload),
        );
    }

    /**
     * @return array{0: User, 1: Player, 2: Player}
     */
    private function players(): array
    {
        $viewer = User::factory()->create();
        $owner = User::factory()->create(['can_create_organizations' => true]);
        $other = User::factory()->create();
        app(PlayerService::class)->create('Widz', $viewer->id);
        app(PlayerService::class)->create('Andrzej', $owner->id);
        app(PlayerService::class)->create('Bolek', $other->id);
        app(FriendshipRepository::class)->addFriendship($viewer->id, $owner->id);

        return [
            $viewer,
            Player::query()->where('user_id', $owner->id)->firstOrFail(),
            Player::query()->where('user_id', $other->id)->firstOrFail(),
        ];
    }

    private function finishedGame(Player $player, Player $opponent, string $finishedAt): Game
    {
        $organization = Organization::query()->create([
            'name' => 'Klub '.uniqid(),
            'description' => 'Opis',
        ]);
        $season = Season::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Sezon',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        $tournament = Tournament::query()->create([
            'season_id' => $season->id,
            'name' => 'Puchar',
            'date' => '2026-06-01',
            'status' => TournamentStatus::GROUP,
        ]);

        return Game::query()->create([
            'tournament_id' => $tournament->id,
            'player1_id' => $player->id,
            'player2_id' => $opponent->id,
            'player1_score' => 2,
            'player2_score' => 0,
            'winner_id' => $player->id,
            'group_number' => 1,
            'status' => GameStatus::FINISHED,
            'finished_at' => $finishedAt,
        ]);
    }

    private function badgeEvent(Player $player, Game $game, string $category, string $key): void
    {
        PlayerBadgeEvent::query()->create([
            'player_id' => $player->id,
            'category' => $category,
            'badge_key' => $key,
            'amount' => 1,
            'source_kind' => 'group',
            'source_id' => $game->id,
        ]);
    }

    private function snapshot(Player $player, Game $game, float $average, string $at): void
    {
        PlayerGameSnapshot::query()->create([
            'player_id' => $player->id,
            'source' => 'tournament',
            'game_type' => '501',
            'occurred_at' => $at,
            'sourceable_type' => $game->getMorphClass(),
            'sourceable_id' => $game->id,
            'metrics' => [
                'schema_version' => 2,
                'average' => $average,
                'darts_thrown' => 30,
                'points' => 500,
                'double_attempts' => null,
                'double_successes' => null,
                'double_tracked' => false,
            ],
        ]);
    }
}
