<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionStatus: string
{
    case Paid = 'paid';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';
}
