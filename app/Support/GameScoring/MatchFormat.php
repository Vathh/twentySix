<?php

namespace App\Support\GameScoring;

/**
 * BC shim (Finding 18 — Domain B strangler): logika przeniesiona do
 * `App\Domain\GameScoring\MatchFormat`. Ten alias zachowuje istniejące
 * `use App\Support\GameScoring\MatchFormat;` bez zmiany zachowania.
 */
class_alias(\App\Domain\GameScoring\MatchFormat::class, __NAMESPACE__.'\MatchFormat');
