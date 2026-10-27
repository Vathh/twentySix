<?php

namespace App\Services\Activity;

use App\Domain\Activity\ActivityRegistry;
use App\Models\Activity\Activity;
use App\Models\Player\Player;
use Carbon\CarbonInterface;

class ActivityCardPresenter
{
    public function __construct(private ActivityRegistry $registry) {}

    /**
     * @param  array{commentCount?: int, counts?: array<string, int>, mine?: ?string, canEngage?: bool}  $engagement
     * @return array<string, mixed>
     */
    public function present(Activity $activity, array $engagement = []): array
    {
        $payload = is_array($activity->payload) ? $activity->payload : [];
        $name = (string) ($payload['actorName'] ?? '');
        $player = $activity->relationLoaded('actorPlayer') ? $activity->actorPlayer : null;
        $counts = $engagement['counts'] ?? [];

        return [
            'id' => (int) $activity->id,
            'type' => $activity->type,
            'kind' => $this->registry->kind((string) $activity->type),
            'sentence' => $this->registry->sentence($activity->type, $payload),
            'occurredAt' => $activity->occurred_at?->toIso8601String(),
            'actor' => [
                'playerId' => $activity->actor_player_id === null ? null : (int) $activity->actor_player_id,
                'userId' => $activity->actor_user_id === null ? null : (int) $activity->actor_user_id,
                'name' => $name,
                'initials' => $player?->initials() ?? Player::initialsFromName($name),
                'avatarUrl' => $player?->avatarUrl(),
            ],
            'links' => [
                'playerId' => $activity->actor_player_id === null ? null : (int) $activity->actor_player_id,
                'tournamentId' => isset($payload['tournamentId']) ? (int) $payload['tournamentId'] : null,
                'organizationId' => $activity->organization_id === null ? null : (int) $activity->organization_id,
            ],
            'canEngage' => (bool) ($engagement['canEngage'] ?? false),
            'commentCount' => (int) ($engagement['commentCount'] ?? 0),
            'reactions' => [
                'counts' => $counts === [] ? (object) [] : $counts,
                'mine' => $engagement['mine'] ?? null,
            ],
        ];
    }

    public function cursor(Activity $activity): string
    {
        $at = $activity->occurred_at instanceof CarbonInterface
            ? $activity->occurred_at->toIso8601String()
            : '';

        return rtrim(strtr(base64_encode($at.'|'.$activity->id), '+/', '-_'), '=');
    }

    /**
     * @return array{at: \Carbon\Carbon, id: int}|null
     */
    public function decodeCursor(?string $cursor): ?array
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        $padded = strtr($cursor, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
        $raw = base64_decode($padded, true);
        if ($raw === false || ! str_contains($raw, '|')) {
            return null;
        }

        [$at, $id] = explode('|', $raw, 2);
        if (! ctype_digit($id)) {
            return null;
        }

        try {
            return ['at' => \Carbon\Carbon::parse($at), 'id' => (int) $id];
        } catch (\Throwable) {
            return null;
        }
    }
}
