<?php

namespace Tests\Unit\Match;

use App\Support\Match\MatchLegScoreValidator;
use DomainException;
use PHPUnit\Framework\TestCase;

class MatchLegScoreValidatorTest extends TestCase
{
    public function test_valid_scores_resolve_winner(): void
    {
        $winner = MatchLegScoreValidator::validateAndResolveWinner(1, 2, 2, 0);

        $this->assertSame(1, $winner);

        $winner = MatchLegScoreValidator::validateAndResolveWinner(1, 2, 1, 2);

        $this->assertSame(2, $winner);
    }

    public function test_rejects_draw(): void
    {
        $this->expectException(DomainException::class);

        MatchLegScoreValidator::validateAndResolveWinner(1, 2, 1, 1);
    }

    public function test_rejects_invalid_totals(): void
    {
        $this->expectException(DomainException::class);

        MatchLegScoreValidator::validateAndResolveWinner(1, 2, 1, 0);
    }

    public function test_walkover_scores(): void
    {
        $this->assertSame([2, 0], MatchLegScoreValidator::walkoverScores(10, 10));
        $this->assertSame([0, 2], MatchLegScoreValidator::walkoverScores(11, 10));
    }
}
