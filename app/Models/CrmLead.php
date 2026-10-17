<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CrmLeadStage;
use Database\Factories\CrmLeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmLead extends Model
{
    /** @use HasFactory<CrmLeadFactory> */
    use HasFactory;

    protected $fillable = ['affiliate_id', 'contact_id', 'product_id', 'stage', 'notes'];

    protected $attributes = ['stage' => 'new'];

    protected function casts(): array
    {
        return ['stage' => CrmLeadStage::class];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'contact_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'lead_id');
    }
}
