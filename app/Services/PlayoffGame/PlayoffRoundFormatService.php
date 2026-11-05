<?php

namespace App\Services\PlayoffGame;

use App\Domain\GameScoring\MatchFormat;
use App\Enums\BracketSide;
use App\Enums\GameStage;
use App\Enums\GameStatus;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentMatchFormat;
use App\Repositories\Player\PlayerRepository;
use App\Services\Tournament\TournamentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlayoffRoundFormatService
{
    public function __construct(
        private TournamentService $tournamentService,
        private PlayerRepository $playerRepository,
    ) {}

    /**
     * @return array{
     *     tournament: Tournament,
     *     sides: list<array{side: BracketSide, label: string, stages: list<array<string, mixed>>}>
     * }
     */
    public function describe(int $tournamentId): array
    {
        $this->tournamentService->loadAndAuthorize($tournamentId);
        $tournament = $this->tournamentService->getModel($tournamentId);
        $this->assertToolApplies($tournament);

        $sides = [[
            'side' => BracketSide::Main,
            'label' => 'Drabinka główna',
            'stages' => $this->stagesFor($tournament, BracketSide::Main),
        ]];

        if ($tournament->has_consolation_bracket) {
            $sides[] = [
                'side' => BracketSide::Consolation,
                'label' => 'Drabinka pocieszenia',
                'stages' => $this->stagesFor($tournament, BracketSide::Consolation),
            ];
        }

        return [
            'tournament' => $tournament,
            'sides' => $sides,
        ];
    }

    /**
     * @param  array<string, array{legsToWinSet: int, setsToWinMatch: int}>  $stages
     * @return array{applied: list<string>, rejected: list<array{stage: string, message: string}>}
     */
    public function update(int $tournamentId, BracketSide $side, array $stages): array
    {
        $this->tournamentService->loadAndAuthorize($tournamentId);
        $tournament = $this->tournamentService->getModel($tournamentId);
        $this->assertToolApplies($tournament);

        if ($side === BracketSide::Consolation && ! $tournament->has_consolation_bracket) {
            throw ValidationException::withMessages([
                'bracketSide' => 'Ten turniej nie ma drabinki pocieszenia.',
            ]);
        }

        $applied = [];
        $rejected = [];
        $byeId = $this->playerRepository->byePlayerId();
        $userId = Auth::id();

        DB::transaction(function () use ($tournament, $side, $stages, $byeId, $userId, &$applied, &$rejected) {
            foreach ($stages as $stageValue => $input) {
                $stage = GameStage::tryFrom((string) $stageValue);
                if ($stage === null || $stage === GameStage::GROUP) {
                    $rejected[] = [
                        'stage' => (string) $stageValue,
                        'message' => 'Nieznany szczebel.',
                    ];

                    continue;
                }

                $formatRow = TournamentMatchFormat::query()
                    ->where('tournament_id', $tournament->id)
                    ->where('bracket_side', $side)
                    ->where('stage', $stage->value)
                    ->first();

                if ($formatRow === null) {
                    $rejected[] = [
                        'stage' => $stage->value,
                        'message' => $stage->label().' nie występuje w tej drabince.',
                    ];

                    continue;
                }

                if ($this->roundHasStarted($tournament->id, $side, $stage, $byeId)) {
                    $rejected[] = [
                        'stage' => $stage->value,
                        'message' => $stage->label().' już się zaczął.',
                    ];

                    continue;
                }

                $legs = (int) $input['legsToWinSet'];
                $sets = (int) $input['setsToWinMatch'];
                $current = $formatRow->toMatchFormat();
                $next = MatchFormat::fromArray(array_merge($current->toArray(), [
                    'legsToWinSet' => $legs,
                    'setsToWinMatch' => $sets,
                ]));
                $next->validateForStage($stage);

                $formatRow->fill($next->toDatabaseColumns());
                $formatRow->save();

                PlayoffGame::query()
                    ->where('tournament_id', $tournament->id)
                    ->where('bracket_side', $side)
                    ->where('round', $stage->value)
                    ->where('status', GameStatus::SCHEDULED)
                    ->where(function ($query) use ($byeId) {
                        $query->whereNull('player1_id')->orWhere('player1_id', '!=', $byeId);
                    })
                    ->where(function ($query) use ($byeId) {
                        $query->whereNull('player2_id')->orWhere('player2_id', '!=', $byeId);
                    })
                    ->update([
                        'legs_to_win_set' => $next->legsToWinSet,
                        'sets_to_win_match' => $next->setsToWinMatch,
                    ]);

                DB::table('tournament_round_format_changes')->insert([
                    'tournament_id' => $tournament->id,
                    'bracket_side' => $side->value,
                    'stage' => $stage->value,
                    'user_id' => $userId,
                    'legs_from' => $current->legsToWinSet,
                    'legs_to' => $next->legsToWinSet,
                    'sets_from' => $current->setsToWinMatch,
                    'sets_to' => $next->setsToWinMatch,
                    'created_at' => now(),
                ]);

                $applied[] = $stage->value;
            }
        });

        return [
            'applied' => $applied,
            'rejected' => $rejected,
        ];
    }

    private function assertToolApplies(Tournament $tournament): void
    {
        if ($tournament->format === TournamentFormat::DoubleElimination) {
            throw ValidationException::withMessages([
                'bracketSide' => 'Zmiana dystansu nie obejmuje podwójnej eliminacji.',
            ]);
        }

        if (! in_array($tournament->status, [TournamentStatus::GROUP, TournamentStatus::PLAYOFF], true)) {
            throw ValidationException::withMessages([
                'bracketSide' => 'Dystans rund można zmienić po starcie turnieju.',
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stagesFor(Tournament $tournament, BracketSide $side): array
    {
        $byeId = $this->playerRepository->byePlayerId();
        $order = array_flip(array_map(
            static fn (GameStage $stage): string => $stage->value,
            GameStage::cases(),
        ));

        return TournamentMatchFormat::query()
            ->where('tournament_id', $tournament->id)
            ->where('bracket_side', $side)
            ->get()
            ->sortBy(fn (TournamentMatchFormat $row) => $order[$row->stage] ?? 99)
            ->map(function (TournamentMatchFormat $row) use ($tournament, $side, $byeId) {
                $stage = GameStage::from($row->stage);
                $started = $this->roundHasStarted($tournament->id, $side, $stage, $byeId);

                return [
                    'stage' => $stage->value,
                    'label' => $stage->label(),
                    'legsToWinSet' => (int) $row->legs_to_win_set,
                    'setsToWinMatch' => (int) $row->sets_to_win_match,
                    'editable' => ! $started,
                    'reason' => $started ? $stage->label().' już się zaczął.' : null,
                ];
            })
            ->values()
            ->all();
    }

    private function roundHasStarted(int $tournamentId, BracketSide $side, GameStage $stage, int $byeId): bool
    {
        return PlayoffGame::query()
            ->where('tournament_id', $tournamentId)
            ->where('bracket_side', $side)
            ->where('round', $stage->value)
            ->whereIn('status', [GameStatus::IN_PROGRESS, GameStatus::FINISHED])
            ->where(function ($query) use ($byeId) {
                $query->whereNull('player1_id')->orWhere('player1_id', '!=', $byeId);
            })
            ->where(function ($query) use ($byeId) {
                $query->whereNull('player2_id')->orWhere('player2_id', '!=', $byeId);
            })
            ->exists();
    }
}
