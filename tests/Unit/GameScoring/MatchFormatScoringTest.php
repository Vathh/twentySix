<?php

namespace Tests\Unit\GameScoring;

use App\Domain\GameScoring\MatchFormat;
use App\Domain\GameScoring\MatchFormatScoring;
use PHPUnit\Framework\TestCase;

class MatchFormatScoringTest extends TestCase
{
    private const PLAYER_1 = 1;

    private const PLAYER_2 = 2;

    // --- applyLegWinToH2hGame — single set (BO legs, no sets) ---

    public function test_apply_leg_win_single_set_increments_score_without_finishing(): void
    {
        $format = new MatchFormat(legsToWinSet: 3, setsToWinMatch: 1);

        $result = MatchFormatScoring::applyLegWinToH2hGame(
            format: $format,
            winnerPlayerId: self::PLAYER_1,
            player1Id: self::PLAYER_1,
            player2Id: self::PLAYER_2,
            player1Score: 0,
            player2Score: 1,
            player1LegsInSet: 0,
            player2LegsInSet: 0,
            currentSetNumber: 1,
        );

        $this->assertSame([
            'finished' => false,
            'winnerId' => null,
            'player1Score' => 1,
            'player2Score' => 1,
            'player1LegsInSet' => 0,
            'player2LegsInSet' => 0,
            'currentSetNumber' => 1,
        ], $result);
    }

    public function test_apply_leg_win_single_set_finishes_game_when_legs_to_win_reached(): void
    {
        $format = new MatchFormat(legsToWinSet: 2, setsToWinMatch: 1);

        $result = MatchFormatScoring::applyLegWinToH2hGame(
            format: $format,
            winnerPlayerId: self::PLAYER_2,
            player1Id: self::PLAYER_1,
            player2Id: self::PLAYER_2,
            player1Score: 1,
            player2Score: 1,
            player1LegsInSet: 0,
            player2LegsInSet: 0,
            currentSetNumber: 1,
        );

        $this->assertSame([
            'finished' => true,
            'winnerId' => self::PLAYER_2,
            'player1Score' => 1,
            'player2Score' => 2,
            'player1LegsInSet' => 0,
            'player2LegsInSet' => 0,
            'currentSetNumber' => 1,
        ], $result);
    }

    public function test_apply_leg_win_single_set_is_pure_and_does_not_mutate_format(): void
    {
        $format = new MatchFormat(legsToWinSet: 3, setsToWinMatch: 1);

        MatchFormatScoring::applyLegWinToH2hGame(
            format: $format,
            winnerPlayerId: self::PLAYER_1,
            player1Id: self::PLAYER_1,
            player2Id: self::PLAYER_2,
            player1Score: 0,
            player2Score: 0,
            player1LegsInSet: 0,
            player2LegsInSet: 0,
            currentSetNumber: 1,
        );

        $this->assertSame(3, $format->legsToWinSet);
        $this->assertSame(1, $format->setsToWinMatch);
    }

    public function test_apply_leg_win_best_of_even_finishes_as_draw(): void
    {
        $format = MatchFormat::forLeagueRules(501, \App\Enums\MatchWinMode::BEST_OF, 6);

        $result = MatchFormatScoring::applyLegWinToH2hGame(
            format: $format,
            winnerPlayerId: self::PLAYER_2,
            player1Id: self::PLAYER_1,
            player2Id: self::PLAYER_2,
            player1Score: 3,
            player2Score: 2,
            player1LegsInSet: 0,
            player2LegsInSet: 0,
            currentSetNumber: 1,
        );

        $this->assertSame([
            'finished' => true,
            'winnerId' => null,
            'player1Score' => 3,
            'player2Score' => 3,
            'player1LegsInSet' => 0,
            'player2LegsInSet' => 0,
            'currentSetNumber' => 1,
        ], $result);
    }

    public function test_apply_leg_win_best_of_finishes_at_win_target_early(): void
    {
        $format = MatchFormat::forLeagueRules(501, \App\Enums\MatchWinMode::BEST_OF, 6);

        $result = MatchFormatScoring::applyLegWinToH2hGame(
            format: $format,
            winnerPlayerId: self::PLAYER_1,
            player1Id: self::PLAYER_1,
            player2Id: self::PLAYER_2,
            player1Score: 3,
            player2Score: 1,
            player1LegsInSet: 0,
            player2LegsInSet: 0,
            currentSetNumber: 1,
        );

        $this->assertTrue($result['finished']);
        $this->assertSame(self::PLAYER_1, $result['winnerId']);
        $this->assertSame(4, $result['player1Score']);
        $this->assertSame(1, $result['player2Score']);
    }

    // --- applyLegWinToH2hGame — multi set ---

    public function test_apply_leg_win_multi_set_increments_legs_in_set_without_closing_set(): void
    {
        $format = new MatchFormat(legsToWinSet: 3, setsToWinMatch: 3);

        $result = MatchFormatScoring::applyLegWinToH2hGame(
            format: $format,
            winnerPlayerId: self::PLAYER_1,
            player1Id: self::PLAYER_1,
            player2Id: self::PLAYER_2,
            player1Score: 0,
            player2Score: 0,
            player1LegsInSet: 1,
            player2LegsInSet: 1,
            currentSetNumber: 1,
        );

        $this->assertSame([
            'finished' => false,
            'winnerId' => null,
            'player1Score' => 0,
            'player2Score' => 0,
            'player1LegsInSet' => 2,
            'player2LegsInSet' => 1,
            'currentSetNumber' => 1,
        ], $result);
    }

    public function test_apply_leg_win_multi_set_closes_set_and_resets_legs(): void
    {
        $format = new MatchFormat(legsToWinSet: 3, setsToWinMatch: 3);

        $result = MatchFormatScoring::applyLegWinToH2hGame(
            format: $format,
            winnerPlayerId: self::PLAYER_1,
            player1Id: self::PLAYER_1,
            player2Id: self::PLAYER_2,
            player1Score: 0,
            player2Score: 1,
            player1LegsInSet: 2,
            player2LegsInSet: 1,
            currentSetNumber: 1,
        );

        $this->assertSame([
            'finished' => false,
            'winnerId' => null,
            'player1Score' => 1,
            'player2Score' => 1,
            'player1LegsInSet' => 0,
            'player2LegsInSet' => 0,
            'currentSetNumber' => 2,
        ], $result);
    }

    public function test_apply_leg_win_multi_set_finishes_match_when_sets_to_win_reached(): void
    {
        $format = new MatchFormat(legsToWinSet: 3, setsToWinMatch: 3);

        $result = MatchFormatScoring::applyLegWinToH2hGame(
            format: $format,
            winnerPlayerId: self::PLAYER_1,
            player1Id: self::PLAYER_1,
            player2Id: self::PLAYER_2,
            player1Score: 2,
            player2Score: 1,
            player1LegsInSet: 2,
            player2LegsInSet: 0,
            currentSetNumber: 3,
        );

        $this->assertSame([
            'finished' => true,
            'winnerId' => self::PLAYER_1,
            'player1Score' => 3,
            'player2Score' => 1,
            'player1LegsInSet' => 0,
            'player2LegsInSet' => 0,
            'currentSetNumber' => 4,
        ], $result);
    }

    // --- revertLegWinOnH2hGame — single set ---

    public function test_revert_leg_win_single_set_decrements_score(): void
    {
        $format = new MatchFormat(legsToWinSet: 3, setsToWinMatch: 1);

        $result = MatchFormatScoring::revertLegWinOnH2hGame(
            format: $format,
            legWinnerId: self::PLAYER_1,
            player1Id: self::PLAYER_1,
            player2Id: self::PLAYER_2,
            player1Score: 1,
            player2Score: 1,
            player1LegsInSet: 0,
            player2LegsInSet: 0,
            currentSetNumber: 1,
        );

        $this->assertSame([
            'player1Score' => 0,
            'player2Score' => 1,
            'player1LegsInSet' => 0,
            'player2LegsInSet' => 0,
            'currentSetNumber' => 1,
        ], $result);
    }

    public function test_revert_leg_win_with_null_winner_is_noop(): void
    {
        $format = new MatchFormat(legsToWinSet: 3, setsToWinMatch: 1);

        $result = MatchFormatScoring::revertLegWinOnH2hGame(
            format: $format,
            legWinnerId: null,
            player1Id: self::PLAYER_1,
            player2Id: self::PLAYER_2,
            player1Score: 1,
            player2Score: 1,
            player1LegsInSet: 0,
            player2LegsInSet: 0,
            currentSetNumber: 1,
        );

        $this->assertSame([
            'player1Score' => 1,
            'player2Score' => 1,
            'player1LegsInSet' => 0,
            'player2LegsInSet' => 0,
            'currentSetNumber' => 1,
        ], $result);
    }

    public function test_revert_leg_win_single_set_does_not_go_below_zero(): void
    {
        $format = new MatchFormat(legsToWinSet: 3, setsToWinMatch: 1);

        $result = MatchFormatScoring::revertLegWinOnH2hGame(
            format: $format,
            legWinnerId: self::PLAYER_1,
            player1Id: self::PLAYER_1,
            player2Id: self::PLAYER_2,
            player1Score: 0,
            player2Score: 0,
            player1LegsInSet: 0,
            player2LegsInSet: 0,
            currentSetNumber: 1,
        );

        $this->assertSame(0, $result['player1Score']);
    }

    // --- revertLegWinOnH2hGame — multi set ---

    public function test_revert_leg_win_multi_set_decrements_legs_in_set(): void
    {
        $format = new MatchFormat(legsToWinSet: 3, setsToWinMatch: 3);

        $result = MatchFormatScoring::revertLegWinOnH2hGame(
            format: $format,
            legWinnerId: self::PLAYER_1,
            player1Id: self::PLAYER_1,
            player2Id: self::PLAYER_2,
            player1Score: 0,
            player2Score: 0,
            player1LegsInSet: 2,
            player2LegsInSet: 1,
            currentSetNumber: 1,
        );

        $this->assertSame([
            'player1Score' => 0,
            'player2Score' => 0,
            'player1LegsInSet' => 1,
            'player2LegsInSet' => 1,
            'currentSetNumber' => 1,
        ], $result);
    }

    public function test_revert_leg_win_multi_set_reopens_previous_set_when_it_was_just_closed(): void
    {
        $format = new MatchFormat(legsToWinSet: 3, setsToWinMatch: 3);

        // Stan tuż po tym, jak gracz 1 domknął set 1 (przechodząc do seta 2 z legami wyzerowanymi).
        $result = MatchFormatScoring::revertLegWinOnH2hGame(
            format: $format,
            legWinnerId: self::PLAYER_1,
            player1Id: self::PLAYER_1,
            player2Id: self::PLAYER_2,
            player1Score: 1,
            player2Score: 0,
            player1LegsInSet: 0,
            player2LegsInSet: 0,
            currentSetNumber: 2,
        );

        $this->assertSame([
            'player1Score' => 0,
            'player2Score' => 0,
            'player1LegsInSet' => 2,
            'player2LegsInSet' => 0,
            'currentSetNumber' => 1,
        ], $result);
    }

    public function test_revert_leg_win_multi_set_does_not_go_below_set_number_one(): void
    {
        $format = new MatchFormat(legsToWinSet: 3, setsToWinMatch: 3);

        $result = MatchFormatScoring::revertLegWinOnH2hGame(
            format: $format,
            legWinnerId: self::PLAYER_1,
            player1Id: self::PLAYER_1,
            player2Id: self::PLAYER_2,
            player1Score: 1,
            player2Score: 0,
            player1LegsInSet: 0,
            player2LegsInSet: 0,
            currentSetNumber: 1,
        );

        $this->assertSame(1, $result['currentSetNumber']);
    }

    // --- applyLegWinToFfa ---

    public function test_apply_leg_win_to_ffa_single_set_increments_legs_won(): void
    {
        $format = new MatchFormat(legsToWinSet: 2, setsToWinMatch: 1);

        $result = MatchFormatScoring::applyLegWinToFfa(
            format: $format,
            winnerPlayerId: self::PLAYER_1,
            legsWonInSet: [self::PLAYER_1 => 0, self::PLAYER_2 => 1],
            setsWon: [self::PLAYER_1 => 0, self::PLAYER_2 => 0],
            currentSetNumber: 1,
        );

        $this->assertSame([
            'finished' => false,
            'legsWonInSet' => [self::PLAYER_1 => 1, self::PLAYER_2 => 1],
            'setsWon' => [self::PLAYER_1 => 0, self::PLAYER_2 => 0],
            'currentSetNumber' => 1,
        ], $result);
    }

    public function test_apply_leg_win_to_ffa_single_set_finishes_when_legs_to_win_reached(): void
    {
        $format = new MatchFormat(legsToWinSet: 2, setsToWinMatch: 1);

        $result = MatchFormatScoring::applyLegWinToFfa(
            format: $format,
            winnerPlayerId: self::PLAYER_2,
            legsWonInSet: [self::PLAYER_1 => 1, self::PLAYER_2 => 1],
            setsWon: [self::PLAYER_1 => 0, self::PLAYER_2 => 0],
            currentSetNumber: 1,
        );

        $this->assertSame([
            'finished' => true,
            'legsWonInSet' => [self::PLAYER_1 => 1, self::PLAYER_2 => 2],
            'setsWon' => [self::PLAYER_1 => 0, self::PLAYER_2 => 1],
            'currentSetNumber' => 1,
        ], $result);
    }

    public function test_apply_leg_win_to_ffa_multi_set_closes_set_and_resets_all_players_legs(): void
    {
        $format = new MatchFormat(legsToWinSet: 2, setsToWinMatch: 3);

        $result = MatchFormatScoring::applyLegWinToFfa(
            format: $format,
            winnerPlayerId: self::PLAYER_1,
            legsWonInSet: [self::PLAYER_1 => 1, self::PLAYER_2 => 1, 3 => 0],
            setsWon: [self::PLAYER_1 => 0, self::PLAYER_2 => 0, 3 => 0],
            currentSetNumber: 1,
        );

        $this->assertSame([
            'finished' => false,
            'legsWonInSet' => [self::PLAYER_1 => 0, self::PLAYER_2 => 0, 3 => 0],
            'setsWon' => [self::PLAYER_1 => 1, self::PLAYER_2 => 0, 3 => 0],
            'currentSetNumber' => 2,
        ], $result);
    }

    public function test_apply_leg_win_to_ffa_multi_set_finishes_when_sets_to_win_reached(): void
    {
        $format = new MatchFormat(legsToWinSet: 2, setsToWinMatch: 2);

        $result = MatchFormatScoring::applyLegWinToFfa(
            format: $format,
            winnerPlayerId: self::PLAYER_1,
            legsWonInSet: [self::PLAYER_1 => 1, self::PLAYER_2 => 0],
            setsWon: [self::PLAYER_1 => 1, self::PLAYER_2 => 0],
            currentSetNumber: 2,
        );

        $this->assertSame([
            'finished' => true,
            'legsWonInSet' => [self::PLAYER_1 => 0, self::PLAYER_2 => 0],
            'setsWon' => [self::PLAYER_1 => 2, self::PLAYER_2 => 0],
            'currentSetNumber' => 3,
        ], $result);
    }

    // --- revertLegWinOnFfa ---

    public function test_revert_leg_win_on_ffa_single_set_decrements_legs_won(): void
    {
        $format = new MatchFormat(legsToWinSet: 2, setsToWinMatch: 1);

        $result = MatchFormatScoring::revertLegWinOnFfa(
            format: $format,
            legWinnerId: self::PLAYER_1,
            legsWonInSet: [self::PLAYER_1 => 1, self::PLAYER_2 => 1],
            setsWon: [self::PLAYER_1 => 0, self::PLAYER_2 => 0],
            currentSetNumber: 1,
        );

        $this->assertSame([
            'legsWonInSet' => [self::PLAYER_1 => 0, self::PLAYER_2 => 1],
            'setsWon' => [self::PLAYER_1 => 0, self::PLAYER_2 => 0],
            'currentSetNumber' => 1,
        ], $result);
    }

    public function test_revert_leg_win_on_ffa_single_set_does_not_go_below_zero(): void
    {
        $format = new MatchFormat(legsToWinSet: 2, setsToWinMatch: 1);

        $result = MatchFormatScoring::revertLegWinOnFfa(
            format: $format,
            legWinnerId: self::PLAYER_1,
            legsWonInSet: [self::PLAYER_1 => 0, self::PLAYER_2 => 0],
            setsWon: [self::PLAYER_1 => 0, self::PLAYER_2 => 0],
            currentSetNumber: 1,
        );

        $this->assertSame(0, $result['legsWonInSet'][self::PLAYER_1]);
    }

    public function test_revert_leg_win_on_ffa_multi_set_reopens_previous_set_when_it_was_just_closed(): void
    {
        $format = new MatchFormat(legsToWinSet: 2, setsToWinMatch: 3);

        // Stan tuż po tym, jak gracz 1 domknął set 1 (legi wyzerowane u wszystkich, sety +1).
        $result = MatchFormatScoring::revertLegWinOnFfa(
            format: $format,
            legWinnerId: self::PLAYER_1,
            legsWonInSet: [self::PLAYER_1 => 0, self::PLAYER_2 => 0],
            setsWon: [self::PLAYER_1 => 1, self::PLAYER_2 => 0],
            currentSetNumber: 2,
        );

        $this->assertSame([
            'legsWonInSet' => [self::PLAYER_1 => 1, self::PLAYER_2 => 0],
            'setsWon' => [self::PLAYER_1 => 0, self::PLAYER_2 => 0],
            'currentSetNumber' => 1,
        ], $result);
    }

    public function test_revert_leg_win_on_ffa_multi_set_does_not_go_below_set_number_one(): void
    {
        $format = new MatchFormat(legsToWinSet: 2, setsToWinMatch: 3);

        $result = MatchFormatScoring::revertLegWinOnFfa(
            format: $format,
            legWinnerId: self::PLAYER_1,
            legsWonInSet: [self::PLAYER_1 => 0, self::PLAYER_2 => 0],
            setsWon: [self::PLAYER_1 => 1, self::PLAYER_2 => 0],
            currentSetNumber: 1,
        );

        $this->assertSame(1, $result['currentSetNumber']);
    }

    // --- legsWonForDisplay ---

    public function test_legs_won_for_display_returns_legs_in_set_for_single_set_format(): void
    {
        $format = new MatchFormat(legsToWinSet: 2, setsToWinMatch: 1);

        $result = MatchFormatScoring::legsWonForDisplay(
            $format,
            legsWonInSet: [self::PLAYER_1 => 1, self::PLAYER_2 => 0],
            setsWon: [self::PLAYER_1 => 0, self::PLAYER_2 => 0],
        );

        $this->assertSame([self::PLAYER_1 => 1, self::PLAYER_2 => 0], $result);
    }

    public function test_legs_won_for_display_returns_sets_won_for_multi_set_format(): void
    {
        $format = new MatchFormat(legsToWinSet: 2, setsToWinMatch: 3);

        $result = MatchFormatScoring::legsWonForDisplay(
            $format,
            legsWonInSet: [self::PLAYER_1 => 1, self::PLAYER_2 => 0],
            setsWon: [self::PLAYER_1 => 1, self::PLAYER_2 => 0],
        );

        $this->assertSame([self::PLAYER_1 => 1, self::PLAYER_2 => 0], $result);
    }
}
