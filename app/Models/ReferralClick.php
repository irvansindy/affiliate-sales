<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralClick extends Model
{
    public $timestamps = false; // hanya clicked_at

    protected $fillable = ['affiliate_id', 'product_id', 'ip_hash', 'user_agent', 'clicked_at'];

    protected function casts(): array
    {
        return ['clicked_at' => 'datetime'];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
