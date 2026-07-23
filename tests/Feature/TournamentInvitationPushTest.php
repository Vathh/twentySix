<?php

namespace Tests\Feature;

use App\Enums\InvitationPushType;
use App\Jobs\SendInvitationPushJob;
use App\Models\League\League;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use App\Services\Tournament\TournamentInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TournamentInvitationPushTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_and_send_bulk_dispatch_push_jobs(): void
    {
        Queue::fake();

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PointSchemeSeeder']);

        $admin = User::factory()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $playerService = app(PlayerService::class);
        $playerService->create('Admin', $admin->id);
        $playerService->create('PlayerA', $userA->id);
        $playerService->create('PlayerB', $userB->id);

        $league = League::create(['name' => 'L', 'description' => '']);
        $season = Season::create([
            'league_id' => $league->id,
            'name' => 'S1',
            'start_date' => now(),
            'end_date' => now()->addMonth(),
        ]);
        $tournament = Tournament::create([
            'season_id' => $season->id,
            'name' => 'Open Cup',
            'date' => now(),
            'status' => 'created',
        ]);

        $service = app(TournamentInvitationService::class);

        $single = $service->send($tournament->id, $userA->id, $admin->id);

        Queue::assertPushed(SendInvitationPushJob::class, function (SendInvitationPushJob $job) use ($userA, $single) {
            return $job->recipientUserId === $userA->id
                && $job->type === InvitationPushType::Tournament->value
                && $job->invitationId === $single->id
                && ($job->context['tournamentName'] ?? null) === 'Open Cup';
        });

        Queue::fake();

        $result = $service->sendBulk($tournament->id, [$userB->id], $admin->id);
        $this->assertSame(1, $result['sent']);

        Queue::assertPushed(SendInvitationPushJob::class, function (SendInvitationPushJob $job) use ($userB) {
            return $job->recipientUserId === $userB->id
                && $job->type === InvitationPushType::Tournament->value
                && ($job->context['tournamentName'] ?? null) === 'Open Cup';
        });
    }
}
