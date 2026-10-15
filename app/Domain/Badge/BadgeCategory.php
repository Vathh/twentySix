<?php

namespace App\Domain\Badge;

enum BadgeCategory: string
{
    case Checkout = 'checkout';

    case HighVisit = 'high_visit';

    case Qf = 'qf';

    case Finish = 'finish';

    case Career = 'career';
}
