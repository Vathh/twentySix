<?php

namespace Tests\Unit\Tournament;

use App\Enums\GameStage;
use App\Support\Tournament\PlayoffRoundLabel;
use PHPUnit\Framework\TestCase;

class PlayoffRoundLabelTest extends TestCase
{
    public function test_compact_label_uses_polish_round_number(): void
    {
        $this->assertSame('Runda 1', PlayoffRoundLabel::label('W0'));
        $this->assertSame('Runda 2', PlayoffRoundLabel::label('L1'));
        $this->assertSame('Wielki finał', PlayoffRoundLabel::label('GF'));
        $this->assertSame('Finał', PlayoffRoundLabel::label(GameStage::FINAL->value));
    }

    public function test_list_label_spells_out_brackets(): void
    {
        $this->assertSame('Drabinka wygranych — runda 1', PlayoffRoundLabel::listLabel('W0'));
        $this->assertSame('Drabinka przegranych — runda 2', PlayoffRoundLabel::listLabel('L1'));
        $this->assertSame('Drabinka wygranych — runda 3', PlayoffRoundLabel::listLabel('W2'));
        $this->assertSame('Wielki finał', PlayoffRoundLabel::broadcastLabel('GF'));
        $this->assertSame('Wielki finał — reset', PlayoffRoundLabel::broadcastLabel('GF2'));
        $this->assertSame('1/8 finału', PlayoffRoundLabel::broadcastLabel(GameStage::EIGHT->value));
        $this->assertSame('Finał', PlayoffRoundLabel::broadcastLabel(GameStage::FINAL->value));
    }
}
