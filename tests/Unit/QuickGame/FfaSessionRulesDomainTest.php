<?php

namespace Tests\Unit\QuickGame;

use App\Domain\QuickGame\FfaSessionRulesDomain;
use App\Models\QuickGame\QuickGameFfaPresence;
use DomainException;
use PHPUnit\Framework\TestCase;

class FfaSessionRulesDomainTest extends TestCase
{
    public function test_should_forfeit_on_leave_only_for_two_player_each_own_in_progress(): void
    {
        $this->assertTrue(FfaSessionRulesDomain::shouldForfeitOnLeave('each_own', 2, true));
        $this->assertFalse(FfaSessionRulesDomain::shouldForfeitOnLeave('each_own', 2, false));
        $this->assertFalse(FfaSessionRulesDomain::shouldForfeitOnLeave('each_own', 3, true));
        $this->assertFalse(FfaSessionRulesDomain::shouldForfeitOnLeave('one_device', 2, true));
    }

    public function test_resolve_forfeit_winner_id_returns_the_other_player(): void
    {
        $winnerId = FfaSessionRulesDomain::resolveForfeitWinnerId([10, 20], 10);

        $this->assertSame(20, $winnerId);
    }

    public function test_resolve_forfeit_winner_id_throws_when_no_other_player(): void
    {
        $this->expectException(DomainException::class);

        FfaSessionRulesDomain::resolveForfeitWinnerId([10], 10);
    }

    public function test_is_decided_by_forfeit_when_fewer_than_two_active_players(): void
    {
        $this->assertTrue(FfaSessionRulesDomain::isDecidedByForfeit([10]));
        $this->assertTrue(FfaSessionRulesDomain::isDecidedByForfeit([]));
        $this->assertFalse(FfaSessionRulesDomain::isDecidedByForfeit([10, 20]));
    }

    public function test_sole_remaining_player_id(): void
    {
        $this->assertSame(10, FfaSessionRulesDomain::soleRemainingPlayerId([10]));
        $this->assertNull(FfaSessionRulesDomain::soleRemainingPlayerId([]));
    }

    public function test_heartbeat_tracked_player_ids_excludes_guests(): void
    {
        $tracked = FfaSessionRulesDomain::heartbeatTrackedPlayerIds([10, 20, 30], [20]);

        $this->assertSame([10, 30], $tracked);
    }

    public function test_effective_presence_status_forces_connected_for_guest_without_account(): void
    {
        $status = FfaSessionRulesDomain::effectivePresenceStatus(true, QuickGameFfaPresence::STATUS_DISCONNECTED);

        $this->assertSame(QuickGameFfaPresence::STATUS_CONNECTED, $status);
    }

    public function test_effective_presence_status_keeps_status_for_real_account(): void
    {
        $status = FfaSessionRulesDomain::effectivePresenceStatus(false, QuickGameFfaPresence::STATUS_DISCONNECTED);

        $this->assertSame(QuickGameFfaPresence::STATUS_DISCONNECTED, $status);
    }

    public function test_effective_presence_status_keeps_left_status_for_guest(): void
    {
        $status = FfaSessionRulesDomain::effectivePresenceStatus(true, QuickGameFfaPresence::STATUS_LEFT);

        $this->assertSame(QuickGameFfaPresence::STATUS_LEFT, $status);
    }
}
