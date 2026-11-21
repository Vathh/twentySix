<?php

namespace App\Domain\Training;

enum TrainingEventAttemptStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Voided = 'voided';
}
