<?php

namespace App\Domain\Training;

final readonly class TrainingDefinitionDraft
{
    /**
     * @param  list<TrainingDefinitionItemDraft>  $items
     * @param  array<string, int>|null  $badgeThresholds
     */
    public function __construct(
        public TrainingKind $kind,
        public string $name,
        public ?string $description,
        public ?TrainingDifficulty $difficulty,
        public ?int $declaredMinutes,
        public ?string $mechanic,
        public ?int $durationSeconds,
        public ?int $dartCount,
        public array $items,
        public ?array $badgeThresholds = null,
        public ?string $startsAt = null,
        public ?string $endsAt = null,
        public ?int $attemptsPerWindow = null,
        public ?string $windowMode = null,
        public ?string $windowAnchorDate = null,
        public ?int $attemptTimeoutHours = null,
    ) {}

    /**
     * @param  array<mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        if (array_is_list($payload)) {
            throw new TrainingException('Definicja musi być obiektem.', 'training_definition_invalid');
        }

        self::assertAllowedKeys($payload, [
            'kind',
            'name',
            'description',
            'difficulty',
            'declaredMinutes',
            'mechanic',
            'durationSeconds',
            'dartCount',
            'items',
            'badgeThresholds',
            'startsAt',
            'endsAt',
            'attemptsPerWindow',
            'windowMode',
            'windowAnchorDate',
            'attemptTimeoutHours',
        ]);

        $kind = TrainingKind::tryFrom((string) ($payload['kind'] ?? ''));
        if ($kind === null) {
            throw new TrainingException('Nieznany rodzaj treści.', 'training_definition_invalid');
        }
        if ($kind === TrainingKind::Exercise) {
            throw new TrainingException(
                'Ćwiczenia są gotową listą. Czas albo liczbę lotek ustawiasz w planie.',
                'training_exercise_is_catalog',
            );
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            throw new TrainingException('Definicja wymaga nazwy.', 'training_definition_invalid');
        }

        $description = self::description($payload['description'] ?? null);
        $difficulty = self::difficulty($payload['difficulty'] ?? null);
        $declaredMinutes = self::optionalInt($payload['declaredMinutes'] ?? null, 'declaredMinutes');
        $mechanic = self::mechanic($payload['mechanic'] ?? null);
        $items = self::items($payload['items'] ?? null, $kind);

        if ($declaredMinutes !== null && ! in_array($declaredMinutes, [30, 60, 90, 120], true)) {
            throw new TrainingException(
                'Deklarowany czas planu to 30, 60, 90 albo 120 minut.',
                'training_definition_invalid',
            );
        }

        $durationSeconds = null;
        $dartCount = null;

        if (array_key_exists('durationSeconds', $payload) || array_key_exists('dartCount', $payload)) {
            throw new TrainingException(
                'Czas i liczbę lotek ustawiasz przy ćwiczeniu w planie.',
                'training_definition_invalid',
            );
        }

        if ($kind === TrainingKind::Plan) {
            if ($difficulty === null) {
                throw new TrainingException('Plan wymaga poziomu trudności.', 'training_definition_invalid');
            }
            if ($declaredMinutes === null) {
                throw new TrainingException('Plan wymaga deklarowanego czasu.', 'training_definition_invalid');
            }
            if ($mechanic !== null) {
                throw new TrainingException('Plan składa się z ćwiczeń i przerw.', 'training_definition_invalid');
            }
            if ($items === []) {
                throw new TrainingException('Plan wymaga co najmniej jednego elementu.', 'training_definition_invalid');
            }
        }

        if ($kind === TrainingKind::Challenge) {
            if ($mechanic !== null) {
                throw new TrainingException(
                    'Wyzwanie składa się z ćwiczeń — bez gotowej mechaniki katalogu.',
                    'training_definition_invalid',
                );
            }
            if ($items === []) {
                throw new TrainingException(
                    'Wyzwanie wymaga co najmniej jednego ćwiczenia.',
                    'training_definition_invalid',
                );
            }
            foreach ($items as $item) {
                if ($item->kind === TrainingItemKind::Break) {
                    throw new TrainingException(
                        'Wyzwanie nie ma przerw — tylko ćwiczenia.',
                        'training_definition_invalid',
                    );
                }
            }
        }

        if ($kind === TrainingKind::Challenge || $kind === TrainingKind::Event) {
            if ($difficulty !== null) {
                throw new TrainingException('Poziom trudności dotyczy ćwiczenia i planu.', 'training_definition_invalid');
            }
            if ($declaredMinutes !== null) {
                throw new TrainingException('Deklarowany czas dotyczy planu.', 'training_definition_invalid');
            }
        }

        $badgeThresholds = null;
        if ($kind === TrainingKind::Challenge) {
            if (array_key_exists('badgeThresholds', $payload) && $payload['badgeThresholds'] !== null) {
                $badgeThresholds = TrainingBadgeThresholds::normalize($payload['badgeThresholds']);
                if ($badgeThresholds === null) {
                    throw new TrainingException(
                        'Progi odznaki są nieprawidłowe.',
                        'training_badge_thresholds_invalid',
                    );
                }
            }
        } elseif (array_key_exists('badgeThresholds', $payload) && $payload['badgeThresholds'] !== null) {
            throw new TrainingException(
                'Progi odznaki dotyczą wyzwania.',
                'training_definition_invalid',
            );
        }

        $startsAt = null;
        $endsAt = null;
        $attemptsPerWindow = null;
        $windowMode = null;
        $windowAnchorDate = null;
        $attemptTimeoutHours = null;

        if ($kind === TrainingKind::Event) {
            if ($mechanic !== null) {
                throw new TrainingException('Event składa się z ćwiczeń.', 'training_definition_invalid');
            }
            if ($items === []) {
                throw new TrainingException('Event wymaga co najmniej jednego ćwiczenia.', 'training_definition_invalid');
            }
            foreach ($items as $item) {
                if ($item->kind === TrainingItemKind::Break) {
                    throw new TrainingException(
                        'Event nie ma przerw — tylko ćwiczenia.',
                        'training_definition_invalid',
                    );
                }
            }
            $startsAt = self::dateTimeString($payload['startsAt'] ?? null, 'startsAt');
            $endsAt = self::dateTimeString($payload['endsAt'] ?? null, 'endsAt');
            if ($startsAt === null || $endsAt === null) {
                throw new TrainingException('Event wymaga daty startu i końca.', 'training_definition_invalid');
            }
            if (strcmp($endsAt, $startsAt) <= 0) {
                throw new TrainingException('Data końca musi być po starcie.', 'training_definition_invalid');
            }
            $attemptsPerWindow = self::optionalInt($payload['attemptsPerWindow'] ?? null, 'attemptsPerWindow');
            if ($attemptsPerWindow === null || $attemptsPerWindow < 1 || $attemptsPerWindow > 20) {
                throw new TrainingException(
                    'Liczba prób w oknie to 1–20.',
                    'training_definition_invalid',
                );
            }
            $windowMode = is_string($payload['windowMode'] ?? null) ? (string) $payload['windowMode'] : null;
            if ($windowMode === null || ! in_array($windowMode, TrainingEventWindow::modes(), true)) {
                throw new TrainingException('Wybierz tryb okna prób.', 'training_definition_invalid');
            }
            if ($windowMode === TrainingEventWindow::MODE_FROM_ANCHOR) {
                $windowAnchorDate = self::dateString($payload['windowAnchorDate'] ?? null, 'windowAnchorDate');
                if ($windowAnchorDate === null) {
                    throw new TrainingException(
                        'Tryb od wskazanej daty wymaga daty kotwicy.',
                        'training_definition_invalid',
                    );
                }
            }
            $attemptTimeoutHours = self::optionalInt($payload['attemptTimeoutHours'] ?? null, 'attemptTimeoutHours')
                ?? TrainingEventWindow::DEFAULT_TIMEOUT_HOURS;
            if ($attemptTimeoutHours < 1 || $attemptTimeoutHours > 72) {
                throw new TrainingException(
                    'Limit czasu otwartej próby to 1–72 godziny.',
                    'training_definition_invalid',
                );
            }
        } elseif (array_key_exists('startsAt', $payload) || array_key_exists('windowMode', $payload)) {
            throw new TrainingException('Harmonogram dotyczy eventu.', 'training_definition_invalid');
        }

        return new self(
            $kind,
            $name,
            $description,
            $difficulty,
            $declaredMinutes,
            $mechanic,
            $durationSeconds,
            $dartCount,
            $items,
            $badgeThresholds,
            $startsAt,
            $endsAt,
            $attemptsPerWindow,
            $windowMode,
            $windowAnchorDate,
            $attemptTimeoutHours,
        );
    }

    private static function dateTimeString(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) && ! is_numeric($value)) {
            throw new TrainingException('Nieprawidłowa data '.$field.'.', 'training_definition_invalid');
        }
        try {
            return \Carbon\CarbonImmutable::parse((string) $value)->toIso8601String();
        } catch (\Throwable) {
            throw new TrainingException('Nieprawidłowa data '.$field.'.', 'training_definition_invalid');
        }
    }

    private static function dateString(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value)) {
            throw new TrainingException('Nieprawidłowa data '.$field.'.', 'training_definition_invalid');
        }
        try {
            return \Carbon\CarbonImmutable::parse($value)->toDateString();
        } catch (\Throwable) {
            throw new TrainingException('Nieprawidłowa data '.$field.'.', 'training_definition_invalid');
        }
    }

    /**
     * @param  array<mixed>  $payload
     * @param  list<string>  $allowed
     */
    private static function assertAllowedKeys(array $payload, array $allowed): void
    {
        $extra = array_diff(array_map(strval(...), array_keys($payload)), $allowed);
        if ($extra === []) {
            return;
        }

        $field = array_values($extra)[0];
        throw new TrainingException(
            'Pole „'.$field.'” nie należy do tego zapisu.',
            'training_unexpected_field',
        );
    }

    private static function description(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw new TrainingException('Opis musi być tekstem.', 'training_definition_invalid');
        }

        $description = trim($value);
        if ($description === '') {
            return null;
        }
        if (mb_strlen($description) > 5000) {
            throw new TrainingException('Opis jest zbyt długi.', 'training_definition_invalid');
        }

        return $description;
    }

    private static function difficulty(mixed $value): ?TrainingDifficulty
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value)) {
            throw new TrainingException('Nieznany poziom trudności.', 'training_definition_invalid');
        }

        return TrainingDifficulty::tryFrom($value)
            ?? throw new TrainingException('Nieznany poziom trudności.', 'training_definition_invalid');
    }

    private static function mechanic(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) || preg_match('/^[a-z][a-z0-9_]{0,63}$/', $value) !== 1) {
            throw new TrainingException('Nieznana mechanika.', 'training_definition_invalid');
        }

        return $value;
    }

    private static function exerciseKey(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) || preg_match('/^[a-z][a-z0-9_]{0,63}$/', $value) !== 1) {
            throw new TrainingException('Wskazane ćwiczenie nie istnieje.', 'training_exercise_not_found');
        }

        return $value;
    }

    /**
     * @return list<TrainingDefinitionItemDraft>
     */
    private static function items(mixed $value, TrainingKind $kind): array
    {
        if ($value === null) {
            return [];
        }
        if (! is_array($value) || ($value !== [] && ! array_is_list($value))) {
            throw new TrainingException('Elementy definicji muszą być listą.', 'training_definition_invalid');
        }
        if (count($value) > 80) {
            throw new TrainingException('Definicja ma zbyt wiele elementów.', 'training_definition_invalid');
        }

        $items = [];
        foreach ($value as $item) {
            if (! is_array($item) || array_is_list($item)) {
                throw new TrainingException('Element definicji musi być obiektem.', 'training_definition_invalid');
            }

            self::assertAllowedKeys($item, [
                'kind',
                'exerciseKey',
                'breakSeconds',
                'durationSeconds',
                'dartCount',
                'scored',
                'variant',
            ]);
            $itemKind = TrainingItemKind::tryFrom((string) ($item['kind'] ?? ''));
            if ($itemKind === null) {
                throw new TrainingException('Nieznany rodzaj elementu.', 'training_definition_invalid');
            }
            if ($itemKind === TrainingItemKind::Break && $kind !== TrainingKind::Plan) {
                throw new TrainingException('Przerwa należy tylko do planu.', 'training_definition_invalid');
            }

            if ($itemKind === TrainingItemKind::Break) {
                $seconds = self::optionalInt($item['breakSeconds'] ?? null, 'breakSeconds');
                if ($seconds === null || $seconds < 1 || $seconds > 7200) {
                    throw new TrainingException(
                        'Przerwa trwa od 1 sekundy do 2 godzin.',
                        'training_definition_invalid',
                    );
                }
                if (($item['exerciseKey'] ?? null) !== null
                    || array_key_exists('durationSeconds', $item)
                    || array_key_exists('dartCount', $item)
                    || array_key_exists('scored', $item)
                    || array_key_exists('variant', $item)) {
                    throw new TrainingException('Przerwa nie wskazuje ćwiczenia.', 'training_definition_invalid');
                }
                $items[] = new TrainingDefinitionItemDraft($itemKind, null, $seconds, null, null, true, null);

                continue;
            }

            if (($item['breakSeconds'] ?? null) !== null) {
                throw new TrainingException('Ćwiczenie w planie nie ma własnej przerwy.', 'training_definition_invalid');
            }
            $exerciseKey = self::exerciseKey($item['exerciseKey'] ?? null);
            if ($exerciseKey === null) {
                throw new TrainingException('Element wskazuje ćwiczenie.', 'training_definition_invalid');
            }
            if (TrainingExerciseCatalog::parameters($exerciseKey) === null) {
                throw new TrainingException(
                    'Wskazane ćwiczenie nie istnieje.',
                    'training_exercise_not_found',
                );
            }
            if ($kind !== TrainingKind::Plan && (array_key_exists('durationSeconds', $item) || array_key_exists('dartCount', $item))) {
                throw new TrainingException(
                    'Czas i liczbę lotek zmieniasz w planie. Wyzwanie i event biorą domyślne.',
                    'training_exercise_limit_on_plan',
                );
            }
            if ($kind !== TrainingKind::Plan && $kind !== TrainingKind::Event && $kind !== TrainingKind::Challenge
                && (array_key_exists('scored', $item) || array_key_exists('variant', $item))) {
                throw new TrainingException(
                    'Punktowanie i wariant ustawiasz w planie, wyzwaniu albo evencie.',
                    'training_definition_invalid',
                );
            }
            $limits = $kind === TrainingKind::Plan
                ? TrainingExerciseLimits::forPlanSlot($exerciseKey, $item)
                : TrainingExerciseCatalog::parameters($exerciseKey);
            $scored = self::scored($item['scored'] ?? true);
            $variant = self::variant($exerciseKey, $item['variant'] ?? null, $kind);
            $items[] = new TrainingDefinitionItemDraft(
                $itemKind,
                $exerciseKey,
                null,
                $limits['durationSeconds'],
                $limits['dartCount'],
                $scored,
                $variant,
            );
        }

        return $items;
    }

    private static function optionalInt(mixed $value, string $field): ?int
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
            'Pole „'.$field.'” musi być liczbą całkowitą.',
            'training_definition_invalid',
        );
    }

    private static function scored(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 1 || $value === '1' || $value === 'true') {
            return true;
        }
        if ($value === 0 || $value === '0' || $value === 'false') {
            return false;
        }

        throw new TrainingException(
            'Pole „scored” musi być wartością logiczną.',
            'training_definition_invalid',
        );
    }

    private static function variant(string $exerciseKey, mixed $value, TrainingKind $kind): ?string
    {
        if ($exerciseKey !== 'scoring_under_pressure') {
            if ($value !== null && $value !== '') {
                throw new TrainingException(
                    'Wariant dotyczy tylko Scoringu z presją.',
                    'training_definition_invalid',
                );
            }

            return null;
        }

        if ($kind !== TrainingKind::Plan && $kind !== TrainingKind::Event && $kind !== TrainingKind::Challenge) {
            if ($value !== null && $value !== '') {
                throw new TrainingException(
                    'Wariant Scoringu z presją ustawiasz w planie, wyzwaniu albo evencie.',
                    'training_definition_invalid',
                );
            }

            return null;
        }

        if (! is_string($value) && $value !== null) {
            throw new TrainingException(
                'Nieznany wariant Scoringu z presją.',
                'training_definition_invalid',
            );
        }

        return TrainingScoringPressureVariant::assert(is_string($value) ? $value : null);
    }
}
