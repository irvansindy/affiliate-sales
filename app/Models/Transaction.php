<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseType;
use App\Enums\SaleType;
use App\Enums\TransactionSource;
use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'external_ref', 'product_id', 'affiliate_id',
        'source', 'purchase_type', 'sale_type', 'subscription_plan', 'subscription_cycle',
        'customer_name', 'customer_email',
        'base_amount', 'discount_amount', 'tax_amount', 'total_paid',
        'status', 'paid_at', 'refund_amount', 'refunded_at',
        'needs_review', 'review_note',
    ];

    protected function casts(): array
    {
        return [
            'source' => TransactionSource::class,
            'purchase_type' => PurchaseType::class,
            'sale_type' => SaleType::class,
            'status' => TransactionStatus::class,
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'needs_review' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function commission(): HasOne
    {
        return $this->hasOne(Commission::class);
    }
}
