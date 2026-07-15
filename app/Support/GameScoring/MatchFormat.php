<?php

namespace App\Support\GameScoring;

use App\Enums\GameStage;
use DomainException;

readonly class MatchFormat
{
    public const DEFAULT_STARTING_SCORE = 501;

    public const DEFAULT_LEGS_TO_WIN_SET = 2;

    public const DEFAULT_SETS_TO_WIN_MATCH = 1;

    public const DEFAULT_GAME_TYPE = 'x01';

    public const DEFAULT_OUT_RULE = 'double_out';

    /** @var list<int> */
    public const ALLOWED_STARTING_SCORES = [101, 201, 301, 401, 501, 601, 701, 801, 901, 1001];

    public function __construct(
        public int $startingScore = self::DEFAULT_STARTING_SCORE,
        public int $legsToWinSet = self::DEFAULT_LEGS_TO_WIN_SET,
        public int $setsToWinMatch = self::DEFAULT_SETS_TO_WIN_MATCH,
        public string $gameType = self::DEFAULT_GAME_TYPE,
        public string $outRule = self::DEFAULT_OUT_RULE,
    ) {
    }

    public static function default(): self
    {
        return new self;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            startingScore: (int) ($data['startingScore'] ?? $data['starting_score'] ?? self::DEFAULT_STARTING_SCORE),
            legsToWinSet: (int) ($data['legsToWinSet'] ?? $data['legs_to_win_set'] ?? self::DEFAULT_LEGS_TO_WIN_SET),
            setsToWinMatch: (int) ($data['setsToWinMatch'] ?? $data['sets_to_win_match'] ?? self::DEFAULT_SETS_TO_WIN_MATCH),
            gameType: self::normalizeGameType((string) ($data['gameType'] ?? $data['game_type'] ?? self::DEFAULT_GAME_TYPE)),
            outRule: (string) ($data['outRule'] ?? $data['out_rule'] ?? self::DEFAULT_OUT_RULE),
        );
    }

    public static function fromRecord(object $record): self
    {
        return new self(
            startingScore: (int) ($record->starting_score ?? self::DEFAULT_STARTING_SCORE),
            legsToWinSet: (int) ($record->legs_to_win_set ?? self::DEFAULT_LEGS_TO_WIN_SET),
            setsToWinMatch: (int) ($record->sets_to_win_match ?? self::DEFAULT_SETS_TO_WIN_MATCH),
            gameType: self::normalizeGameType((string) ($record->game_type ?? self::DEFAULT_GAME_TYPE)),
            outRule: self::DEFAULT_OUT_RULE,
        );
    }

    public static function normalizeGameType(string $gameType): string
    {
        if ($gameType === '501') {
            return self::DEFAULT_GAME_TYPE;
        }

        return $gameType !== '' ? $gameType : self::DEFAULT_GAME_TYPE;
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

    public function formatLabel(): string
    {
        if ($this->isSingleSet()) {
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
    }

    public function validateForStage(GameStage $stage): void
    {
        $this->validate();
    }
}
