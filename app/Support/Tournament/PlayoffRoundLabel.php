<?php

namespace App\Support\Tournament;

use App\Enums\BracketSide;
use App\Enums\GameStage;

/**
 * Etykiety rund playoff (SE używa wartości GameStage; DE: W0, L1, GF, …).
 */
final class PlayoffRoundLabel
{
    public static function label(string $round): string
    {
        $stage = GameStage::tryFrom($round);
        if ($stage !== null) {
            return $stage->label();
        }

        if ($round === 'GF' || $round === 'GF1') {
            return 'Wielki finał';
        }
        if ($round === 'GF2') {
            return 'Wielki finał — reset';
        }
        if (preg_match('/^[WL](\d+)$/', $round, $m)) {
            return 'Runda '.((int) $m[1] + 1);
        }

        return $round;
    }

    /** Etykieta na liście meczów / overlay — bez skrótów WB/LB. */
    public static function listLabel(string $round): string
    {
        $stage = GameStage::tryFrom($round);
        if ($stage !== null) {
            return $stage->label();
        }

        if ($round === 'GF' || $round === 'GF1') {
            return 'Wielki finał';
        }
        if ($round === 'GF2') {
            return 'Wielki finał — reset';
        }
        if (preg_match('/^W(\d+)$/', $round, $m)) {
            return 'Drabinka wygranych — runda '.((int) $m[1] + 1);
        }
        if (preg_match('/^L(\d+)$/', $round, $m)) {
            return 'Drabinka przegranych — runda '.((int) $m[1] + 1);
        }

        return $round;
    }

    public static function resultLabel(string $round, BracketSide $side = BracketSide::Main): string
    {
        $base = self::label($round);
        if ($side === BracketSide::Consolation) {
            return 'Pocieszenie — '.$base;
        }

        return $base;
    }

    /** Etykieta dla widza (overlay / transmisja). */
    public static function broadcastLabel(string $round): string
    {
        return self::listLabel($round);
    }
}
