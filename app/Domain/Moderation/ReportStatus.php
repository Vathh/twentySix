<?php

namespace App\Domain\Moderation;

enum ReportStatus: string
{
    case Open = 'open';
    case Dismissed = 'dismissed';
    case Resolved = 'resolved';
}
