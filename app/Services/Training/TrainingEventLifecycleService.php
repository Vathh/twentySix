<?php

namespace App\Services\Training;

use App\Domain\Training\TrainingDefinitionStatus;
use App\Domain\Training\TrainingException;
use App\Domain\Training\TrainingKind;
use App\Models\Training\TrainingDefinition;
use App\Models\Training\TrainingEventDisqualification;
use App\Models\Users\User;
use App\Repositories\Training\TrainingDefinitionRepository;
use App\Repositories\Training\TrainingEventAttemptRepository;
use App\Support\Http\DomainExceptionHttp;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class TrainingEventLifecycleService
{
    public function __construct(
        private TrainingDefinitionRepository $definitions,
        private TrainingEventAttemptRepository $attempts,
    ) {}

    public function startEarly(User $admin, int $id): TrainingDefinition
    {
        $this->assertAdmin($admin);

        return DB::transaction(function () use ($id): TrainingDefinition {
            $definition = $this->definitions->lock($id);
            $this->assertEvent($definition);
            if ($definition->status !== TrainingDefinitionStatus::Inactive
                && $definition->status !== TrainingDefinitionStatus::Active) {
                throw new TrainingException('Eventu nie da się już wystartować.', 'training_definition_locked');
            }
            $definition->starts_at = CarbonImmutable::now();
            $definition->status = TrainingDefinitionStatus::Active;
            if ($definition->published_at === null) {
                $definition->published_at = now();
            }
            $definition->save();

            return $definition->load('items');
        });
    }

    public function pause(User $admin, int $id): TrainingDefinition
    {
        $this->assertAdmin($admin);

        return DB::transaction(function () use ($id): TrainingDefinition {
            $definition = $this->definitions->lock($id);
            $this->assertEvent($definition);
            if ($definition->status !== TrainingDefinitionStatus::Active) {
                throw new TrainingException('Wstrzymać można tylko aktywny event.', 'training_definition_not_active');
            }
            $definition->status = TrainingDefinitionStatus::Paused;
            $definition->paused_at = CarbonImmutable::now();
            $definition->save();

            return $definition->load('items');
        });
    }

    public function resume(User $admin, int $id): TrainingDefinition
    {
        $this->assertAdmin($admin);

        return DB::transaction(function () use ($id): TrainingDefinition {
            $definition = $this->definitions->lock($id);
            $this->assertEvent($definition);
            if ($definition->status !== TrainingDefinitionStatus::Paused) {
                throw new TrainingException('Wznowić można tylko wstrzymany event.', 'training_definition_invalid');
            }
            $definition->status = TrainingDefinitionStatus::Active;
            $definition->paused_at = null;
            $definition->save();

            return $definition->load('items');
        });
    }

    public function closeEarly(User $admin, int $id): TrainingDefinition
    {
        $this->assertAdmin($admin);

        return DB::transaction(function () use ($id): TrainingDefinition {
            $definition = $this->definitions->lock($id);
            $this->assertEvent($definition);
            if (! in_array($definition->status, [
                TrainingDefinitionStatus::Active,
                TrainingDefinitionStatus::Paused,
            ], true)) {
                throw new TrainingException('Eventu nie da się wcześniej zakończyć.', 'training_definition_locked');
            }
            $definition->status = TrainingDefinitionStatus::Closing;
            $definition->closing_at = CarbonImmutable::now();
            $definition->save();

            return $this->maybeFinish($definition);
        });
    }

    public function maybeFinish(TrainingDefinition $definition): TrainingDefinition
    {
        if ($definition->kind !== TrainingKind::Event) {
            return $definition;
        }
        if ($definition->status !== TrainingDefinitionStatus::Closing
            && ! ($definition->ends_at !== null && CarbonImmutable::now()->gt($definition->ends_at)
                && in_array($definition->status, [
                    TrainingDefinitionStatus::Active,
                    TrainingDefinitionStatus::Paused,
                ], true))) {
            return $definition;
        }

        if ($this->attempts->hasOpenForEvent((int) $definition->id)) {
            if ($definition->status !== TrainingDefinitionStatus::Closing) {
                $definition->status = TrainingDefinitionStatus::Closing;
                $definition->closing_at = $definition->closing_at ?? CarbonImmutable::now();
                $definition->save();
            }

            return $definition->load('items');
        }

        $definition->status = TrainingDefinitionStatus::Finished;
        $definition->finished_at = CarbonImmutable::now();
        $definition->save();

        return $definition->load('items');
    }

    public function disqualify(User $admin, int $definitionId, int $userId): void
    {
        $this->assertAdmin($admin);
        DB::transaction(function () use ($admin, $definitionId, $userId): void {
            $definition = $this->definitions->lock($definitionId);
            $this->assertEvent($definition);
            $row = TrainingEventDisqualification::query()
                ->where('definition_id', $definitionId)
                ->where('user_id', $userId)
                ->first();
            if ($row instanceof TrainingEventDisqualification && $row->isActive()) {
                return;
            }
            if ($row instanceof TrainingEventDisqualification) {
                $row->disqualified_at = CarbonImmutable::now();
                $row->reinstated_at = null;
                $row->admin_id = $admin->id;
                $row->save();

                return;
            }
            TrainingEventDisqualification::query()->create([
                'definition_id' => $definitionId,
                'user_id' => $userId,
                'admin_id' => $admin->id,
                'disqualified_at' => CarbonImmutable::now(),
            ]);
        });
    }

    public function reinstate(User $admin, int $definitionId, int $userId): void
    {
        $this->assertAdmin($admin);
        DB::transaction(function () use ($definitionId, $userId): void {
            $definition = $this->definitions->lock($definitionId);
            $this->assertEvent($definition);
            $row = TrainingEventDisqualification::query()
                ->where('definition_id', $definitionId)
                ->where('user_id', $userId)
                ->whereNull('reinstated_at')
                ->first();
            if ($row instanceof TrainingEventDisqualification) {
                $row->reinstated_at = CarbonImmutable::now();
                $row->save();
            }
        });
    }

    private function assertEvent(TrainingDefinition $definition): void
    {
        if ($definition->kind !== TrainingKind::Event) {
            throw new TrainingException('To nie jest event.', 'training_definition_invalid');
        }
    }

    private function assertAdmin(User $actor): void
    {
        if (! method_exists($actor, 'isPlatformAdmin') || ! $actor->isPlatformAdmin()) {
            throw new TrainingException(
                'Brak uprawnień.',
                'training_forbidden',
                DomainExceptionHttp::FORBIDDEN,
            );
        }
    }
}
