<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CrmContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmContact extends Model
{
    /** @use HasFactory<CrmContactFactory> */
    use HasFactory;

    protected $fillable = ['affiliate_id', 'name', 'email', 'phone', 'company'];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(CrmLead::class, 'contact_id');
    }
}
