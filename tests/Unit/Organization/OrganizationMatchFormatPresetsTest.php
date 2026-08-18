<?php

namespace Tests\Unit\Organization;

use App\Enums\GameStage;
use App\Domain\GameScoring\MatchFormat;
use App\Support\Organization\OrganizationMatchFormatPresets;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrganizationMatchFormatPresetsTest extends TestCase
{
    public function test_defaults_by_stage_without_presets_uses_match_format_default(): void
    {
        $defaults = OrganizationMatchFormatPresets::defaultsByStage(null);
        $expected = MatchFormat::default()->toArray();

        $this->assertSame($expected, $defaults[GameStage::GROUP->value]);
        $this->assertSame($expected, $defaults[GameStage::FINAL->value]);
    }

    public function test_defaults_by_stage_merges_organization_presets(): void
    {
        $defaults = OrganizationMatchFormatPresets::defaultsByStage([
            GameStage::GROUP->value => [
                'startingScore' => 301,
                'legsToWinSet' => 2,
                'setsToWinMatch' => 1,
            ],
            GameStage::FINAL->value => [
                'startingScore' => 501,
                'legsToWinSet' => 5,
                'setsToWinMatch' => 1,
            ],
        ]);

        $this->assertSame(301, $defaults[GameStage::GROUP->value]['startingScore']);
        $this->assertSame(5, $defaults[GameStage::FINAL->value]['legsToWinSet']);
        $this->assertSame(
            MatchFormat::DEFAULT_LEGS_TO_WIN_SET,
            $defaults[GameStage::SEMI->value]['legsToWinSet'],
        );
    }

    public function test_from_form_input_rejects_invalid_score(): void
    {
        $this->expectException(ValidationException::class);

        $input = [];
        foreach (GameStage::cases() as $stage) {
            $input[$stage->value] = MatchFormat::default()->toArray();
        }
        $input[GameStage::GROUP->value]['startingScore'] = 999;

        OrganizationMatchFormatPresets::fromFormInput($input);
    }
}
