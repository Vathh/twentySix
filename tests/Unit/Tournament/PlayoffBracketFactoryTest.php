<?php

namespace Tests\Unit\Tournament;

use App\Enums\GameStage;
use App\Factories\PlayoffBracketFactory;
use App\Support\Tournament\PlayoffFirstRoundPairing;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlayoffBracketFactoryTest extends TestCase
{
    private PlayoffBracketFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new PlayoffBracketFactory();
    }

    #[DataProvider('bracketSizeProvider')]
    public function test_creates_expected_number_of_games(int $bracketSize, int $expectedGames, GameStage $firstRound): void
    {
        $pairs = $this->buildDistinctGroupPairs($bracketSize);

        $games = $this->factory->create(1, $bracketSize, $pairs);

        $this->assertCount($expectedGames, $games);
        $this->assertSame(
            $bracketSize / 2,
            $games->where('round', $firstRound)->whereNotNull('player1Id')->count(),
        );
        $this->assertSame(
            range(1, $bracketSize),
            $games
                ->filter(fn ($game) => $game->player1Id !== null)
                ->flatMap(fn ($game) => [$game->player1Id, $game->player2Id])
                ->sort()
                ->values()
                ->all(),
        );
    }

    public static function bracketSizeProvider(): array
    {
        return [
            '2 players' => [2, 1, GameStage::FINAL],
            '4 players' => [4, 4, GameStage::SEMI],
            '8 players' => [8, 8, GameStage::QUARTER],
            '16 players' => [16, 16, GameStage::EIGHT],
            '32 players' => [32, 32, GameStage::SIXTEEN],
        ];
    }

    public function test_rejects_wrong_pair_count(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->factory->create(1, 4, [[1, 2]]);
    }

    public function test_rejects_invalid_bracket_size(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->factory->create(1, 3, [[1, 2]]);
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private function buildDistinctGroupPairs(int $bracketSize): array
    {
        $advancing = [];

        for ($playerId = 1; $playerId <= $bracketSize; $playerId++) {
            $advancing[] = [
                'player_id' => $playerId,
                'group_number' => $playerId,
            ];
        }

        return PlayoffFirstRoundPairing::pair($advancing);
    }
}
