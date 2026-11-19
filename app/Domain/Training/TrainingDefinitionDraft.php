<?php

namespace App\Domain\Training;

final readonly class TrainingDefinitionDraft
{
    /**
     * @param  list<TrainingDefinitionItemDraft>  $items
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

        if ($kind === TrainingKind::Challenge && $mechanic === null && $items === []) {
            throw new TrainingException(
                'Wyzwanie wymaga mechaniki albo listy ćwiczeń.',
                'training_definition_invalid',
            );
        }

        if ($kind === TrainingKind::Challenge || $kind === TrainingKind::Event) {
            if ($difficulty !== null) {
                throw new TrainingException('Poziom trudności dotyczy ćwiczenia i planu.', 'training_definition_invalid');
            }
            if ($declaredMinutes !== null) {
                throw new TrainingException('Deklarowany czas dotyczy planu.', 'training_definition_invalid');
            }
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
        );
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

            self::assertAllowedKeys($item, ['kind', 'exerciseKey', 'breakSeconds', 'durationSeconds', 'dartCount']);
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
                    || array_key_exists('dartCount', $item)) {
                    throw new TrainingException('Przerwa nie wskazuje ćwiczenia.', 'training_definition_invalid');
                }
                $items[] = new TrainingDefinitionItemDraft($itemKind, null, $seconds, null, null);

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
                    'Czas i liczbę lotek zmieniasz w planie.',
                    'training_exercise_limit_on_plan',
                );
            }
            $limits = $kind === TrainingKind::Plan
                ? TrainingExerciseLimits::forPlanSlot($exerciseKey, $item)
                : TrainingExerciseCatalog::parameters($exerciseKey);
            $items[] = new TrainingDefinitionItemDraft(
                $itemKind,
                $exerciseKey,
                null,
                $limits['durationSeconds'],
                $limits['dartCount'],
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
}
