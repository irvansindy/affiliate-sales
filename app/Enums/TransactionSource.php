<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionSource: string
{
    case Webhook = 'webhook';
    case Manual = 'manual';
}
