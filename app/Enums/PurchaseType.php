<?php

declare(strict_types=1);

namespace App\Enums;

enum PurchaseType: string
{
    case Subscription = 'subscription';
    case OneTime = 'one_time';
}
