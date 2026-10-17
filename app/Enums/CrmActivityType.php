<?php

declare(strict_types=1);

namespace App\Enums;

enum CrmActivityType: string
{
    case Interaction = 'interaction';
    case FollowUp = 'follow_up';
}
