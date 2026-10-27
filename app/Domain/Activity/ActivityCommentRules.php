<?php

namespace App\Domain\Activity;

use App\Models\Activity\ActivityComment;

final class ActivityCommentRules
{
    public const MAX_LENGTH = 500;

    public function normalizedBody(string $body): string
    {
        return trim($body);
    }

    public function parentFits(?ActivityComment $parent, int $activityId): bool
    {
        if ($parent === null) {
            return false;
        }

        return (int) $parent->activity_id === $activityId && $parent->parent_id === null;
    }
}
