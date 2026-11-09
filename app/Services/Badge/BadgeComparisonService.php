<?php

namespace App\Services\Badge;

use App\Models\Player\Player;
use App\Models\Users\User;

class BadgeComparisonService
{
    public function __construct(
        private HighVisitBadgeAssembler $highVisitBadgeAssembler,
        private QfBadgeAssembler $qfBadgeAssembler,
        private FinishBadgeAssembler $finishBadgeAssembler,
        private CareerBadgeAssembler $careerBadgeAssembler,
        private CheckoutWheelAssembler $checkoutWheelAssembler,
    ) {}

    /**
     * Lewa strona to widz, prawa to oglądany gracz.
     * Wiersz znika, gdy obie strony mają levelName locked.
     * Koła checkoutów są pełnymi katalogami, jak na profilu.
     *
     * @return array{
     *     viewer: array{id: int, name: string},
     *     player: array{id: int, name: string},
     *     sections: list<array{key: string, title: string, rows: list<array{key: string, label: string, mine: array<string, mixed>, theirs: array<string, mixed>}>}>,
     *     checkout: array{
     *         mine: array{hits: array<int, int>, items: list<array<string, mixed>>},
     *         theirs: array{hits: array<int, int>, items: list<array<string, mixed>>}
     *     }
     * }
     */
    public function compare(Player $player, ?User $viewer): array
    {
        if (! $player->user_id) {
            abort(404);
        }

        $viewerPlayer = $viewer?->player;
        if ($viewerPlayer === null || (int) $viewerPlayer->id === (int) $player->id) {
            abort(404);
        }

        $mineId = (int) $viewerPlayer->id;
        $theirsId = (int) $player->id;

        $sections = array_values(array_filter([
            $this->section(
                'high_visit',
                'Mocny punkciarz',
                $this->highVisitBadgeAssembler->itemsForPlayer($mineId),
                $this->highVisitBadgeAssembler->itemsForPlayer($theirsId),
            ),
            $this->section(
                'qf',
                'Szybka akcja',
                $this->qfBadgeAssembler->itemsForPlayer($mineId, includeUnearned: true),
                $this->qfBadgeAssembler->itemsForPlayer($theirsId, includeUnearned: true),
            ),
            $this->section(
                'finish',
                'Inne',
                $this->finishBadgeAssembler->itemsForPlayer($mineId),
                $this->finishBadgeAssembler->itemsForPlayer($theirsId),
            ),
            $this->section(
                'career',
                'Kariera',
                $this->careerBadgeAssembler->itemsForPlayer($mineId),
                $this->careerBadgeAssembler->itemsForPlayer($theirsId),
            ),
        ]));

        return [
            'viewer' => [
                'id' => $mineId,
                'name' => $viewerPlayer->name,
            ],
            'player' => [
                'id' => $theirsId,
                'name' => $player->name,
            ],
            'sections' => $sections,
            'checkout' => [
                'mine' => $this->checkoutSide($mineId),
                'theirs' => $this->checkoutSide($theirsId),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $mine
     * @param  list<array<string, mixed>>  $theirs
     * @return array{key: string, title: string, rows: list<array{key: string, label: string, mine: array<string, mixed>, theirs: array<string, mixed>}>}|null
     */
    private function section(string $key, string $title, array $mine, array $theirs): ?array
    {
        $rows = $this->rows($mine, $theirs);
        if ($rows === []) {
            return null;
        }

        return [
            'key' => $key,
            'title' => $title,
            'rows' => $rows,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $mine
     * @param  list<array<string, mixed>>  $theirs
     * @return list<array{key: string, label: string, mine: array<string, mixed>, theirs: array<string, mixed>}>
     */
    private function rows(array $mine, array $theirs): array
    {
        $theirsByKey = [];
        foreach ($theirs as $item) {
            $theirsByKey[(string) $item['key']] = $item;
        }

        $rows = [];
        foreach ($mine as $item) {
            $key = (string) $item['key'];
            $other = $theirsByKey[$key] ?? null;
            if (! is_array($other)) {
                continue;
            }
            if ($this->locked($item) && $this->locked($other)) {
                continue;
            }
            $rows[] = [
                'key' => $key,
                'label' => (string) ($item['label'] ?? $key),
                'mine' => $item,
                'theirs' => $other,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function locked(array $item): bool
    {
        return ($item['levelName'] ?? 'locked') === 'locked';
    }

    /**
     * @return array{hits: array<int, int>, items: list<array<string, mixed>>}
     */
    private function checkoutSide(int $playerId): array
    {
        $items = $this->checkoutWheelAssembler->itemsForPlayer($playerId);
        $hits = [];
        foreach ($items as $item) {
            $times = (int) $item['timesEarned'];
            if ($times > 0) {
                $hits[(int) $item['key']] = $times;
            }
        }

        return [
            'hits' => $hits,
            'items' => $items,
        ];
    }
}
