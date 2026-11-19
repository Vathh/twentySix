<?php

namespace App\Services\Training;

use App\Domain\Training\TrainingDefinitionRules;
use App\Domain\Training\TrainingException;
use App\Domain\Training\TrainingExerciseCatalog;
use App\Domain\Training\TrainingKind;
use App\Domain\Training\TrainingSessionStats;
use App\Models\Training\TrainingSession;
use App\Models\Users\User;
use App\Repositories\Training\TrainingDefinitionRepository;
use App\Repositories\Training\TrainingSessionRepository;
use App\Support\Http\DomainExceptionHttp;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class TrainingSessionService
{
    public function __construct(
        private TrainingDefinitionRepository $definitions,
        private TrainingSessionRepository $sessions,
    ) {}

    /**
     * Zapis ukończonej sesji jest niezmienny. Drugi zapis tego samego
     * clientSessionId na tym koncie jest odrzucany i nie tworzy kolejnej historii.
     *
     * @param  array<mixed>  $payload
     */
    public function complete(User $user, array $payload): TrainingSession
    {
        if (array_is_list($payload)) {
            throw new TrainingException('Sesja musi być obiektem.', 'training_session_invalid');
        }

        $this->assertAllowedKeys($payload);

        $clientSessionId = (string) ($payload['clientSessionId'] ?? '');
        $hasDefinition = array_key_exists('definitionId', $payload) && $payload['definitionId'] !== null && $payload['definitionId'] !== '';
        $hasExercise = array_key_exists('exerciseKey', $payload) && $payload['exerciseKey'] !== null && $payload['exerciseKey'] !== '';
        if ($hasDefinition === $hasExercise) {
            throw new TrainingException(
                'Sesja wskazuje ćwiczenie albo plan, wyzwanie lub event.',
                'training_session_invalid',
            );
        }
        $definitionId = $hasDefinition ? (int) $payload['definitionId'] : null;
        $exerciseKey = $hasExercise ? (string) $payload['exerciseKey'] : null;
        $completedAt = CarbonImmutable::parse((string) ($payload['completedAt'] ?? ''));
        $score = $this->score($payload['score'] ?? null);

        try {
            return DB::transaction(function () use ($user, $clientSessionId, $definitionId, $exerciseKey, $completedAt, $score, $payload): TrainingSession {
                if ($this->sessions->findForUser($user->id, $clientSessionId) !== null) {
                    throw $this->alreadyStored();
                }

                $stats = TrainingSessionStats::assertAggregate($payload['stats'] ?? null);
                if ($exerciseKey !== null) {
                    if (TrainingExerciseCatalog::parameters($exerciseKey) === null) {
                        throw new TrainingException(
                            'Wskazane ćwiczenie nie istnieje.',
                            'training_exercise_not_found',
                            DomainExceptionHttp::NOT_FOUND,
                        );
                    }

                    return $this->sessions->create(
                        $user->id,
                        $clientSessionId,
                        TrainingKind::Exercise,
                        null,
                        $exerciseKey,
                        $completedAt,
                        $score,
                        $stats,
                    );
                }

                $definition = $this->definitions->lock((int) $definitionId);
                if ($definition->kind === TrainingKind::Exercise) {
                    throw new TrainingException(
                        'Sesja ćwiczenia wskazuje ćwiczenie z listy.',
                        'training_session_invalid',
                    );
                }
                TrainingDefinitionRules::assertCompletable($definition->status);

                return $this->sessions->create(
                    $user->id,
                    $clientSessionId,
                    $definition->kind,
                    (int) $definition->id,
                    null,
                    $completedAt,
                    $score,
                    $stats,
                );
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->alreadyStored();
        }
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function assertAllowedKeys(array $payload): void
    {
        $extra = array_diff(array_map(strval(...), array_keys($payload)), [
            'clientSessionId',
            'definitionId',
            'exerciseKey',
            'completedAt',
            'score',
            'stats',
        ]);
        if ($extra === []) {
            return;
        }

        throw new TrainingException(
            'Pole „'.array_values($extra)[0].'” nie należy do zapisu sesji.',
            'training_unexpected_field',
        );
    }

    private function score(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new TrainingException(
            'Wynik musi być liczbą całkowitą.',
            'training_session_invalid',
        );
    }

    private function alreadyStored(): TrainingException
    {
        return new TrainingException(
            'Ta sesja została już zapisana.',
            'training_session_already_stored',
            DomainExceptionHttp::CONFLICT,
        );
    }
}
