<?php

declare(strict_types=1);

namespace App\Enums;

enum SaleType: string
{
    case NewSale = 'new';
    case Renewal = 'renewal';
    case PlanChange = 'plan_change';
}
