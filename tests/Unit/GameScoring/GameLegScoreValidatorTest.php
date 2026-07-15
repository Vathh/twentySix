<?php

namespace Tests\Unit\GameScoring;

use App\Support\GameScoring\GameLegScoreValidator;
use App\Support\GameScoring\MatchFormat;
use DomainException;
use PHPUnit\Framework\TestCase;

class GameLegScoreValidatorTest extends TestCase
{
    private MatchFormat $bo3Legs;

    private MatchFormat $bo3Sets;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bo3Legs = MatchFormat::default();
        $this->bo3Sets = new MatchFormat(
            startingScore: 501,
            legsToWinSet: 3,
            setsToWinMatch: 2,
        );
    }

    public function test_valid_leg_scores_resolve_winner(): void
    {
        $winner = GameLegScoreValidator::validateAndResolveWinner(1, 2, 2, 0, $this->bo3Legs);

        $this->assertSame(1, $winner);

        $winner = GameLegScoreValidator::validateAndResolveWinner(1, 2, 1, 2, $this->bo3Legs);

        $this->assertSame(2, $winner);
    }

    public function test_valid_set_scores_resolve_winner(): void
    {
        $winner = GameLegScoreValidator::validateAndResolveWinner(1, 2, 2, 0, $this->bo3Sets);

        $this->assertSame(1, $winner);

        $winner = GameLegScoreValidator::validateAndResolveWinner(1, 2, 1, 2, $this->bo3Sets);

        $this->assertSame(2, $winner);
    }

    public function test_rejects_draw(): void
    {
        $this->expectException(DomainException::class);

        GameLegScoreValidator::validateAndResolveWinner(1, 2, 1, 1, $this->bo3Legs);
    }

    public function test_rejects_invalid_leg_totals(): void
    {
        $this->expectException(DomainException::class);

        GameLegScoreValidator::validateAndResolveWinner(1, 2, 1, 0, $this->bo3Legs);
    }

    public function test_rejects_invalid_set_totals(): void
    {
        $this->expectException(DomainException::class);

        GameLegScoreValidator::validateAndResolveWinner(1, 2, 1, 0, $this->bo3Sets);
    }

    public function test_walkover_scores_single_set(): void
    {
        $this->assertSame([2, 0], GameLegScoreValidator::walkoverScores(10, 10, $this->bo3Legs));
        $this->assertSame([0, 2], GameLegScoreValidator::walkoverScores(11, 10, $this->bo3Legs));
    }

    public function test_walkover_scores_multi_set(): void
    {
        $this->assertSame([2, 0], GameLegScoreValidator::walkoverScores(10, 10, $this->bo3Sets));
        $this->assertSame([0, 2], GameLegScoreValidator::walkoverScores(11, 10, $this->bo3Sets));
    }

    public function test_walkover_three_legs_to_win(): void
    {
        $format = new MatchFormat(legsToWinSet: 3, setsToWinMatch: 1);

        $this->assertSame([3, 0], GameLegScoreValidator::walkoverScores(5, 5, $format));
    }
}
