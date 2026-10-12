<?php

namespace Tests\Feature;

use App\Events\WebcamMatchSignal;
use App\Models\Users\User;
use App\Models\Webcam\WebcamMatch;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WebcamMatchTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    private User $guest;

    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();

        $players = app(PlayerService::class);
        $this->host = User::factory()->create();
        $this->guest = User::factory()->create();
        $this->stranger = User::factory()->create();
        $players->create('Host', $this->host->id);
        $players->create('Gość', $this->guest->id);
        $players->create('Obcy', $this->stranger->id);

        DB::table('friendships')->insert([
            'user_id' => $this->host->id,
            'friend_id' => $this->guest->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_guest_is_redirected_from_the_list(): void
    {
        $this->get(route('webcam.index'))
            ->assertRedirect(route('pages.loginPanel'));
    }

    public function test_only_a_friend_can_be_invited(): void
    {
        $this->actingAs($this->host)
            ->post(route('webcam.store'), ['friend_user_id' => $this->stranger->id])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('webcam_matches', 0);
    }

    public function test_friends_open_a_match_and_a_stranger_cannot_see_it(): void
    {
        $this->actingAs($this->host)
            ->post(route('webcam.store'), ['friend_user_id' => $this->guest->id])
            ->assertRedirect();

        $match = WebcamMatch::query()->firstOrFail();

        $this->actingAs($this->guest)
            ->get(route('webcam.show', $match))
            ->assertOk()
            ->assertSee('Gotowy', false);

        $this->actingAs($this->stranger)
            ->get(route('webcam.show', $match))
            ->assertNotFound();

        $this->actingAs($this->host)
            ->post(route('webcam.store'), ['friend_user_id' => $this->guest->id])
            ->assertRedirect(route('webcam.show', $match));

        $this->assertDatabaseCount('webcam_matches', 1);
    }

    public function test_the_match_starts_when_both_players_are_ready_and_the_host_throws_first(): void
    {
        $match = $this->openMatch();

        $this->actingAs($this->host)
            ->postJson(route('webcam.ready', $match))
            ->assertOk()
            ->assertJsonPath('status', 'lobby');

        $this->actingAs($this->guest)
            ->postJson(route('webcam.visit', $match), $this->visitBody(60, 501))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Mecz zaczyna się, gdy oboje będziecie gotowi.');

        $this->actingAs($this->guest)
            ->postJson(route('webcam.ready', $match))
            ->assertOk()
            ->assertJsonPath('status', 'live')
            ->assertJsonPath('currentTurnUserId', $this->host->id);

        $this->actingAs($this->guest)
            ->postJson(route('webcam.visit', $match), $this->visitBody(26, 501))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Teraz rzuca przeciwnik.');
    }

    public function test_a_visit_passes_the_turn_and_undo_gives_it_back(): void
    {
        $match = $this->liveMatch();

        $this->actingAs($this->host)
            ->postJson(route('webcam.visit', $match), $this->visitBody(60, 501))
            ->assertOk()
            ->assertJsonPath('currentTurnUserId', $this->guest->id)
            ->assertJsonPath('players.0.remaining', 441);

        $this->actingAs($this->host)
            ->postJson(route('webcam.undo', $match))
            ->assertOk()
            ->assertJsonPath('currentTurnUserId', $this->host->id)
            ->assertJsonPath('players.0.remaining', 501)
            ->assertJsonPath('visits', []);
    }

    public function test_bust_keeps_the_remaining_score(): void
    {
        $match = $this->liveMatch();

        $this->actingAs($this->host)
            ->postJson(route('webcam.visit', $match), $this->visitBody(0, 501, bust: true))
            ->assertOk()
            ->assertJsonPath('currentTurnUserId', $this->guest->id)
            ->assertJsonPath('players.0.remaining', 501)
            ->assertJsonPath('visits.0.bust', true);
    }

    public function test_leaving_one_point_is_rejected(): void
    {
        $match = $this->liveMatch();
        $this->bringHostRemainingTo($match, 41);

        $this->actingAs($this->host)
            ->postJson(route('webcam.visit', $match), $this->visitBody(40, 41))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Zostawienie 1 punktu to bust.');
    }

    public function test_checkout_rotates_the_opener_and_two_legs_finish_the_match(): void
    {
        $match = $this->liveMatch();
        $this->hostChecksOut($match);

        $match->refresh();
        $this->assertSame(1, $match->host_legs_won);
        $this->assertSame(2, $match->leg_number);
        $this->assertSame($this->guest->id, (int) $match->current_turn_user_id);

        $this->hostChecksOut($match);

        $this->actingAs($this->host)
            ->getJson(route('webcam.state', $match))
            ->assertOk()
            ->assertJsonPath('status', 'finished')
            ->assertJsonPath('winnerUserId', $this->host->id)
            ->assertJsonPath('players.0.legsWon', 2);
    }

    public function test_participant_can_send_a_signal_and_a_stranger_cannot(): void
    {
        $match = $this->openMatch();

        Event::fake([WebcamMatchSignal::class]);

        $this->actingAs($this->stranger)
            ->postJson(route('webcam.signal', $match), [
                'kind' => 'offer',
                'payload' => ['type' => 'offer', 'sdp' => 'v=0'],
            ])
            ->assertNotFound();

        $this->actingAs($this->host)
            ->postJson(route('webcam.signal', $match), [
                'kind' => 'offer',
                'payload' => [
                    'type' => 'offer',
                    'sdp' => 'v=0',
                    'roles' => ['0' => 'board', '1' => 'face'],
                ],
            ])
            ->assertOk();

        Event::assertDispatched(WebcamMatchSignal::class, function (WebcamMatchSignal $event) use ($match) {
            return $event->matchId === $match->id
                && $event->fromUserId === $this->host->id
                && $event->kind === 'offer';
        });
    }

    private function openMatch(): WebcamMatch
    {
        $this->actingAs($this->host)
            ->post(route('webcam.store'), ['friend_user_id' => $this->guest->id])
            ->assertRedirect();

        return WebcamMatch::query()->firstOrFail();
    }

    private function liveMatch(): WebcamMatch
    {
        $match = $this->openMatch();
        $this->actingAs($this->host)->postJson(route('webcam.ready', $match))->assertOk();
        $this->actingAs($this->guest)->postJson(route('webcam.ready', $match))->assertOk();

        return $match->refresh();
    }

    /**
     * @return array<string, int|bool>
     */
    private function visitBody(int $score, int $before, bool $closed = false, int $darts = 3, bool $bust = false): array
    {
        return [
            'score' => $bust ? 0 : $score,
            'remaining_before' => $before,
            'remaining_after' => $bust ? $before : ($closed ? 0 : $before - $score),
            'darts_in_visit' => $darts,
            'bust' => $bust,
            'closed_leg' => $closed,
        ];
    }

    private function throwVisit(User $user, WebcamMatch $match, int $score, int $before, bool $closed = false, int $darts = 3): void
    {
        $this->actingAs($user)
            ->postJson(route('webcam.visit', $match), $this->visitBody($score, $before, $closed, $darts))
            ->assertOk();
    }

    private function bringHostRemainingTo(WebcamMatch $match, int $target): void
    {
        $hostRemaining = 501;
        $guestRemaining = 501;
        $state = $this->actingAs($this->host)->getJson(route('webcam.state', $match))->json();
        $hostTurn = $state['currentTurnUserId'] === $this->host->id;

        while ($hostRemaining !== $target || ! $hostTurn) {
            if (! $hostTurn) {
                $this->throwVisit($this->guest, $match, 0, $guestRemaining);
                $hostTurn = true;

                continue;
            }

            $score = min(180, $hostRemaining - $target);
            if ($hostRemaining - $score === 1) {
                $score--;
            }
            $this->throwVisit($this->host, $match, $score, $hostRemaining);
            $hostRemaining -= $score;
            $hostTurn = false;
        }
    }

    private function hostChecksOut(WebcamMatch $match): void
    {
        $this->bringHostRemainingTo($match, 41);
        $state = $this->actingAs($this->host)
            ->getJson(route('webcam.state', $match))
            ->json();
        $this->assertSame($this->host->id, $state['currentTurnUserId']);

        $this->throwVisit($this->host, $match, 41, 41, closed: true, darts: 2);
    }
}
