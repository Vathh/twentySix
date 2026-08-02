<?php

namespace App\Support\GameScoring;

/**
 * BC shim (Finding 18 — Domain B strangler): logika przeniesiona do
 * `App\Domain\GameScoring\GameLegScoreValidator`. Ten alias zachowuje istniejące
 * `use App\Support\GameScoring\GameLegScoreValidator;` bez zmiany zachowania.
 */
class_alias(\App\Domain\GameScoring\GameLegScoreValidator::class, __NAMESPACE__.'\GameLegScoreValidator');
