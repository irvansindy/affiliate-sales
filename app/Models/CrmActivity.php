<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CrmActivityType;
use Database\Factories\CrmActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmActivity extends Model
{
    /** @use HasFactory<CrmActivityFactory> */
    use HasFactory;

    protected $fillable = ['lead_id', 'author_user_id', 'type', 'summary', 'occurred_at', 'due_at', 'completed_at'];

    protected function casts(): array
    {
        return [
            'type' => CrmActivityType::class,
            'occurred_at' => 'datetime',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
