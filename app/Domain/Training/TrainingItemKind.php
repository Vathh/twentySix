<?php

namespace App\Domain\Training;

enum TrainingItemKind: string
{
    case Exercise = 'exercise';
    case Break = 'break';
}
