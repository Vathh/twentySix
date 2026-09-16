<?php

namespace App\Repositories\Badge;

use App\Models\Badge\BadgeGameCommit;
use App\Models\Badge\PlayerBadge;
use App\Models\Badge\PlayerBadgeEvent;
use Illuminate\Support\Collection;

class PlayerBadgeRepository
{
    public function hasCommit(string $sourceKind, int $sourceId): bool
    {
        return BadgeGameCommit::query()
            ->where('source_kind', $sourceKind)
            ->where('source_id', $sourceId)
            ->exists();
    }

    public function insertCommit(string $sourceKind, int $sourceId): void
    {
        BadgeGameCommit::query()->create([
            'source_kind' => $sourceKind,
            'source_id' => $sourceId,
        ]);
    }

    public function deleteCommit(string $sourceKind, int $sourceId): void
    {
        BadgeGameCommit::query()
            ->where('source_kind', $sourceKind)
            ->where('source_id', $sourceId)
            ->delete();
    }

    /**
     * @param  list<array{playerId: int, category: string, badgeKey: string, amount: int}>  $events
     */
    public function insertEvents(string $sourceKind, int $sourceId, array $events): void
    {
        $now = now();
        $rows = [];
        foreach ($events as $event) {
            $rows[] = [
                'player_id' => $event['playerId'],
                'category' => $event['category'],
                'badge_key' => $event['badgeKey'],
                'amount' => $event['amount'],
                'source_kind' => $sourceKind,
                'source_id' => $sourceId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            PlayerBadgeEvent::query()->insert($rows);
        }
    }

    /**
     * @return list<array{player_id: int, category: string, badge_key: string}>
     */
    public function deleteEventsForSource(string $sourceKind, int $sourceId): array
    {
        $rows = PlayerBadgeEvent::query()
            ->where('source_kind', $sourceKind)
            ->where('source_id', $sourceId)
            ->get(['player_id', 'category', 'badge_key']);

        PlayerBadgeEvent::query()
            ->where('source_kind', $sourceKind)
            ->where('source_id', $sourceId)
            ->delete();

        return $rows->map(fn (PlayerBadgeEvent $row) => [
            'player_id' => (int) $row->player_id,
            'category' => (string) $row->category,
            'badge_key' => (string) $row->badge_key,
        ])->all();
    }

    /**
     * @return array{amount: int, first: string|null, last: string|null}
     */
    public function remainingTotals(int $playerId, string $category, string $badgeKey): array
    {
        $row = PlayerBadgeEvent::query()
            ->where('player_id', $playerId)
            ->where('category', $category)
            ->where('badge_key', $badgeKey)
            ->selectRaw('COALESCE(SUM(amount), 0) as amount')
            ->selectRaw('MIN(created_at) as first_at')
            ->selectRaw('MAX(created_at) as last_at')
            ->first();

        return [
            'amount' => (int) ($row?->amount ?? 0),
            'first' => $row?->first_at,
            'last' => $row?->last_at,
        ];
    }

    public function upsertFromAward(int $playerId, string $category, string $badgeKey, int $amount): void
    {
        $now = now();
        $existing = PlayerBadge::query()
            ->where('player_id', $playerId)
            ->where('category', $category)
            ->where('badge_key', $badgeKey)
            ->first();

        if ($existing === null) {
            PlayerBadge::query()->create([
                'player_id' => $playerId,
                'category' => $category,
                'badge_key' => $badgeKey,
                'times_earned' => $amount,
                'first_unlocked_at' => $now,
                'last_earned_at' => $now,
                'source_quality' => 'manual',
            ]);

            return;
        }

        $existing->times_earned = (int) $existing->times_earned + $amount;
        $existing->last_earned_at = $now;
        $existing->save();
    }

    public function replaceTotals(int $playerId, string $category, string $badgeKey, int $amount, ?string $firstAt, ?string $lastAt): void
    {
        if ($amount < 1) {
            PlayerBadge::query()
                ->where('player_id', $playerId)
                ->where('category', $category)
                ->where('badge_key', $badgeKey)
                ->delete();

            return;
        }

        PlayerBadge::query()->updateOrCreate(
            [
                'player_id' => $playerId,
                'category' => $category,
                'badge_key' => $badgeKey,
            ],
            [
                'times_earned' => $amount,
                'first_unlocked_at' => $firstAt,
                'last_earned_at' => $lastAt,
                'source_quality' => 'manual',
            ],
        );
    }

    /**
     * Najnowsze zdarzenie per badge_key (checkout).
     *
     * @return array<string, array{source_kind: string, source_id: int, created_at: string|null}>
     */
    public function latestEventsByBadgeKey(int $playerId, string $category): array
    {
        $rows = PlayerBadgeEvent::query()
            ->where('player_id', $playerId)
            ->where('category', $category)
            ->orderByDesc('id')
            ->get(['badge_key', 'source_kind', 'source_id', 'created_at']);

        $latest = [];
        foreach ($rows as $row) {
            $key = (string) $row->badge_key;
            if (isset($latest[$key])) {
                continue;
            }
            $latest[$key] = [
                'source_kind' => (string) $row->source_kind,
                'source_id' => (int) $row->source_id,
                'created_at' => $row->created_at?->toIso8601String(),
            ];
        }

        return $latest;
    }

    /**
     * @return Collection<int, PlayerBadge>
     */
    public function forPlayerCategory(int $playerId, string $category): Collection
    {
        return PlayerBadge::query()
            ->where('player_id', $playerId)
            ->where('category', $category)
            ->get();
    }
}
