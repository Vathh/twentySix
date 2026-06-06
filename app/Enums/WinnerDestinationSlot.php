<?php

namespace App\Enums;

use App\Domain\Game\WinnerDestination;

enum WinnerDestinationSlot: string
{
    case EIGHT_1_A = 'EIGHT_1-A';
    case EIGHT_1_B = 'EIGHT_1-B';
    case EIGHT_2_A = 'EIGHT_2-A';
    case EIGHT_2_B = 'EIGHT_2-B';
    case EIGHT_3_A = 'EIGHT_3-A';
    case EIGHT_3_B = 'EIGHT_3-B';
    case EIGHT_4_A = 'EIGHT_4-A';
    case EIGHT_4_B = 'EIGHT_4-B';
    case EIGHT_5_A = 'EIGHT_5-A';
    case EIGHT_5_B = 'EIGHT_5-B';
    case EIGHT_6_A = 'EIGHT_6-A';
    case EIGHT_6_B = 'EIGHT_6-B';
    case EIGHT_7_A = 'EIGHT_7-A';
    case EIGHT_7_B = 'EIGHT_7-B';
    case EIGHT_8_A = 'EIGHT_8-A';
    case EIGHT_8_B = 'EIGHT_8-B';
    case QF_1_A = 'QF_1-A';
    case QF_1_B = 'QF_1-B';
    case QF_2_A = 'QF_2-A';
    case QF_2_B = 'QF_2-B';
    case QF_3_A = 'QF_3-A';
    case QF_3_B = 'QF_3-B';
    case QF_4_A = 'QF_4-A';
    case QF_4_B = 'QF_4-B';
    case SEMI_1_A = 'SEMI_1-A';
    case SEMI_1_B = 'SEMI_1-B';
    case SEMI_2_A = 'SEMI_2-A';
    case SEMI_2_B = 'SEMI_2-B';
    case FINAL_A = 'FINAL-A';
    case FINAL_B = 'FINAL-B';
    case THIRD_A = 'THIRD-A';
    case THIRD_B = 'THIRD-B';

    public function toDestination(): WinnerDestination
    {
        [$playoffSlot, $playerSlot] = explode('-', $this->value);

        return new WinnerDestination(PlayoffSlot::from($playoffSlot),
                                        PlayerSlot::from($playerSlot));
    }
}
