<?php

namespace Tests\Feature;

use App\Domain\Badge\BadgeCategory;
use App\Domain\Badge\Checkout\CheckoutCatalog;
use App\Models\Badge\PlayerBadge;
use App\Models\Player\Player;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BadgeComparisonTest extends TestCase
{
    use RefreshDatabase;

    private User $annaUser;

    private User $bartekUser;

    private Player $anna;

    private Player $bartek;

    protected function setUp(): void
    {
        parent::setUp();

        $players = app(PlayerService::class);
        $this->annaUser = User::factory()->create();
        $players->create('Anna', $this->annaUser->id);
        $this->anna = Player::where('user_id', $this->annaUser->id)->firstOrFail();

        $this->bartekUser = User::factory()->create();
        $players->create('Bartek', $this->bartekUser->id);
        $this->bartek = Player::where('user_id', $this->bartekUser->id)->firstOrFail();

        $this->badge($this->anna, BadgeCategory::HighVisit, '180', 1);
        $this->badge($this->bartek, BadgeCategory::Qf, '9', 1);
    }

    public function test_profile_still_hides_unearned_qf_while_compare_pairs_the_rest(): void
    {
        Sanctum::actingAs($this->annaUser);

        $this->getJson('/api/players/'.$this->anna->id)
            ->assertOk()
            ->assertJsonCount(0, 'qfItems');

        $this->getJson('/api/players/'.$this->bartek->id)
            ->assertOk()
            ->assertJsonCount(1, 'qfItems')
            ->assertJsonPath('qfItems.0.key', '9');

        $response = $this->getJson('/api/players/'.$this->bartek->id.'/badges/compare')
            ->assertOk()
            ->assertJsonPath('viewer.name', 'Anna')
            ->assertJsonPath('player.name', 'Bartek');

        $sections = collect($response->json('sections'))->keyBy('key');
        $this->assertSame(['high_visit', 'qf'], $sections->keys()->all());

        $visit = $sections['high_visit']['rows'];
        $this->assertCount(1, $visit);
        $this->assertSame('180', $visit[0]['key']);
        $this->assertSame('iron', $visit[0]['mine']['levelName']);
        $this->assertSame('locked', $visit[0]['theirs']['levelName']);
        $this->assertSame('images/badges/visits/180/iron.png', $visit[0]['theirs']['image']);

        $qf = $sections['qf']['rows'];
        $this->assertCount(1, $qf);
        $this->assertSame('9', $qf[0]['key']);
        $this->assertSame('locked', $qf[0]['mine']['levelName']);
        $this->assertSame('images/badges/qf/9/iron.png', $qf[0]['mine']['image']);
        $this->assertSame('iron', $qf[0]['theirs']['levelName']);

        $this->assertCount(CheckoutCatalog::count(), $response->json('checkout.mine.items'));
        $this->assertCount(CheckoutCatalog::count(), $response->json('checkout.theirs.items'));
    }

    public function test_compare_requires_a_logged_in_viewer_with_a_different_player(): void
    {
        $this->getJson('/api/players/'.$this->bartek->id.'/badges/compare')
            ->assertUnauthorized();

        $this->get(route('players.badges.compare', $this->bartek))
            ->assertRedirect(route('pages.loginPanel'));

        Sanctum::actingAs($this->annaUser);
        $this->getJson('/api/players/'.$this->anna->id.'/badges/compare')
            ->assertNotFound();

        $this->actingAs($this->annaUser)
            ->get(route('players.badges.compare', $this->anna))
            ->assertNotFound();

        $bare = User::factory()->create();
        Sanctum::actingAs($bare);
        $this->getJson('/api/players/'.$this->bartek->id.'/badges/compare')
            ->assertNotFound();

        $guest = Player::create(['name' => 'Gość']);
        Sanctum::actingAs($this->annaUser);
        $this->getJson('/api/players/'.$guest->id.'/badges/compare')
            ->assertNotFound();
    }

    public function test_compare_button_is_only_on_someone_elses_profile(): void
    {
        $this->get(route('players.show', $this->bartek))
            ->assertOk()
            ->assertDontSee('Porównaj odznaczenia');

        $this->actingAs($this->annaUser)
            ->get(route('players.show', $this->anna))
            ->assertOk()
            ->assertDontSee('Porównaj odznaczenia');

        $this->actingAs($this->annaUser)
            ->get(route('players.show', $this->bartek))
            ->assertOk()
            ->assertSee('Porównaj odznaczenia');
    }

    public function test_web_compare_page_pairs_badges_and_shows_both_wheels(): void
    {
        $response = $this->actingAs($this->annaUser)
            ->get(route('players.badges.compare', $this->bartek));

        $response->assertOk()
            ->assertSee('Porównanie odznaczeń')
            ->assertSee('Anna')
            ->assertSee('Bartek')
            ->assertSee('Mocny punkciarz')
            ->assertSee('Szybka akcja')
            ->assertSee('QF 9')
            ->assertSee('is-locked')
            ->assertSee('badges/visits/180/iron.png')
            ->assertSee('badges/qf/9/iron.png')
            ->assertSee('Poziom 0')
            ->assertSee('Poziom 1')
            ->assertDontSee('Kariera')
            ->assertDontSee('140+')
            ->assertDontSee('Podium')
            ->assertDontSee('>Inne<', false);

        $this->assertSame(2, substr_count($response->getContent(), 'data-checkout-wheel'));
    }

    private function badge(Player $player, BadgeCategory $category, string $key, int $times): void
    {
        PlayerBadge::query()->create([
            'player_id' => $player->id,
            'category' => $category->value,
            'badge_key' => $key,
            'times_earned' => $times,
            'first_unlocked_at' => now(),
            'last_earned_at' => now(),
            'source_quality' => 'manual',
        ]);
    }
}
