<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseType;
use App\Enums\SalesSubmissionStatus;
use Database\Factories\SalesSubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesSubmission extends Model
{
    /** @use HasFactory<SalesSubmissionFactory> */
    use HasFactory;

    protected $fillable = ['affiliate_id', 'product_id', 'purchase_type', 'customer_name', 'customer_email', 'claimed_ref', 'proof_path'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['purchase_type' => PurchaseType::class, 'status' => SalesSubmissionStatus::class, 'reviewed_at' => 'datetime'];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
