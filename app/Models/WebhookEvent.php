<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WebhookEventStatus;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    protected $fillable = ['event_id', 'event', 'payload', 'status', 'processed_at', 'error'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => WebhookEventStatus::class,
            'processed_at' => 'datetime',
        ];
    }
}
