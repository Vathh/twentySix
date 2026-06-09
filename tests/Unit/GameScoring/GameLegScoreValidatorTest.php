<?php

namespace Tests\Unit\GameScoring;

use App\Support\GameScoring\GameLegScoreValidator;
use DomainException;
use PHPUnit\Framework\TestCase;

class GameLegScoreValidatorTest extends TestCase
{
    public function test_valid_scores_resolve_winner(): void
    {
        $winner = GameLegScoreValidator::validateAndResolveWinner(1, 2, 2, 0);

        $this->assertSame(1, $winner);

        $winner = GameLegScoreValidator::validateAndResolveWinner(1, 2, 1, 2);

        $this->assertSame(2, $winner);
    }

    public function test_rejects_draw(): void
    {
        $this->expectException(DomainException::class);

        GameLegScoreValidator::validateAndResolveWinner(1, 2, 1, 1);
    }

    public function test_rejects_invalid_totals(): void
    {
        $this->expectException(DomainException::class);

        GameLegScoreValidator::validateAndResolveWinner(1, 2, 1, 0);
    }

    public function test_walkover_scores(): void
    {
        $this->assertSame([2, 0], GameLegScoreValidator::walkoverScores(10, 10));
        $this->assertSame([0, 2], GameLegScoreValidator::walkoverScores(11, 10));
    }
}
