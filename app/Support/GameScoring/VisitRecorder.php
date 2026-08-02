<?php

namespace App\Support\GameScoring;

/**
 * BC shim (Finding 18 — Domain B strangler): logika przeniesiona do
 * `App\Domain\GameScoring\VisitRecorder`. Ten alias zachowuje istniejące
 * `use App\Support\GameScoring\VisitRecorder;` bez zmiany zachowania.
 */
class_alias(\App\Domain\GameScoring\VisitRecorder::class, __NAMESPACE__.'\VisitRecorder');
