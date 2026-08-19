<?php

namespace App\Domain\GameScoring;

use App\Enums\GameStage;
use App\Enums\MatchWinMode;
use DomainException;

readonly class MatchFormat
{
    public const DEFAULT_STARTING_SCORE = 501;

    public const DEFAULT_LEGS_TO_WIN_SET = 2;

    public const DEFAULT_SETS_TO_WIN_MATCH = 1;

    public const DEFAULT_GAME_TYPE = 'x01';

    public const GAME_TYPE_CRICKET = 'cricket';

    public const GAME_TYPE_BOB27 = 'bob27';

    public const GAME_TYPE_ATC = 'atc';

    public const GAME_TYPE_CATCH40 = 'catch40';

    public const GAME_TYPE_CRICKET56 = 'cricket56';

    public const DEFAULT_OUT_RULE = 'double_out';

    public const BOB27_MODE_EASY = 'easy';

    public const BOB27_MODE_HARD = 'hard';

    public const BOB27_BULL_WITH = 'with';

    public const BOB27_BULL_WITHOUT = 'without';

    /** @var list<int> */
    public const ALLOWED_STARTING_SCORES = [101, 201, 301, 401, 501, 601, 701, 801, 901, 1001];

    public int $winLength;

    public function __construct(
        public int $startingScore = self::DEFAULT_STARTING_SCORE,
        public int $legsToWinSet = self::DEFAULT_LEGS_TO_WIN_SET,
        public int $setsToWinMatch = self::DEFAULT_SETS_TO_WIN_MATCH,
        public string $gameType = self::DEFAULT_GAME_TYPE,
        public string $outRule = self::DEFAULT_OUT_RULE,
        public string $bob27Mode = self::BOB27_MODE_HARD,
        public string $bob27Bull = self::BOB27_BULL_WITH,
        public MatchWinMode $winMode = MatchWinMode::FIRST_TO,
        ?int $winLength = null,
    ) {
        $this->winLength = $winLength ?? (
            $winMode === MatchWinMode::BEST_OF
                ? max(1, ($legsToWinSet * 2) - 1)
                : ($setsToWinMatch === 1 ? $legsToWinSet : $setsToWinMatch)
        );
    }

    public static function default(): self
    {
        return new self;
    }

    public static function forLeagueRules(int $startingScore, MatchWinMode $winMode, int $length): self
    {
        if ($winMode === MatchWinMode::BEST_OF) {
            if ($length < 2 || $length > 16 || $length % 2 !== 0) {
                throw new DomainException('Best of z remisami: parzysta liczba legów (2–16).');
            }

            return new self(
                startingScore: $startingScore,
                legsToWinSet: intdiv($length, 2) + 1,
                setsToWinMatch: 1,
                winMode: MatchWinMode::BEST_OF,
                winLength: $length,
            );
        }

        if ($length < 1 || $length > 15) {
            throw new DomainException('First to: 1–15 legów.');
        }

        return new self(
            startingScore: $startingScore,
            legsToWinSet: $length,
            setsToWinMatch: 1,
            winMode: MatchWinMode::FIRST_TO,
            winLength: $length,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $winMode = self::normalizeWinMode($data['winMode'] ?? $data['win_mode'] ?? null);
        $explicitLength = $data['winLength'] ?? $data['win_length'] ?? null;
        $legs = (int) ($data['legsToWinSet'] ?? $data['legs_to_win_set'] ?? self::DEFAULT_LEGS_TO_WIN_SET);
        if ($winMode === MatchWinMode::BEST_OF && $explicitLength !== null) {
            $legs = intdiv((int) $explicitLength, 2) + 1;
        }

        return new self(
            startingScore: (int) ($data['startingScore'] ?? $data['starting_score'] ?? self::DEFAULT_STARTING_SCORE),
            legsToWinSet: $legs,
            setsToWinMatch: (int) ($data['setsToWinMatch'] ?? $data['sets_to_win_match'] ?? self::DEFAULT_SETS_TO_WIN_MATCH),
            gameType: self::normalizeGameType((string) ($data['gameType'] ?? $data['game_type'] ?? self::DEFAULT_GAME_TYPE)),
            outRule: (string) ($data['outRule'] ?? $data['out_rule'] ?? self::DEFAULT_OUT_RULE),
            bob27Mode: self::normalizeBob27Mode((string) ($data['bob27Mode'] ?? $data['bob27_mode'] ?? self::BOB27_MODE_HARD)),
            bob27Bull: self::normalizeBob27Bull((string) ($data['bob27Bull'] ?? $data['bob27_bull'] ?? self::BOB27_BULL_WITH)),
            winMode: $winMode,
            winLength: $explicitLength !== null ? (int) $explicitLength : null,
        );
    }

    public static function fromRecord(object $record): self
    {
        $winMode = self::normalizeWinMode($record->win_mode ?? $record->winMode ?? null);
        $explicitLength = $record->win_length ?? $record->winLength ?? null;
        $legs = (int) ($record->legs_to_win_set ?? self::DEFAULT_LEGS_TO_WIN_SET);
        if ($winMode === MatchWinMode::BEST_OF && $explicitLength !== null) {
            $legs = intdiv((int) $explicitLength, 2) + 1;
        }

        return new self(
            startingScore: (int) ($record->starting_score ?? self::DEFAULT_STARTING_SCORE),
            legsToWinSet: $legs,
            setsToWinMatch: (int) ($record->sets_to_win_match ?? self::DEFAULT_SETS_TO_WIN_MATCH),
            gameType: self::normalizeGameType((string) ($record->game_type ?? self::DEFAULT_GAME_TYPE)),
            outRule: self::DEFAULT_OUT_RULE,
            bob27Mode: self::normalizeBob27Mode((string) (
                (isset($record->bob27_mode) && $record->bob27_mode !== null)
                    ? $record->bob27_mode
                    : self::BOB27_MODE_HARD
            )),
            bob27Bull: self::normalizeBob27Bull((string) (
                (isset($record->bob27_bull) && $record->bob27_bull !== null)
                    ? $record->bob27_bull
                    : self::BOB27_BULL_WITH
            )),
            winMode: $winMode,
            winLength: $explicitLength !== null ? (int) $explicitLength : null,
        );
    }

    public static function normalizeWinMode(mixed $value): MatchWinMode
    {
        if ($value instanceof MatchWinMode) {
            return $value;
        }

        return MatchWinMode::tryFrom((string) $value) ?? MatchWinMode::FIRST_TO;
    }

    public static function normalizeGameType(string $gameType): string
    {
        if ($gameType === '501') {
            return self::DEFAULT_GAME_TYPE;
        }
        if ($gameType === 'around_the_clock' || $gameType === 'clock') {
            return self::GAME_TYPE_ATC;
        }
        if ($gameType === 'catch_40' || $gameType === 'catch-40') {
            return self::GAME_TYPE_CATCH40;
        }
        if (
            $gameType === 'cricket_56'
            || $gameType === 'cricket-56'
            || $gameType === 'cricket60'
            || $gameType === 'cricket_60'
            || $gameType === 'cricketsequence'
            || $gameType === 'cricket_sequence'
        ) {
            return self::GAME_TYPE_CRICKET56;
        }

        return $gameType !== '' ? $gameType : self::DEFAULT_GAME_TYPE;
    }

    public static function normalizeBob27Mode(string $mode): string
    {
        return strtolower($mode) === self::BOB27_MODE_EASY
            ? self::BOB27_MODE_EASY
            : self::BOB27_MODE_HARD;
    }

    public static function normalizeBob27Bull(string $value): string
    {
        return strtolower($value) === self::BOB27_BULL_WITHOUT
            ? self::BOB27_BULL_WITHOUT
            : self::BOB27_BULL_WITH;
    }

    public function isX01(): bool
    {
        return $this->gameType === self::DEFAULT_GAME_TYPE;
    }

    public function isCricket(): bool
    {
        return $this->gameType === self::GAME_TYPE_CRICKET;
    }

    public function isBob27(): bool
    {
        return $this->gameType === self::GAME_TYPE_BOB27;
    }

    public function includesBob27Bull(): bool
    {
        return $this->bob27Bull === self::BOB27_BULL_WITH;
    }

    public function isAtc(): bool
    {
        return $this->gameType === self::GAME_TYPE_ATC;
    }

    public function isCatch40(): bool
    {
        return $this->gameType === self::GAME_TYPE_CATCH40;
    }

    public function isCricket56(): bool
    {
        return $this->gameType === self::GAME_TYPE_CRICKET56;
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'startingScore' => $this->startingScore,
            'legsToWinSet' => $this->legsToWinSet,
            'setsToWinMatch' => $this->setsToWinMatch,
            'gameType' => $this->gameType,
            'outRule' => $this->outRule,
            'bob27Mode' => $this->bob27Mode,
            'bob27Bull' => $this->bob27Bull,
            'winMode' => $this->winMode->value,
            'winLength' => $this->winLength,
        ];
    }

    /**
     * @return array<string, int|string>
     */
    public function toDatabaseColumns(): array
    {
        return [
            'starting_score' => $this->startingScore,
            'legs_to_win_set' => $this->legsToWinSet,
            'sets_to_win_match' => $this->setsToWinMatch,
            'game_type' => $this->gameType,
        ];
    }

    public function isSingleSet(): bool
    {
        return $this->setsToWinMatch === 1;
    }

    /** Wynik meczu liczony w setach (nie w legach). */
    public function usesSetScore(): bool
    {
        return ! $this->isSingleSet();
    }

    /** Ile trzeba wygrać w jednostce wyniku meczu (legi lub sety). */
    public function scoreToWin(): int
    {
        return $this->isSingleSet() ? $this->legsToWinSet : $this->setsToWinMatch;
    }

    public function scoreUnit(): string
    {
        return $this->isSingleSet() ? 'legi' : 'sety';
    }

    public function isBestOf(): bool
    {
        return $this->winMode === MatchWinMode::BEST_OF;
    }

    public function allowsDraw(): bool
    {
        return $this->isBestOf() && $this->isSingleSet() && $this->winLength % 2 === 0;
    }

    public function formatLabel(): string
    {
        if ($this->isCricket()) {
            return sprintf('Cricket · do %d legów', $this->legsToWinSet);
        }
        if ($this->isBob27()) {
            $bull = $this->includesBob27Bull() ? 'z bullem' : 'bez bulla';

            return sprintf("Bob's 27 · %s · %s · do %d legów", $this->bob27Mode, $bull, $this->legsToWinSet);
        }
        if ($this->isAtc()) {
            return sprintf('Around the Clock · do %d legów', $this->legsToWinSet);
        }
        if ($this->isCatch40()) {
            return sprintf('Catch 40 · do %d legów', $this->legsToWinSet);
        }
        if ($this->isCricket56()) {
            return sprintf('Cricket 60 · do %d legów', $this->legsToWinSet);
        }
        if ($this->isSingleSet()) {
            if ($this->isBestOf()) {
                return sprintf('%d · best of %d', $this->startingScore, $this->winLength);
            }

            return sprintf('%d · do %d legów', $this->startingScore, $this->legsToWinSet);
        }

        return sprintf(
            '%d · %d sety · %d legi/set',
            $this->startingScore,
            $this->setsToWinMatch,
            $this->legsToWinSet,
        );
    }

    public function walkoverScoreLine(): string
    {
        $win = $this->scoreToWin();

        return sprintf('%d:0 %s', $win, $this->scoreUnit());
    }

    public function validate(): void
    {
        if (! in_array($this->startingScore, self::ALLOWED_STARTING_SCORES, true)) {
            throw new DomainException('Nieprawidłowe punkty startowe.');
        }

        if ($this->legsToWinSet < 1 || $this->legsToWinSet > 15) {
            throw new DomainException('Legi do seta muszą być między 1 a 15.');
        }

        if ($this->setsToWinMatch < 1 || $this->setsToWinMatch > 5) {
            throw new DomainException('Sety do meczu muszą być między 1 a 5.');
        }

        if ($this->winMode === MatchWinMode::BEST_OF) {
            if (! $this->isSingleSet()) {
                throw new DomainException('Best of jest dostępne tylko przy jednym secie (gra na legi).');
            }
            if ($this->winLength < 2 || $this->winLength > 16) {
                throw new DomainException('Best of: 2–16 legów.');
            }
        }
    }

    public function validateForStage(GameStage $stage): void
    {
        if (! $this->isX01()) {
            throw new DomainException('W turnieju dostępny jest tylko format X01.');
        }

        if ($this->winMode === MatchWinMode::BEST_OF) {
            throw new DomainException('W turnieju dostępny jest tylko First to — mecz musi mieć zwycięzcę.');
        }

        $this->validate();
    }
}
