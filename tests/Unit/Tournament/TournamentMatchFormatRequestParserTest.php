<?php

namespace Tests\Unit\Tournament;

use App\Enums\GameStage;
use App\Support\GameScoring\MatchFormat;
use App\Support\Tournament\TournamentMatchFormatRequestParser;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TournamentMatchFormatRequestParserTest extends TestCase
{
    public function test_parses_formats_for_active_stages_only(): void
    {
        $formats = TournamentMatchFormatRequestParser::fromRunInput([
            'matchFormats' => [
                GameStage::GROUP->value => [
                    'startingScore' => 301,
                    'legsToWinSet' => 3,
                    'setsToWinMatch' => 1,
                ],
                GameStage::SEMI->value => [
                    'startingScore' => 501,
                    'legsToWinSet' => 5,
                    'setsToWinMatch' => 2,
                ],
                GameStage::THIRD->value => [
                    'startingScore' => 501,
                    'legsToWinSet' => 2,
                    'setsToWinMatch' => 1,
                ],
                GameStage::FINAL->value => [
                    'startingScore' => 501,
                    'legsToWinSet' => 7,
                    'setsToWinMatch' => 1,
                ],
            ],
        ], 4);

        $this->assertSame(301, $formats[GameStage::GROUP->value]['startingScore']);
        $this->assertSame(5, $formats[GameStage::SEMI->value]['legsToWinSet']);
        $this->assertSame(2, $formats[GameStage::SEMI->value]['setsToWinMatch']);
        $this->assertSame(7, $formats[GameStage::FINAL->value]['legsToWinSet']);
    }

    public function test_rejects_invalid_starting_score(): void
    {
        $this->expectException(ValidationException::class);

        TournamentMatchFormatRequestParser::fromRunInput([
            'matchFormats' => [
                GameStage::GROUP->value => [
                    'startingScore' => 999,
                    'legsToWinSet' => 2,
                    'setsToWinMatch' => 1,
                ],
                GameStage::FINAL->value => MatchFormat::default()->toArray(),
            ],
        ], 2);
    }
}
