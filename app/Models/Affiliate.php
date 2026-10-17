<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AffiliateStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Affiliate extends Model
{
    protected $fillable = [
        'user_id', 'referral_code', 'status', 'phone',
        'bank_name', 'bank_account_name', 'bank_account_number', 'approved_at',
    ];

    protected $hidden = ['bank_account_number'];

    protected function casts(): array
    {
        return [
            'status' => AffiliateStatus::class,
            'bank_account_number' => 'encrypted',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Affiliate $affiliate) {
            if (! $affiliate->referral_code) {
                do {
                    $code = Str::upper(Str::random(6));
                } while (static::where('referral_code', $code)->exists());

                $affiliate->referral_code = $code;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(ReferralClick::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CrmContact::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(CrmLead::class);
    }

    public function isActive(): bool
    {
        return $this->status === AffiliateStatus::Active;
    }
}
