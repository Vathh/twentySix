<?php

namespace Tests\Feature;

use App\Enums\TournamentStatus;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentResult;
use App\Models\Users\User;
use App\Repositories\Friends\FriendshipRepository;
use App\Repositories\Organization\OrganizationRepository;
use App\Services\Activity\ActivityRecorder;
use App\Services\Organization\OrganizationService;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_tournament_win_is_one_card_and_survives_a_single_mute(): void
    {
        [$viewer, $andrzej, $organization, $tournament] = $this->club();
        app(FriendshipRepository::class)->addFriendship($viewer->id, $andrzej->id);
        app(OrganizationRepository::class)->addRelatedUser($organization->id, $viewer->id);

        app(ActivityRecorder::class)->tournamentChampion($tournament->id);

        $this->assertDatabaseCount('activities', 1);
        app(ActivityRecorder::class)->tournamentChampion($tournament->id);
        $this->assertDatabaseCount('activities', 1);

        $sentence = 'Andrzej właśnie wygrał turniej Piątkowe Granie w Apagon.';

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/feed')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.sentence', $sentence)
            ->assertJsonPath('items.0.commentCount', 0)
            ->assertJsonPath('items.0.header.name', 'Apagon')
            ->assertJsonPath('items.0.header.organizationId', $organization->id)
            ->assertJsonPath('items.0.header.playerId', null);

        $this->actingAs($viewer)
            ->get('/')
            ->assertOk()
            ->assertSee($sentence, false)
            ->assertSee(route('organizations.show', $organization), false)
            ->assertDontSee(route('players.show', Player::query()->where('user_id', $andrzej->id)->value('id')), false);

        $this->actingAs($viewer)
            ->get(route('settings.feed.edit'))
            ->assertOk()
            ->assertSee('Kto widzi moją tablicę profilu');

        $this->actingAs($viewer, 'sanctum')
            ->postJson('/api/feed/mutes', [
                'subjectType' => 'user',
                'subjectKey' => (string) $andrzej->id,
            ])
            ->assertOk();

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/feed')
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.sentence', $sentence);

        $this->actingAs($viewer, 'sanctum')
            ->postJson('/api/feed/mutes', [
                'subjectType' => 'organization',
                'subjectKey' => (string) $organization->id,
            ])
            ->assertOk();

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/feed')
            ->assertJsonCount(0, 'items');
    }

    public function test_guest_champion_is_plain_text_and_the_club_signs_the_card(): void
    {
        [$viewer, , $organization, $tournament] = $this->club();
        app(OrganizationRepository::class)->addRelatedUser($organization->id, $viewer->id);

        $guest = Player::query()->create(['name' => 'jjj']);
        TournamentResult::query()
            ->where('tournament_id', $tournament->id)
            ->update(['player_id' => $guest->id]);

        app(ActivityRecorder::class)->tournamentChampion($tournament->id);

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/feed')
            ->assertOk()
            ->assertJsonPath('items.0.header.name', 'Apagon')
            ->assertJsonPath('items.0.header.playerId', null)
            ->assertJsonPath('items.0.actor.name', 'jjj')
            ->assertJsonPath('items.0.actor.userId', null)
            ->assertJsonPath('items.0.sentence', 'jjj właśnie wygrał turniej Piątkowe Granie w Apagon.');

        $this->actingAs($viewer)
            ->get('/')
            ->assertOk()
            ->assertSee('jjj właśnie wygrał turniej Piątkowe Granie w Apagon.', false)
            ->assertDontSee(route('players.show', $guest), false)
            ->assertSee(route('organizations.show', $organization), false);
    }

    public function test_muting_the_organization_keeps_the_friend_channel(): void
    {
        [$viewer, $andrzej, $organization, $tournament] = $this->club();
        app(FriendshipRepository::class)->addFriendship($viewer->id, $andrzej->id);
        app(OrganizationRepository::class)->addRelatedUser($organization->id, $viewer->id);
        app(ActivityRecorder::class)->tournamentChampion($tournament->id);

        $this->actingAs($viewer, 'sanctum')->postJson('/api/feed/mutes', [
            'subjectType' => 'organization',
            'subjectKey' => (string) $organization->id,
        ])->assertOk();

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/feed')
            ->assertJsonCount(1, 'items');
    }

    public function test_first_180_is_hidden_when_the_actor_is_muted(): void
    {
        [$viewer, $andrzej] = $this->club();
        app(FriendshipRepository::class)->addFriendship($viewer->id, $andrzej->id);
        $player = Player::query()->where('user_id', $andrzej->id)->firstOrFail();

        app(ActivityRecorder::class)->playerFirst180($player->id);
        app(ActivityRecorder::class)->playerFirst180($player->id);
        $this->assertDatabaseCount('activities', 1);

        $this->actingAs($viewer, 'sanctum')->postJson('/api/feed/mutes', [
            'subjectType' => 'user',
            'subjectKey' => (string) $andrzej->id,
        ])->assertOk();

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/feed')
            ->assertJsonCount(0, 'items');
    }

    public function test_profile_visibility_for_guest_friend_and_circle(): void
    {
        [$viewer, $andrzej, $organization, $tournament] = $this->club();
        $player = Player::query()->where('user_id', $andrzej->id)->firstOrFail();
        app(ActivityRecorder::class)->tournamentChampion($tournament->id);

        $sentence = 'Andrzej właśnie wygrał turniej Piątkowe Granie w Apagon.';

        $this->getJson('/api/players/'.$player->id.'/feed')
            ->assertOk()
            ->assertJsonPath('items.0.sentence', $sentence);

        $this->get(route('players.show', $player))
            ->assertOk()
            ->assertSee('Podsumowanie')
            ->assertSee('Tablica')
            ->assertSee($sentence, false);

        $this->get(route('players.feed', $player))
            ->assertOk()
            ->assertJsonPath('items.0.sentence', $sentence);

        $this->actingAs($andrzej, 'sanctum')->putJson('/api/feed/preferences', [
            'profileFeedVisibility' => 'friends',
            'channels' => ['friends' => true, 'organization' => true],
        ])->assertOk();

        $this->app['auth']->forgetGuards();

        $this->getJson('/api/players/'.$player->id.'/feed')
            ->assertJsonCount(0, 'items');

        $this->get(route('players.show', $player))
            ->assertOk()
            ->assertDontSee($sentence, false);

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/players/'.$player->id.'/feed')
            ->assertJsonCount(0, 'items');

        $this->actingAs($andrzej, 'sanctum')->putJson('/api/feed/preferences', [
            'profileFeedVisibility' => 'friends_and_circle',
            'channels' => ['friends' => true, 'organization' => true],
        ])->assertOk();

        app(OrganizationRepository::class)->addRelatedUser($organization->id, $viewer->id);

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/players/'.$player->id.'/feed')
            ->assertJsonCount(1, 'items');

        $this->actingAs($andrzej, 'sanctum')->postJson('/api/feed/mutes', [
            'subjectType' => 'user',
            'subjectKey' => (string) $andrzej->id,
        ])->assertStatus(422);
    }

    /**
     * @return array{0: User, 1: User, 2: Organization, 3: Tournament}
     */
    private function club(): array
    {
        $viewer = User::factory()->create();
        $andrzej = User::factory()->create(['can_create_organizations' => true]);
        app(PlayerService::class)->create('Widz', $viewer->id);
        app(PlayerService::class)->create('Andrzej', $andrzej->id);

        $organization = app(OrganizationService::class)->create('Apagon', 'Klub', $andrzej->id);
        $organizationModel = Organization::query()->findOrFail($organization->id);
        $season = Season::query()->create([
            'organization_id' => $organizationModel->id,
            'name' => 'Sezon',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        $tournament = Tournament::query()->create([
            'season_id' => $season->id,
            'name' => 'Piątkowe Granie',
            'date' => '2026-06-01',
            'status' => TournamentStatus::FINISHED,
        ]);
        $player = Player::query()->where('user_id', $andrzej->id)->firstOrFail();
        TournamentResult::query()->create([
            'season_id' => $season->id,
            'tournament_id' => $tournament->id,
            'player_id' => $player->id,
            'points' => 10,
            'place' => 1,
        ]);

        return [$viewer, $andrzej, $organizationModel, $tournament];
    }
}
