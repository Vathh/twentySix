<?php

namespace Tests\Feature;

use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeChromeTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_in_home_leads_with_the_app_download_and_a_friends_rail(): void
    {
        $user = User::factory()->create();
        app(PlayerService::class)->create('Ala', $user->id);

        $html = $this->actingAs($user)->get(route('pages.home'))->assertOk()->getContent();

        $downloadAt = strpos($html, 'Pobierz aplikację mobilną');
        $feedAt = strpos($html, 'Na razie cisza');
        $this->assertNotFalse($downloadAt);
        $this->assertNotFalse($feedAt);
        $this->assertLessThan($feedAt, $downloadAt);
        $this->assertStringContainsString('friends-rail', $html);
        $this->assertStringContainsString('Schowaj znajomych', $html);
        $this->assertStringNotContainsString('Znajomi (', $html);
    }

    public function test_guest_home_keeps_the_download_above_the_hero(): void
    {
        $html = $this->get(route('pages.home'))->assertOk()->getContent();

        $downloadAt = strpos($html, 'Pobierz aplikację mobilną');
        $heroAt = strpos($html, 'Organizacje, turnieje, wyniki na żywo');
        $this->assertNotFalse($downloadAt);
        $this->assertNotFalse($heroAt);
        $this->assertLessThan($heroAt, $downloadAt);
        $this->assertStringNotContainsString('friends-rail', $html);
    }
}
