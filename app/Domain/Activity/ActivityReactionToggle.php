<?php

namespace App\Domain\Activity;

final class ActivityReactionToggle
{
    public function next(?string $current, ?ActivityReactionKind $requested): ?ActivityReactionKind
    {
        if ($requested === null) {
            return null;
        }

        if ($current === $requested->value) {
            return null;
        }

        return $requested;
    }
}
