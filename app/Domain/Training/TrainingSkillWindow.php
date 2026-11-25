<?php

namespace App\Domain\Training;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Toczące okno skill-statystyk treningu: N×30 dni wstecz + offset okien.
 */
final class TrainingSkillWindow
{
    public const TIMEZONE = 'Europe/Warsaw';

    public const DEFAULT_KEY = '90d';

    /** @var list<string> */
    public const KEYS = ['90d', '180d', '270d', '360d'];

    public const SECTOR_DAYS = 30;

    public function __construct(
        public readonly string $key,
        public readonly int $days,
        public readonly int $offset,
    ) {}

    public static function fromQuery(?string $window, int $offset = 0): self
    {
        $key = $window ?: self::DEFAULT_KEY;
        if (! in_array($key, self::KEYS, true)) {
            throw new InvalidArgumentException('Nieznane okno skill-statystyk.');
        }

        $days = (int) substr($key, 0, -1);

        return new self($key, $days, max(0, $offset));
    }

    public static function sector(int $offset = 0): self
    {
        return new self('30d', self::SECTOR_DAYS, max(0, $offset));
    }

    public function endExclusiveUtc(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE)
            ->startOfDay()
            ->addDay()
            ->subDays($this->offset * $this->days)
            ->utc();
    }

    public function startUtc(): CarbonImmutable
    {
        return $this->endExclusiveUtc()->subDays($this->days);
    }

    public function rangeStartDate(): string
    {
        return $this->startUtc()->timezone(self::TIMEZONE)->toDateString();
    }

    public function rangeEndDate(): string
    {
        return $this->endExclusiveUtc()
            ->timezone(self::TIMEZONE)
            ->subDay()
            ->toDateString();
    }
}
