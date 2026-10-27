<?php

namespace Tests\Feature\Activity;

use App\Domain\Activity\ProfileFeedVisibility;
use App\Enums\TournamentStatus;
use App\Models\Activity\Activity;
use App\Models\Activity\ActivityComment;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentResult;
use App\Models\Users\User;
use App\Repositories\Activity\ActivityRepository;
use App\Repositories\Friends\FriendshipRepository;
use App\Repositories\Organization\OrganizationRepository;
use App\Services\Activity\ActivityRecorder;
use App\Services\Organization\OrganizationService;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityEngagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_friend_can_comment_reply_and_like_and_a_second_tap_removes_the_like(): void
    {
        [$viewer, $andrzej] = $this->pair(friends: true);
        $activity = $this->story($andrzej);

        $this->actingAs($viewer, 'sanctum')
            ->postJson('/api/feed/activities/'.$activity->id.'/comments', ['body' => '  Brawo!  '])
            ->assertOk()
            ->assertJsonPath('comment.body', 'Brawo!')
            ->assertJsonPath('comment.author.name', 'Widz')
            ->assertJsonPath('commentCount', 1);

        $commentId = (int) ActivityComment::query()->value('id');

        $reply = $this->actingAs($andrzej, 'sanctum')
            ->postJson('/api/feed/activities/'.$activity->id.'/comments', [
                'body' => 'Dzięki',
                'parentId' => $commentId,
            ])
            ->assertOk()
            ->assertJsonPath('comment.parentId', $commentId)
            ->assertJsonPath('commentCount', 2);

        $replyId = (int) $reply->json('comment.id');

        $this->actingAs($viewer, 'sanctum')
            ->postJson('/api/feed/activities/'.$activity->id.'/comments', [
                'body' => 'za głęboko',
                'parentId' => $replyId,
            ])
            ->assertStatus(422);

        $this->actingAs($viewer, 'sanctum')
            ->putJson('/api/feed/activities/'.$activity->id.'/reaction', ['kind' => 'like'])
            ->assertOk()
            ->assertJsonPath('reactions.mine', 'like')
            ->assertJsonPath('reactions.counts.like', 1);

        $this->actingAs($viewer, 'sanctum')
            ->putJson('/api/feed/activities/'.$activity->id.'/reaction', ['kind' => 'like'])
            ->assertOk()
            ->assertJsonPath('reactions.mine', null);

        $this->actingAs($andrzej, 'sanctum')
            ->putJson('/api/feed/comments/'.$commentId.'/reaction', ['kind' => 'like'])
            ->assertOk()
            ->assertJsonPath('reactions.counts.like', 1);

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/feed/activities/'.$activity->id.'/comments')
            ->assertOk()
            ->assertJsonPath('comments.0.body', 'Brawo!')
            ->assertJsonPath('comments.0.canDelete', true)
            ->assertJsonPath('comments.0.replies.0.body', 'Dzięki')
            ->assertJsonPath('comments.0.reactions.counts.like', 1);

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/feed')
            ->assertOk()
            ->assertJsonPath('items.0.commentCount', 2)
            ->assertJsonPath('items.0.canEngage', true);

        $this->actingAs($andrzej, 'sanctum')
            ->deleteJson('/api/feed/comments/'.$commentId)
            ->assertStatus(403);

        $this->actingAs($viewer, 'sanctum')
            ->deleteJson('/api/feed/comments/'.$commentId)
            ->assertOk()
            ->assertJsonPath('commentCount', 0);

        $this->assertDatabaseCount('activity_comments', 0);
    }

    public function test_a_hidden_board_rejects_a_guessed_activity_even_when_the_home_channel_is_open(): void
    {
        [$viewer, $andrzej, $organization] = $this->pair(friends: false);
        $andrzej->profile_feed_visibility = ProfileFeedVisibility::Friends;
        $andrzej->save();
        app(OrganizationRepository::class)->addRelatedUser($organization->id, $viewer->id);
        $activity = $this->story($andrzej);

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/feed')
            ->assertOk()
            ->assertJsonPath('items.0.canEngage', false);

        $this->actingAs($viewer, 'sanctum')
            ->postJson('/api/feed/activities/'.$activity->id.'/comments', ['body' => 'hej'])
            ->assertNotFound();

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/feed/activities/'.$activity->id.'/comments')
            ->assertNotFound();

        $this->app['auth']->forgetGuards();

        $this->postJson('/api/feed/activities/'.$activity->id.'/comments', ['body' => 'hej'])
            ->assertUnauthorized();
    }

    public function test_removing_the_story_removes_its_comments_and_reactions(): void
    {
        [$viewer, $andrzej] = $this->pair(friends: true);
        $activity = $this->story($andrzej);

        $this->actingAs($viewer, 'sanctum')
            ->postJson('/api/feed/activities/'.$activity->id.'/comments', ['body' => 'Brawo'])
            ->assertOk();
        $this->actingAs($viewer, 'sanctum')
            ->putJson('/api/feed/activities/'.$activity->id.'/reaction', ['kind' => 'like'])
            ->assertOk();

        app(ActivityRepository::class)->deleteSubject('tournament.champion', 'tournament', (int) $activity->subject_id);

        $this->assertDatabaseCount('activities', 0);
        $this->assertDatabaseCount('activity_comments', 0);
        $this->assertDatabaseCount('activity_reactions', 0);
    }

    public function test_the_web_session_can_leave_a_comment(): void
    {
        [$viewer, $andrzej] = $this->pair(friends: true);
        $activity = $this->story($andrzej);

        $this->actingAs($viewer)
            ->postJson(route('feed.comments.store', $activity), ['body' => 'Z sieci'])
            ->assertOk()
            ->assertJsonPath('comment.body', 'Z sieci');
    }

    /**
     * @return array{0: User, 1: User, 2: Organization}
     */
    private function pair(bool $friends): array
    {
        $viewer = User::factory()->create();
        $andrzej = User::factory()->create(['can_create_organizations' => true]);
        app(PlayerService::class)->create('Widz', $viewer->id);
        app(PlayerService::class)->create('Andrzej', $andrzej->id);
        if ($friends) {
            app(FriendshipRepository::class)->addFriendship($viewer->id, $andrzej->id);
        }

        $organization = app(OrganizationService::class)->create('Apagon', 'Klub', $andrzej->id);

        return [$viewer, $andrzej, Organization::query()->findOrFail($organization->id)];
    }

    private function story(User $andrzej): Activity
    {
        $organization = Organization::query()->firstOrFail();
        $season = Season::query()->create([
            'organization_id' => $organization->id,
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
        app(ActivityRecorder::class)->tournamentChampion($tournament->id);

        return Activity::query()->firstOrFail();
    }
}
