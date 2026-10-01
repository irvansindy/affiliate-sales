<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductType: string
{
    case Website = 'website';
    case MobileApp = 'mobile_app';
}
