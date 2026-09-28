<?php

namespace App\Domain\Retention;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class ApplicationEntityRetention
{
    public const DAYS = 90;

    public static function cutoff(?CarbonInterface $now = null): Carbon
    {
        return Carbon::parse($now ?? now())->subDays(self::DAYS);
    }
}
