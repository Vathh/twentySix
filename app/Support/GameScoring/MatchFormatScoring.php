<?php

namespace App\Support\GameScoring;

/**
 * BC shim (Finding 18 — Domain B strangler): logika przeniesiona do
 * `App\Domain\GameScoring\MatchFormatScoring`. Ten alias zachowuje istniejące
 * `use App\Support\GameScoring\MatchFormatScoring;` bez zmiany zachowania.
 */
class_alias(\App\Domain\GameScoring\MatchFormatScoring::class, __NAMESPACE__.'\MatchFormatScoring');
