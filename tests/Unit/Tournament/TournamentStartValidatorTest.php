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

    public function test_accepts_valid_eight_player_two_group_configuration(): void
    {
        $this->validator->validate(
            playerCount: 8,
            groupsCount: 2,
            playoffBracketSize: 4,
        );

        $this->assertTrue(true);
    }

    public function test_accepts_thirty_seven_players_seven_groups_sixteen_bracket(): void
    {
        $this->validator->validate(
            playerCount: 37,
            groupsCount: 7,
            playoffBracketSize: 16,
        );

        $this->assertTrue(true);
    }

    public function test_rejects_bracket_smaller_than_groups_count(): void
    {
        try {
            $this->validator->validate(16, 8, 4);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('playoffBracketSize', $e->errors());
        }
    }

    public function test_rejects_three_players(): void
    {
        try {
            $this->validator->validate(3, 2, 4);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('selectedPlayers', $e->errors());
        }
    }

    public function test_rejects_seven_groups_for_eight_players(): void
    {
        try {
            $this->validator->validate(8, 7, 8);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('groupsCount', $e->errors());
        }
    }

    public function test_rejects_bracket_above_mvp_cap(): void
    {
        try {
            $this->validator->validate(64, 16, 256);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('playoffBracketSize', $e->errors());
        }
    }

    public function test_rejects_more_groups_than_players(): void
    {
        try {
            $this->validator->validate(4, 8, 4);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('groupsCount', $e->errors());
        }
    }

    #[DataProvider('validConfigurationProvider')]
    public function test_accepts_valid_configurations(
        int $players,
        int $groups,
        int $bracketSize,
    ): void {
        $this->validator->validate($players, $groups, $bracketSize);

        $this->assertTrue(true);
    }

    public static function validConfigurationProvider(): array
    {
        return [
            '8 players 2 groups bracket 4' => [8, 2, 4],
            '12 players 4 groups bracket 8' => [12, 4, 8],
            '16 players 4 groups bracket 8' => [16, 4, 8],
            '32 players 8 groups bracket 32' => [32, 8, 32],
            '37 players 7 groups bracket 16' => [37, 7, 16],
        ];
    }
}
