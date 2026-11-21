<?php

namespace App\Services\Training;

use App\Domain\Training\TrainingDefinitionStatus;
use App\Domain\Training\TrainingException;
use App\Domain\Training\TrainingKind;
use App\Models\Training\TrainingChallengePublication;
use App\Models\Training\TrainingDefinition;
use App\Models\Training\TrainingSession;
use App\Models\Users\User;
use App\Repositories\Training\TrainingChallengePublicationRepository;
use App\Repositories\Training\TrainingDefinitionRepository;
use App\Repositories\Training\TrainingSessionRepository;
use App\Support\Http\DomainExceptionHttp;
use Illuminate\Support\Facades\DB;

class TrainingChallengeRankingService
{
    public function __construct(
        private TrainingDefinitionRepository $definitions,
        private TrainingSessionRepository $sessions,
        private TrainingChallengePublicationRepository $publications,
    ) {}

    /**
     * @return array{published: bool, place: int|null, score: int, message: string}
     */
    public function publish(User $user, int $sessionId): array
    {
        return DB::transaction(function () use ($user, $sessionId): array {
            $session = $this->sessions->findOwned($user->id, $sessionId);
            if ($session === null) {
                throw new TrainingException(
                    'Nie ma takiej sesji.',
                    'training_session_not_found',
                    DomainExceptionHttp::NOT_FOUND,
                );
            }
            if ($session->kind !== TrainingKind::Challenge || $session->definition_id === null) {
                throw new TrainingException(
                    'Publikacja dotyczy ukończonej próby wyzwania.',
                    'training_challenge_publish_invalid',
                );
            }
            if ($session->score === null) {
                throw new TrainingException(
                    'Próba bez wyniku nie wchodzi do rankingu.',
                    'training_challenge_publish_invalid',
                );
            }

            $definition = $this->definitions->lock((int) $session->definition_id);
            $this->assertActiveChallenge($definition);

            $existing = $this->publications->findForUser($user->id, (int) $definition->id);
            if ($existing instanceof TrainingChallengePublication && $session->score <= $existing->score) {
                throw new TrainingException(
                    'Opublikować można tylko lepszy wynik od już opublikowanego.',
                    'training_challenge_publish_not_better',
                );
            }

            $this->publications->upsert(
                $user->id,
                (int) $definition->id,
                (int) $session->id,
                (int) $session->score,
            );

            $place = $this->placeFor($user->id, (int) $definition->id);

            return [
                'published' => true,
                'place' => $place,
                'score' => (int) $session->score,
                'message' => 'Opublikowano wynik. Miejsce '.$place.', wynik '.$session->score.'.',
            ];
        });
    }

    /**
     * @return array{withdrawn: bool}
     */
    public function withdraw(User $user, int $definitionId): array
    {
        return DB::transaction(function () use ($user, $definitionId): array {
            $definition = $this->definitions->lock($definitionId);
            $this->assertChallenge($definition);
            $removed = $this->publications->deleteForUser($user->id, $definitionId);

            return ['withdrawn' => $removed];
        });
    }

    /**
     * @return array{
     *     items: list<array<string, mixed>>,
     *     nextOffset: int|null,
     *     total: int,
     *     mine: array<string, mixed>|null,
     *     emptyMessage: string|null
     * }
     */
    public function ranking(User $user, int $definitionId, int $offset = 0, int $limit = 10): array
    {
        $definition = $this->definitions->find($definitionId);
        $this->assertChallenge($definition);
        if ($definition->status !== TrainingDefinitionStatus::Active) {
            throw new TrainingException(
                'Ranking jest dostępny dla aktywnego wyzwania.',
                'training_definition_not_active',
                DomainExceptionHttp::CONFLICT,
            );
        }

        $offset = max(0, $offset);
        $limit = min(50, max(1, $limit));
        $ordered = $this->publications->allForRanking($definitionId);
        $total = $ordered->count();
        $withPlaces = $this->withContestPlaces($ordered);

        $slice = array_slice($withPlaces, $offset, $limit);
        $nextOffset = ($offset + $limit) < $total ? $offset + $limit : null;

        $mine = null;
        foreach ($withPlaces as $row) {
            if ((int) $row['userId'] === (int) $user->id) {
                $mine = $row;
                break;
            }
        }

        $mineOutside = null;
        if ($mine !== null) {
            $inSlice = false;
            foreach ($slice as $row) {
                if ((int) $row['userId'] === (int) $user->id) {
                    $inSlice = true;
                    break;
                }
            }
            if (! $inSlice) {
                $mineOutside = $mine;
            }
        }

        return [
            'items' => $slice,
            'nextOffset' => $nextOffset,
            'total' => $total,
            'mine' => $mineOutside,
            'publishedScore' => $mine['score'] ?? null,
            'emptyMessage' => $total === 0
                ? 'Brak opublikowanych wyników. Podejmij wyzwanie jako pierwszy'
                : null,
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, nextCursor: array{beforeCompletedAt: string, beforeId: int}|null}
     */
    public function attempts(
        User $user,
        int $definitionId,
        string $sort = 'newest',
        ?int $days = null,
        ?string $beforeCompletedAt = null,
        ?int $beforeId = null,
    ): array {
        $definition = $this->definitions->find($definitionId);
        $this->assertChallenge($definition);

        $page = $this->sessions->challengeAttemptsPage(
            $user->id,
            $definitionId,
            $sort,
            $days,
            $beforeCompletedAt,
            $beforeId,
            5,
        );

        $minePub = $this->publications->findForUser($user->id, $definitionId);
        $publishedSessionId = $minePub?->session_id;

        $items = [];
        foreach ($page['items'] as $session) {
            /** @var TrainingSession $session */
            $items[] = [
                'id' => (int) $session->id,
                'clientSessionId' => $session->client_session_id,
                'score' => $session->score,
                'completedAt' => $session->completed_at?->toIso8601String(),
                'published' => $publishedSessionId !== null && (int) $publishedSessionId === (int) $session->id,
                'stats' => $session->stats,
            ];
        }

        return [
            'items' => $items,
            'nextCursor' => $page['nextCursor'],
            'publishedScore' => $minePub?->score,
        ];
    }

    private function assertActiveChallenge(TrainingDefinition $definition): void
    {
        $this->assertChallenge($definition);
        if ($definition->status !== TrainingDefinitionStatus::Active) {
            throw new TrainingException(
                'Wyzwanie nie jest aktywne.',
                'training_definition_not_active',
                DomainExceptionHttp::CONFLICT,
            );
        }
    }

    private function assertChallenge(TrainingDefinition $definition): void
    {
        if ($definition->kind !== TrainingKind::Challenge) {
            throw new TrainingException(
                'To nie jest wyzwanie.',
                'training_definition_invalid',
            );
        }
    }

    private function placeFor(int $userId, int $definitionId): int
    {
        $ordered = $this->publications->allForRanking($definitionId);
        foreach ($this->withContestPlaces($ordered) as $row) {
            if ((int) $row['userId'] === $userId) {
                return (int) $row['place'];
            }
        }

        return 1;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, TrainingChallengePublication>  $ordered
     * @return list<array<string, mixed>>
     */
    private function withContestPlaces($ordered): array
    {
        $rows = [];
        $place = 0;
        $index = 0;
        $previousScore = null;
        foreach ($ordered as $publication) {
            $index++;
            $score = (int) $publication->score;
            if ($previousScore === null || $score !== $previousScore) {
                $place = $index;
                $previousScore = $score;
            }
            $user = $publication->user;
            $player = $user?->player;
            $rows[] = [
                'place' => $place,
                'userId' => (int) $publication->user_id,
                'name' => (string) ($player?->name ?? $user?->name ?? 'Gracz'),
                'avatarUrl' => $player?->avatarUrl(),
                'score' => $score,
                'publishedAt' => $publication->published_at?->toIso8601String(),
            ];
        }

        return $rows;
    }
}
