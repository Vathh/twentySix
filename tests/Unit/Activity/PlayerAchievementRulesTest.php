<?php

namespace Tests\Unit\Activity;

use App\Domain\Activity\BadgeUnlock;
use App\Domain\Activity\Definitions\PlayerBadgesActivity;
use App\Domain\Activity\Definitions\PlayerPersonalBestActivity;
use App\Domain\Activity\PersonalBestDiff;
use PHPUnit\Framework\TestCase;

class PlayerAchievementRulesTest extends TestCase
{
    public function test_iron_and_a_later_bronze_both_count_as_unlocks(): void
    {
        $iron = BadgeUnlock::fromProgress('high_visit', '180', 0, 1);
        $same = BadgeUnlock::fromProgress('high_visit', '180', 1, 2);
        $bronze = BadgeUnlock::fromProgress('high_visit', '180', 1, 5);

        $this->assertSame('Żelazo', $iron['levelLabel'] ?? null);
        $this->assertNull($same);
        $this->assertSame('Brąz', $bronze['levelLabel'] ?? null);
    }

    public function test_a_jump_across_levels_reports_only_the_reached_one(): void
    {
        $unlocks = BadgeUnlock::collect([
            ['category' => 'high_visit', 'key' => '180', 'before' => 0, 'after' => 5],
            ['category' => 'qf', 'key' => '12', 'before' => 0, 'after' => 1],
        ]);

        $this->assertCount(2, $unlocks);
        $this->assertSame('Brąz', $unlocks[0]['levelLabel']);
        $this->assertSame('Żelazo', $unlocks[1]['levelLabel']);

        $sentence = (new PlayerBadgesActivity)->sentence([
            'actorName' => 'Andrzej',
            'badges' => $unlocks,
        ]);

        $this->assertSame('Andrzej właśnie zdobył odznaki: 180 — Brąz, QF 12 — Żelazo.', $sentence);
    }

    public function test_personal_bests_skip_the_first_average_and_keep_the_first_finish(): void
    {
        $this->assertSame([], PersonalBestDiff::improved(null, 40.0, null, null, null, null));

        $records = PersonalBestDiff::improved(40.0, 55.2, null, 141, null, 12);
        $this->assertSame([
            ['kind' => 'average', 'value' => 55.2],
            ['kind' => 'hf', 'value' => 141],
            ['kind' => 'qf', 'value' => 12],
        ], $records);

        $this->assertSame([], PersonalBestDiff::improved(55.2, 50.0, 141, 120, 12, 15));

        $sentence = (new PlayerPersonalBestActivity)->sentence([
            'actorName' => 'Andrzej',
            'records' => [['kind' => 'hf', 'value' => 170]],
        ]);
        $this->assertSame('Andrzej właśnie poprawił życiowy rekord najwyższego finishu: 170.', $sentence);
    }
}
