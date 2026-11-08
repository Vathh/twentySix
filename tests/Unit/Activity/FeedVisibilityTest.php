<?php

namespace Tests\Unit\Activity;

use App\Domain\Activity\ActivityChannel;
use App\Domain\Activity\HomeFeedPolicy;
use App\Domain\Activity\ProfileFeedAccess;
use App\Domain\Activity\ProfileFeedVisibility;
use PHPUnit\Framework\TestCase;

class FeedVisibilityTest extends TestCase
{
    private HomeFeedPolicy $home;

    private ProfileFeedAccess $profile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->home = new HomeFeedPolicy;
        $this->profile = new ProfileFeedAccess;
    }

    public function test_unfollowed_actor_still_arrives_through_the_organization(): void
    {
        $this->assertTrue($this->home->visible(
            viewerId: 1,
            actorUserId: 2,
            organizationId: 9,
            channels: [ActivityChannel::Friends, ActivityChannel::Organization],
            friendsChannelEnabled: true,
            organizationChannelEnabled: true,
            followedUserIds: [],
            followedOrganizationIds: [9 => true],
        ));
    }

    public function test_unfollowed_organization_still_arrives_through_the_player(): void
    {
        $this->assertTrue($this->home->visible(
            viewerId: 1,
            actorUserId: 2,
            organizationId: 9,
            channels: [ActivityChannel::Friends, ActivityChannel::Organization],
            friendsChannelEnabled: true,
            organizationChannelEnabled: true,
            followedUserIds: [2 => true],
            followedOrganizationIds: [],
        ));
    }

    public function test_both_channels_closed_hides_the_story(): void
    {
        $this->assertFalse($this->home->visible(
            viewerId: 1,
            actorUserId: 2,
            organizationId: 9,
            channels: [ActivityChannel::Friends, ActivityChannel::Organization],
            friendsChannelEnabled: true,
            organizationChannelEnabled: true,
            followedUserIds: [],
            followedOrganizationIds: [],
        ));
    }

    public function test_player_story_disappears_when_the_actor_is_not_followed(): void
    {
        $this->assertFalse($this->home->visible(
            viewerId: 1,
            actorUserId: 2,
            organizationId: null,
            channels: [ActivityChannel::Friends],
            friendsChannelEnabled: true,
            organizationChannelEnabled: true,
            followedUserIds: [],
            followedOrganizationIds: [],
        ));
    }

    public function test_guest_sees_public_profile_only(): void
    {
        $this->assertTrue($this->profile->canView(null, 2, ProfileFeedVisibility::Public, false, false));
        $this->assertFalse($this->profile->canView(null, 2, ProfileFeedVisibility::Friends, false, false));
        $this->assertFalse($this->profile->canView(null, 2, ProfileFeedVisibility::FriendsAndCircle, false, false));
    }

    public function test_circle_opens_the_profile_without_friendship(): void
    {
        $this->assertTrue($this->profile->canView(1, 2, ProfileFeedVisibility::FriendsAndCircle, false, true));
        $this->assertFalse($this->profile->canView(1, 2, ProfileFeedVisibility::Friends, false, true));
    }

    public function test_owner_always_sees_their_profile(): void
    {
        $this->assertTrue($this->profile->canView(2, 2, ProfileFeedVisibility::Friends, false, false));
    }
}
