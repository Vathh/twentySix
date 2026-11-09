<?php

namespace App\Services\Game;

use App\Repositories\Game\HeadToHeadRepository;
use Carbon\Carbon;
use Throwable;

class HeadToHeadService
{
    private const RECENT_LIMIT = 5;

    public function __construct(
        private HeadToHeadRepository $headToHeadRepository,
    ) {}

    /**
     * Bilans i ostatnie spotkania pary. Wynik jest ustawiony tak, że player1 to lewa
     * strona oglądanego meczu. Bieżący mecz wlicza się do bilansu, gdy jest zakończony,
     * i nie pojawia się na liście ostatnich.
     *
     * @return array{
     *     meetings: int,
     *     meetingsWord: string,
     *     player1Wins: int,
     *     player2Wins: int,
     *     player1Legs: int,
     *     player2Legs: int,
     *     player1Average: float|null,
     *     player2Average: float|null,
     *     streak: array{side: string, count: int, label: string}|null,
     *     firstMeetingAt: string|null,
     *     recent: list<array<string, mixed>>,
     *     recentHasMore: bool
     * }|null
     */
    public function between(int $player1Id, int $player2Id, string $currentKind, int $currentId): ?array
    {
        return $this->firstPage($player1Id, $player2Id, $currentKind, $currentId);
    }

    /**
     * Bilans pary na profilu. Nie ma „bieżącego meczu”, więc każde spotkanie
     * jest w bilansie i na liście. Lewa strona to $leftId.
     *
     * @return array{
     *     meetings: int,
     *     meetingsWord: string,
     *     player1Wins: int,
     *     player2Wins: int,
     *     player1Legs: int,
     *     player2Legs: int,
     *     player1Average: float|null,
     *     player2Average: float|null,
     *     streak: array{side: string, count: int, label: string}|null,
     *     firstMeetingAt: string|null,
     *     recent: list<array<string, mixed>>,
     *     recentHasMore: bool
     * }|null
     */
    public function betweenPlayers(int $leftId, int $rightId): ?array
    {
        return $this->firstPage($leftId, $rightId, null, null);
    }

    /**
     * Kolejna porcja spotkań poza oglądanym meczem.
     *
     * @return array{recent: list<array<string, mixed>>, hasMore: bool}|null
     */
    public function recent(int $player1Id, int $player2Id, string $currentKind, int $currentId, int $offset): ?array
    {
        return $this->recentPage($player1Id, $player2Id, $currentKind, $currentId, $offset);
    }

    /**
     * Kolejna porcja spotkań pary z profilu.
     *
     * @return array{recent: list<array<string, mixed>>, hasMore: bool}|null
     */
    public function recentBetweenPlayers(int $leftId, int $rightId, int $offset): ?array
    {
        return $this->recentPage($leftId, $rightId, null, null, $offset);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function firstPage(int $player1Id, int $player2Id, ?string $currentKind, ?int $currentId): ?array
    {
        $built = $this->build($player1Id, $player2Id, $currentKind, $currentId);
        if ($built === null) {
            return null;
        }

        $page = $this->page($built['others'], 0);
        unset($built['others']);

        return [
            ...$built,
            'recent' => $page['recent'],
            'recentHasMore' => $page['hasMore'],
        ];
    }

    /**
     * @return array{recent: list<array<string, mixed>>, hasMore: bool}|null
     */
    private function recentPage(int $player1Id, int $player2Id, ?string $currentKind, ?int $currentId, int $offset): ?array
    {
        $built = $this->build($player1Id, $player2Id, $currentKind, $currentId);
        if ($built === null) {
            return null;
        }

        return $this->page($built['others'], $offset);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function build(int $player1Id, int $player2Id, ?string $currentKind, ?int $currentId): ?array
    {
        if ($player1Id < 1 || $player2Id < 1 || $player1Id === $player2Id) {
            return null;
        }
        if ($currentKind === 'quick' && $currentId !== null && $this->headToHeadRepository->quickResultCount($currentId) > 2) {
            return null;
        }

        $meetings = $this->headToHeadRepository->meetings($player1Id, $player2Id);
        $averages = $this->headToHeadRepository->averages($meetings);
        $legWins = $this->headToHeadRepository->legWins($meetings);

        $player1Wins = 0;
        $player2Wins = 0;
        $player1Legs = 0;
        $player2Legs = 0;
        $player1Averages = [];
        $player2Averages = [];
        $others = [];

        foreach ($meetings as $meeting) {
            $oriented = $this->orient($meeting, $player1Id, $player2Id, $averages);
            if ($oriented['winnerSide'] === 'player1') {
                $player1Wins++;
            } elseif ($oriented['winnerSide'] === 'player2') {
                $player2Wins++;
            }
            [$leftLegs, $rightLegs] = $this->legsOf($meeting, $player1Id, $legWins);
            $player1Legs += $leftLegs;
            $player2Legs += $rightLegs;
            $player1Averages[] = $oriented['player1Average'];
            $player2Averages[] = $oriented['player2Average'];

            $isCurrent = $currentKind !== null
                && $currentId !== null
                && $meeting['kind'] === $currentKind
                && $meeting['id'] === $currentId;
            if (! $isCurrent) {
                $others[] = $oriented;
            }
        }

        $count = count($meetings);
        $oldest = $meetings === [] ? null : $meetings[array_key_last($meetings)];

        return [
            'meetings' => $count,
            'meetingsWord' => $this->meetingsWord($count),
            'player1Wins' => $player1Wins,
            'player2Wins' => $player2Wins,
            'player1Legs' => $player1Legs,
            'player2Legs' => $player2Legs,
            'player1Average' => $this->mean($player1Averages),
            'player2Average' => $this->mean($player2Averages),
            'streak' => $this->streak($meetings, $player1Id, $player2Id),
            'firstMeetingAt' => $oldest !== null ? $this->formatDate($oldest['played_at']) : null,
            'others' => $others,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $others
     * @return array{recent: list<array<string, mixed>>, hasMore: bool}
     */
    private function page(array $others, int $offset): array
    {
        $offset = max(0, $offset);

        return [
            'recent' => array_values(array_slice($others, $offset, self::RECENT_LIMIT)),
            'hasMore' => count($others) > $offset + self::RECENT_LIMIT,
        ];
    }

    /**
     * Wygrane legi w meczu. Gdy nie ma zapisanych legów, a mecz idzie na legi (nie na sety),
     * bierzemy wynik meczu — tak wchodzi walkower wpisany samym wynikiem.
     *
     * @param  array<string, mixed>  $meeting
     * @param  array<string, int>  $legWins
     * @return array{0: int, 1: int}
     */
    private function legsOf(array $meeting, int $player1Id, array $legWins): array
    {
        $swapped = (int) $meeting['player1_id'] !== $player1Id;
        $homeId = (int) $meeting['player1_id'];
        $awayId = (int) $meeting['player2_id'];
        $key = $meeting['kind'].':'.$meeting['id'].':';
        $homeLegs = $legWins[$key.$homeId] ?? 0;
        $awayLegs = $legWins[$key.$awayId] ?? 0;

        if ($homeLegs + $awayLegs === 0 && (int) $meeting['sets_to_win_match'] <= 1) {
            $homeLegs = (int) $meeting['player1_score'];
            $awayLegs = (int) $meeting['player2_score'];
        }

        return $swapped
            ? [$awayLegs, $homeLegs]
            : [$homeLegs, $awayLegs];
    }

    /**
     * @param  array<string, mixed>  $meeting
     * @param  array<string, float>  $averages
     * @return array<string, mixed>
     */
    private function orient(array $meeting, int $player1Id, int $player2Id, array $averages): array
    {
        $swapped = (int) $meeting['player1_id'] !== $player1Id;
        $leftScore = $swapped ? $meeting['player2_score'] : $meeting['player1_score'];
        $rightScore = $swapped ? $meeting['player1_score'] : $meeting['player2_score'];
        $winnerId = $meeting['winner_id'];
        $winnerSide = null;
        if ($winnerId === $player1Id) {
            $winnerSide = 'player1';
        } elseif ($winnerId === $player2Id) {
            $winnerSide = 'player2';
        }

        $key = $meeting['kind'].':'.$meeting['id'].':';

        return [
            'kind' => $meeting['kind'],
            'id' => $meeting['id'],
            'dateFormatted' => $this->formatDate($meeting['played_at']),
            'player1Score' => $leftScore,
            'player2Score' => $rightScore,
            'winnerSide' => $winnerSide,
            'player1Average' => $averages[$key.$player1Id] ?? null,
            'player2Average' => $averages[$key.$player2Id] ?? null,
            'placeLabel' => $this->placeLabel($meeting),
            'url' => route('games.show', ['type' => $meeting['kind'], 'id' => $meeting['id']]),
        ];
    }

    /**
     * @param  array<string, mixed>  $meeting
     */
    private function placeLabel(array $meeting): string
    {
        $kind = match ($meeting['kind']) {
            'group', 'playoff' => 'Turniej',
            'league' => 'Liga',
            default => 'Szybka gra',
        };
        if ($meeting['organization_name'] === null) {
            return $kind;
        }

        return $kind.' · '.$meeting['organization_name'];
    }

    /**
     * @param  list<array<string, mixed>>  $meetings  od najnowszych
     * @return array{side: string, count: int, label: string}|null
     */
    private function streak(array $meetings, int $player1Id, int $player2Id): ?array
    {
        $winnerId = null;
        $count = 0;
        foreach ($meetings as $meeting) {
            $id = $meeting['winner_id'];
            if ($id !== $player1Id && $id !== $player2Id) {
                break;
            }
            if ($winnerId === null) {
                $winnerId = $id;
                $count = 1;

                continue;
            }
            if ($id !== $winnerId) {
                break;
            }
            $count++;
        }

        if ($winnerId === null || $count < 2) {
            return null;
        }

        return [
            'side' => $winnerId === $player1Id ? 'player1' : 'player2',
            'count' => $count,
            'label' => $this->streakLabel($count),
        ];
    }

    private function streakLabel(int $count): string
    {
        return $count.' zwycięstw z rzędu';
    }

    /**
     * @param  list<float|null>  $values
     */
    private function mean(array $values): ?float
    {
        $numbers = array_values(array_filter($values, fn ($value) => $value !== null));
        if ($numbers === []) {
            return null;
        }

        return round(array_sum($numbers) / count($numbers), 2);
    }

    private function meetingsWord(int $count): string
    {
        if ($count === 1) {
            return 'spotkanie';
        }
        $mod10 = $count % 10;
        $mod100 = $count % 100;
        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            return 'spotkania';
        }

        return 'spotkań';
    }

    private function formatDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        try {
            return Carbon::parse($value)->timezone((string) config('app.timezone'))->format('d.m.Y');
        } catch (Throwable) {
            return null;
        }
    }
}
