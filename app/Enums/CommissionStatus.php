<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Paid = 'paid';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
