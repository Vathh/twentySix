<?php

namespace Tests\Feature;

use App\Domain\Moderation\ReportStatus;
use App\Models\Activity\Activity;
use App\Models\Activity\ActivityComment;
use App\Models\Moderation\ContentReport;
use App\Models\Player\Player;
use App\Models\Users\User;
use App\Repositories\Friends\FriendshipRepository;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_block_hides_posts_and_comments_only_for_the_blocker(): void
    {
        [$adam, $ola] = $this->pair();
        $adamsPost = $this->story($adam, 1);
        $olasPost = $this->story($ola, 2);

        $this->actingAs($ola, 'sanctum')
            ->postJson('/api/feed/activities/'.$adamsPost->id.'/comments', ['body' => 'komentarz Oli'])
            ->assertOk();
        $this->actingAs($adam, 'sanctum')
            ->postJson('/api/feed/activities/'.$adamsPost->id.'/comments', ['body' => 'komentarz Adama'])
            ->assertOk();

        $this->actingAs($adam, 'sanctum')
            ->postJson('/api/blocks', ['userId' => $adam->id])
            ->assertStatus(422);

        $this->actingAs($adam, 'sanctum')
            ->postJson('/api/blocks', ['userId' => $ola->id])
            ->assertOk();

        $adamFeed = collect($this->actingAs($adam, 'sanctum')->getJson('/api/feed')->json('items'));
        $olaFeed = collect($this->actingAs($ola, 'sanctum')->getJson('/api/feed')->json('items'));

        $this->assertFalse($adamFeed->pluck('id')->contains($olasPost->id));
        $this->assertTrue($adamFeed->pluck('id')->contains($adamsPost->id));
        $this->assertSame(1, $adamFeed->firstWhere('id', $adamsPost->id)['commentCount']);
        $this->assertTrue($olaFeed->pluck('id')->contains($adamsPost->id));
        $this->assertTrue($olaFeed->pluck('id')->contains($olasPost->id));
        $this->assertSame(2, $olaFeed->firstWhere('id', $adamsPost->id)['commentCount']);

        $adamComments = $this->actingAs($adam, 'sanctum')
            ->getJson('/api/feed/activities/'.$adamsPost->id.'/comments')
            ->assertOk()
            ->json('comments');
        $olaComments = $this->actingAs($ola, 'sanctum')
            ->getJson('/api/feed/activities/'.$adamsPost->id.'/comments')
            ->assertOk()
            ->json('comments');

        $this->assertSame(['komentarz Adama'], array_column($adamComments, 'body'));
        $this->assertEqualsCanonicalizing(
            ['komentarz Adama', 'komentarz Oli'],
            array_column($olaComments, 'body'),
        );

        $olaPlayer = Player::query()->where('user_id', $ola->id)->firstOrFail();
        $this->actingAs($adam, 'sanctum')
            ->getJson('/api/players/'.$olaPlayer->id.'/feed')
            ->assertJsonCount(0, 'items');

        $this->actingAs($ola, 'sanctum')
            ->postJson('/api/blocks', ['userId' => $adam->id])
            ->assertOk();

        $olaFeedAfter = collect($this->actingAs($ola, 'sanctum')->getJson('/api/feed')->json('items'));
        $this->assertFalse($olaFeedAfter->pluck('id')->contains($adamsPost->id));
        $this->actingAs($ola, 'sanctum')
            ->getJson('/api/feed/activities/'.$adamsPost->id.'/comments')
            ->assertNotFound();
    }

    public function test_a_report_can_be_filed_once_and_an_admin_can_act_on_it(): void
    {
        [$adam, $ola] = $this->pair();
        $post = $this->story($adam, 3);
        $commentId = $this->actingAs($ola, 'sanctum')
            ->postJson('/api/feed/activities/'.$post->id.'/comments', ['body' => 'spam na tablicy'])
            ->assertOk()
            ->json('comment.id');

        $this->actingAs($adam, 'sanctum')
            ->postJson('/api/reports', [
                'subjectType' => 'comment',
                'subjectId' => $commentId,
                'reason' => 'spam',
            ])
            ->assertOk();

        $this->actingAs($adam, 'sanctum')
            ->postJson('/api/reports', [
                'subjectType' => 'comment',
                'subjectId' => $commentId,
                'reason' => 'insult',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'To zgłoszenie już jest.');

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $reportId = (int) ContentReport::query()->value('id');

        $this->actingAs($admin)
            ->post(route('admin.reports.dismiss', $reportId))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(ReportStatus::Dismissed, ContentReport::query()->find($reportId)?->status);

        $this->actingAs($ola, 'sanctum')
            ->postJson('/api/reports', [
                'subjectType' => 'post',
                'subjectId' => $post->id,
                'reason' => 'spam',
                'details' => 'Ten wpis jest reklamą.',
            ])
            ->assertOk();

        $this->actingAs($adam, 'sanctum')
            ->postJson('/api/reports', [
                'subjectType' => 'user',
                'subjectId' => $ola->id,
                'reason' => 'insult',
                'details' => 'Wyzywa przy każdym komentarzu.',
            ])
            ->assertOk();

        $userReportId = (int) ContentReport::query()->where('subject_type', 'user')->value('id');

        $this->actingAs($admin)
            ->get(route('admin.reports'))
            ->assertOk()
            ->assertSee('Obraza')
            ->assertSee('Ola')
            ->assertSee('Wyzywa przy każdym komentarzu.')
            ->assertSee('Wpis: Adam wygrał turniej Puchar.')
            ->assertSee('Ten wpis jest reklamą.');

        $this->actingAs($admin)
            ->post(route('admin.reports.ban', $userReportId))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNotNull($ola->fresh()->banned_at);
        $this->assertSame(ReportStatus::Resolved, ContentReport::query()->find($userReportId)?->status);

        $openComment = ActivityComment::query()->where('body', 'spam na tablicy')->firstOrFail();
        ContentReport::query()->whereKey($reportId)->update(['status' => ReportStatus::Open->value]);

        $this->actingAs($admin)
            ->post(route('admin.reports.delete-comment', $reportId))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNull(ActivityComment::query()->find($openComment->id));
    }

    public function test_blocked_list_can_be_reopened(): void
    {
        [$adam, $ola] = $this->pair();

        $this->actingAs($adam, 'sanctum')->postJson('/api/blocks', ['userId' => $ola->id])->assertOk();

        $this->actingAs($adam)
            ->get(route('settings.blocks.index'))
            ->assertOk()
            ->assertSee('Ola')
            ->assertSee('Odblokuj');

        $this->actingAs($adam)
            ->delete(route('blocks.destroy', $ola->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($adam, 'sanctum')
            ->getJson('/api/blocks')
            ->assertOk()
            ->assertJsonCount(0, 'blocked');
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function pair(): array
    {
        $adam = User::factory()->create();
        $ola = User::factory()->create();
        app(PlayerService::class)->create('Adam', $adam->id);
        app(PlayerService::class)->create('Ola', $ola->id);
        app(FriendshipRepository::class)->addFriendship($adam->id, $ola->id);
        app(FriendshipRepository::class)->addFriendship($ola->id, $adam->id);

        return [$adam, $ola];
    }

    private function story(User $author, int $subjectId): Activity
    {
        $player = Player::query()->where('user_id', $author->id)->firstOrFail();

        return Activity::query()->create([
            'type' => 'tournament.champion',
            'actor_player_id' => $player->id,
            'actor_user_id' => $author->id,
            'subject_type' => 'tournament',
            'subject_id' => $subjectId,
            'payload' => [
                'actorName' => $player->name,
                'tournamentName' => 'Puchar',
            ],
            'occurred_at' => now(),
        ]);
    }
}
