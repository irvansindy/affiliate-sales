<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Commission extends Model
{
    use SoftDeletes;

    // 'status' sengaja tidak fillable: transisi hanya lewat Action yang menulis audit log.
    protected $fillable = [
        'transaction_id', 'affiliate_id', 'base_amount', 'rate', 'amount',
        'approvable_at', 'approved_at', 'payout_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => CommissionStatus::class,
            'rate' => 'decimal:2',
            'approvable_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }
}
