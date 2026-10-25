<?php

namespace Tests\Unit\Tournament;

use App\Domain\Game\PlayoffGameDomain;
use App\Enums\BracketSide;
use App\Enums\GameStage;
use App\Enums\TournamentFormat;
use App\Models\Tournament\Tournament;
use App\Services\Tournament\PlayoffBracketOutline;
use Tests\TestCase;

class PlayoffBracketOutlineTest extends TestCase
{
    public function test_group_phase_gets_a_full_empty_main_bracket(): void
    {
        $filled = $this->outline()->fill($this->tournament(4), [], BracketSide::Main);

        $this->assertCount(2, $filled[GameStage::SEMI->value]);
        $this->assertCount(1, $filled[GameStage::FINAL->value]);
        $this->assertCount(1, $filled[GameStage::THIRD->value]);
        $this->assertNull($filled[GameStage::SEMI->value][0]->id);
        $this->assertNull($filled[GameStage::SEMI->value][0]->player1Id);
    }

    public function test_known_match_keeps_its_slot_inside_the_empty_bracket(): void
    {
        $known = PlayoffGameDomain::createForBracket(
            tournamentId: 3,
            round: GameStage::SEMI,
            slot: 'SEMI_1',
        )->withPlayerIds(11, 22);

        $filled = $this->outline()->fill(
            $this->tournament(4),
            [GameStage::SEMI->value => [$known]],
            BracketSide::Main,
        );

        $semi = collect($filled[GameStage::SEMI->value])->keyBy(fn (PlayoffGameDomain $game) => $game->slot);

        $this->assertSame($known, $semi->get('SEMI_1'));
        $this->assertSame(11, $semi->get('SEMI_1')->player1Id);
        $this->assertNull($semi->get('SEMI_2')->player1Id);
    }

    public function test_consolation_stays_hidden_until_the_tournament_has_one(): void
    {
        $tournament = $this->tournament(4);
        $tournament->has_consolation_bracket = false;

        $this->assertSame([], $this->outline()->fill($tournament, [], BracketSide::Consolation));
    }

    public function test_consolation_without_third_place_omits_that_match(): void
    {
        $tournament = $this->tournament(8);
        $tournament->has_consolation_bracket = true;
        $tournament->consolation_bracket_size = 4;
        $tournament->has_consolation_third_place = false;

        $filled = $this->outline()->fill($tournament, [], BracketSide::Consolation);

        $this->assertArrayNotHasKey(GameStage::THIRD->value, $filled);
        $this->assertSame('C_SEMI_1', $filled[GameStage::SEMI->value][0]->slot);
        $this->assertSame('C_FINAL', $filled[GameStage::FINAL->value][0]->slot);
    }

    private function outline(): PlayoffBracketOutline
    {
        return app(PlayoffBracketOutline::class);
    }

    private function tournament(int $bracketSize): Tournament
    {
        $tournament = new Tournament;
        $tournament->id = 3;
        $tournament->format = TournamentFormat::GroupsPlayoff;
        $tournament->playoff_bracket_size = $bracketSize;

        return $tournament;
    }
}
