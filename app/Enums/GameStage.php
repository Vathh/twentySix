<?php

namespace App\Enums;

enum GameStage: string
{
    case GROUP = 'GROUP';
    case SIXTYFOUR = 'SIXTYFOUR';
    case THIRTYTWO = 'THIRTYTWO';
    case SIXTEEN = 'SIXTEEN';
    case EIGHT = 'EIGHT';
    case QUARTER = 'QUARTER';
    case SEMI = 'SEMI';
    case THIRD = 'THIRD';
    case FINAL = 'FINAL';

    public function label(): string
    {
        return match ($this) {
            self::GROUP => 'Faza grupowa',
            self::SIXTYFOUR => '1/64 finału',
            self::THIRTYTWO => '1/32 finału',
            self::SIXTEEN => '1/16 finału',
            self::EIGHT => '1/8 finału',
            self::QUARTER => 'Ćwierćfinał',
            self::SEMI => 'Półfinał',
            self::THIRD => 'Mecz o 3. miejsce',
            self::FINAL => 'Finał',
        };
    }

    /**
     * Etapy turnieju przy danej wielkości drabinki (z fazą grupową — groups_playoff).
     *
     * @return list<self>
     */
    public static function forPlayoffBracketSize(int $bracketSize): array
    {
        return array_merge([self::GROUP], self::eliminationStages($bracketSize, includeThird: true));
    }

    /**
     * Same rundy pucharowe bez GROUP (SE / DE / formaty meczów tylko playoff).
     *
     * @return list<self>
     */
    public static function forEliminationBracketSize(int $bracketSize, bool $includeThird = true): array
    {
        return self::eliminationStages($bracketSize, $includeThird);
    }

    /**
     * @return list<self>
     */
    private static function eliminationStages(int $bracketSize, bool $includeThird): array
    {
        $stages = [];

        if ($bracketSize >= 128) {
            $stages[] = self::SIXTYFOUR;
        }
        if ($bracketSize >= 64) {
            $stages[] = self::THIRTYTWO;
        }
        if ($bracketSize >= 32) {
            $stages[] = self::SIXTEEN;
        }
        if ($bracketSize >= 16) {
            $stages[] = self::EIGHT;
        }
        if ($bracketSize >= 8) {
            $stages[] = self::QUARTER;
        }
        if ($bracketSize >= 4) {
            $stages[] = self::SEMI;
            if ($includeThird) {
                $stages[] = self::THIRD;
            }
        }

        $stages[] = self::FINAL;

        return $stages;
    }

    /**
     * Rundy playoff z wspólnym miejscem ex aequo (od najbliższej półfinałów do najwcześniejszej).
     *
     * @return list<self>
     */
    public static function sharedPlacementStages(int $bracketSize): array
    {
        $early = array_values(array_filter(
            self::eliminationStages($bracketSize, includeThird: false),
            fn (self $stage) => ! in_array($stage, [self::SEMI, self::FINAL], true),
        ));

        return array_reverse($early);
    }
}
