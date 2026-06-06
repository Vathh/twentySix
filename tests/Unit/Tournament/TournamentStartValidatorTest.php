<?php

namespace Tests\Unit\Tournament;

use App\Services\Tournament\TournamentStartValidator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TournamentStartValidatorTest extends TestCase
{
    private TournamentStartValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new TournamentStartValidator();
    }

    public function test_accepts_valid_four_player_two_group_configuration(): void
    {
        $this->validator->validate(
            playerCount: 4,
            groupsCount: 2,
            advancePerGroup: 2,
            tabletsCount: 2,
        );

        $this->assertTrue(true);
    }

    public function test_rejects_sixty_four_groups_because_bracket_exceeds_mvp_cap(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate(
            playerCount: 64,
            groupsCount: 64,
            advancePerGroup: 1,
            tabletsCount: 1,
        );
    }

    public function test_accepts_thirty_two_groups_with_advance_one_at_mvp_cap(): void
    {
        $this->validator->validate(
            playerCount: 32,
            groupsCount: 32,
            advancePerGroup: 1,
            tabletsCount: 3,
        );

        $this->assertTrue(true);
    }

    public function test_rejects_three_players(): void
    {
        try {
            $this->validator->validate(3, 2, 2, 1);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('selectedPlayers', $e->errors());
        }
    }

    public function test_rejects_non_power_of_two_groups(): void
    {
        try {
            $this->validator->validate(8, 3, 2, 1);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('groupsCount', $e->errors());
        }
    }

    public function test_rejects_invalid_advance_for_groups(): void
    {
        try {
            $this->validator->validate(16, 8, 3, 2);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('advancePerGroup', $e->errors());
        }
    }

    public function test_rejects_bracket_above_mvp_cap(): void
    {
        try {
            $this->validator->validate(64, 16, 4, 2);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('advancePerGroup', $e->errors());
        }
    }

    public function test_rejects_more_groups_than_players(): void
    {
        try {
            $this->validator->validate(4, 8, 1, 1);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('groupsCount', $e->errors());
        }
    }

    public function test_rejects_zero_tablets(): void
    {
        try {
            $this->validator->validate(4, 2, 2, 0);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('tabletsCount', $e->errors());
        }
    }

    #[DataProvider('validConfigurationProvider')]
    public function test_accepts_valid_configurations(
        int $players,
        int $groups,
        int $advance,
        int $tablets,
    ): void {
        $this->validator->validate($players, $groups, $advance, $tablets);

        $this->assertTrue(true);
    }

    public static function validConfigurationProvider(): array
    {
        return [
            '4 players 2x2' => [4, 2, 2, 1],
            '8 players 4x2' => [8, 4, 2, 2],
            '16 players 8x2' => [16, 8, 2, 4],
            '32 players 8x4' => [32, 8, 4, 5],
            '32 players 16x2' => [32, 16, 2, 1],
        ];
    }
}
