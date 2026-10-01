<?php

declare(strict_types=1);

namespace App\Enums;

enum PayoutStatus: string
{
    case Processing = 'processing';
    case Paid = 'paid';
    case Failed = 'failed';
}
