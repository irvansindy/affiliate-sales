<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payout extends Model
{
    use SoftDeletes;

    protected $attributes = ['status' => 'processing'];

    // 'status' tidak fillable: hanya lewat Action (audit log).
    protected $fillable = ['affiliate_id', 'total_amount', 'bank_name', 'bank_account_name', 'bank_account_number', 'note'];

    protected $hidden = ['bank_name', 'bank_account_name', 'bank_account_number', 'proof_path'];

    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'paid_at' => 'datetime',
            'bank_name' => 'encrypted',
            'bank_account_name' => 'encrypted',
            'bank_account_number' => 'encrypted',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }
}
