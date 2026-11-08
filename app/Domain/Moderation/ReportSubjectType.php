<?php

namespace App\Domain\Moderation;

enum ReportSubjectType: string
{
    case Post = 'post';
    case Comment = 'comment';
    case User = 'user';
}
