<?php

namespace App\Services\Activity;

use App\Domain\Activity\ActivityRegistry;
use App\Domain\Activity\ActivityType;
use App\Models\Activity\Activity;
use App\Models\Organization\Organization;
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
        $sentence = $this->registry->sentence($activity->type, $payload);
        $playerId = $activity->actor_user_id === null || $activity->actor_player_id === null
            ? null
            : (int) $activity->actor_player_id;

        return [
            'id' => (int) $activity->id,
            'type' => $activity->type,
            'kind' => $this->registry->kind((string) $activity->type),
            'sentence' => $sentence,
            'sentenceParts' => $this->sentenceParts((string) $activity->type, $sentence, $name, $playerId, $payload),
            'occurredAt' => $activity->occurred_at?->toIso8601String(),
            'header' => $this->header($activity, $payload, $player),
            'actor' => [
                'playerId' => $activity->actor_player_id === null ? null : (int) $activity->actor_player_id,
                'userId' => $activity->actor_user_id === null ? null : (int) $activity->actor_user_id,
                'name' => $name,
                'initials' => $player?->initials() ?? Player::initialsFromName($name),
                'avatarUrl' => $player?->avatarUrl(),
            ],
            'links' => [
                'playerId' => $playerId,
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

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{text: string, url: ?string, playerId: ?int, tournamentId: ?int, organizationId: ?int}>
     */
    private function sentenceParts(string $type, string $sentence, string $actorName, ?int $playerId, array $payload): array
    {
        if ($type === ActivityType::TournamentChampion->value || $type === ActivityType::TournamentAnnounced->value) {
            return $this->tournamentSentenceParts($type, $playerId, $payload);
        }

        if ($playerId === null || $actorName === '' || ! str_starts_with($sentence, $actorName)) {
            return [$this->part($sentence)];
        }

        return [
            $this->part($actorName, route('players.show', $playerId), $playerId),
            $this->part(substr($sentence, strlen($actorName))),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{text: string, url: ?string, playerId: ?int, tournamentId: ?int, organizationId: ?int}>
     */
    private function tournamentSentenceParts(string $type, ?int $playerId, array $payload): array
    {
        $tournamentName = (string) ($payload['tournamentName'] ?? 'turniej');
        $tournamentId = isset($payload['tournamentId']) ? (int) $payload['tournamentId'] : null;
        $organizationName = trim((string) ($payload['organizationName'] ?? ''));
        $organizationId = isset($payload['organizationId']) ? (int) $payload['organizationId'] : null;
        $tournamentUrl = $tournamentId ? route('tournaments.show', $tournamentId) : null;
        $organizationUrl = $organizationId ? route('organizations.show', $organizationId) : null;

        $parts = [];
        if ($type === ActivityType::TournamentAnnounced->value) {
            $parts[] = $this->part('Nowy turniej ');
        } else {
            $actor = (string) ($payload['actorName'] ?? 'Gracz');
            $parts[] = $playerId
                ? $this->part($actor, route('players.show', $playerId), $playerId)
                : $this->part($actor);
            $parts[] = $this->part(' wygrał turniej ');
        }

        $parts[] = $tournamentId
            ? $this->part($tournamentName, $tournamentUrl, null, $tournamentId)
            : $this->part($tournamentName);

        if ($organizationName !== '') {
            $parts[] = $this->part(' w ');
            $parts[] = $organizationId
                ? $this->part($organizationName, $organizationUrl, null, null, $organizationId)
                : $this->part($organizationName);
        }

        $parts[] = $this->part('.');

        return $parts;
    }

    /**
     * @return array{text: string, url: ?string, playerId: ?int, tournamentId: ?int, organizationId: ?int}
     */
    private function part(
        string $text,
        ?string $url = null,
        ?int $playerId = null,
        ?int $tournamentId = null,
        ?int $organizationId = null,
    ): array {
        return [
            'text' => $text,
            'url' => $url,
            'playerId' => $playerId,
            'tournamentId' => $tournamentId,
            'organizationId' => $organizationId,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{name: string, initials: string, avatarUrl: ?string, url: ?string, playerId: ?int, organizationId: ?int, userId: ?int}
     */
    private function header(Activity $activity, array $payload, ?Player $player): array
    {
        $definition = $this->registry->find((string) $activity->type);
        if ($definition?->authoredByOrganization()) {
            $organization = $activity->relationLoaded('organization') ? $activity->organization : null;
            $name = trim((string) ($payload['organizationName'] ?? ''));
            if ($name === '' && $organization instanceof Organization) {
                $name = $organization->name;
            }
            if ($name === '') {
                $name = 'Organizacja';
            }
            $organizationId = $activity->organization_id === null ? null : (int) $activity->organization_id;

            return [
                'name' => $name,
                'initials' => Player::initialsFromName($name),
                'avatarUrl' => $organization instanceof Organization ? $organization->logoUrl() : null,
                'url' => $organizationId === null ? null : route('organizations.show', $organizationId),
                'playerId' => null,
                'organizationId' => $organizationId,
                'userId' => null,
            ];
        }

        $name = (string) ($payload['actorName'] ?? '');
        $registered = $activity->actor_user_id !== null && $activity->actor_player_id !== null;
        $playerId = $registered ? (int) $activity->actor_player_id : null;

        return [
            'name' => $name,
            'initials' => $player?->initials() ?? Player::initialsFromName($name),
            'avatarUrl' => $player?->avatarUrl(),
            'url' => $playerId === null ? null : route('players.show', $playerId),
            'playerId' => $playerId,
            'organizationId' => null,
            'userId' => $activity->actor_user_id === null ? null : (int) $activity->actor_user_id,
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
