<?php

namespace App\Enums;

enum PlayoffSlot: string
{
    case QF_1_A = 'QF_1_A';
    case QF_1_B = 'QF_1_B';
    case QF_2_A = 'QF_2_A';
    case QF_2_B = 'QF_2_B';
    case QF_3_A = 'QF_3_A';
    case QF_3_B = 'QF_3_B';
    case QF_4_A = 'QF_4_A';
    case QF_4_B = 'QF_4_B';
    case SEMI_1_A = 'SEMI_1_A';
    case SEMI_1_B = 'SEMI_1_B';
    case SEMI_2_A = 'SEMI_2_A';
    case SEMI_2_B = 'SEMI_2_B';
    case FINAL_A = 'FINAL_A';
    case FINAL_B = 'FINAL_B';
    case THIRD_A = 'THIRD_A';
    case THIRD_B = 'THIRD_B';
}
