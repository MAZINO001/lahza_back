<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'interval',
        'price',
        'currency',
        'stripe_price_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    /**
     * Get the plan that owns this price.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get all subscriptions using this price.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Scope a query to only include monthly prices.
     */
    public function scopeMonthly($query)
    {
        return $query->where('interval', 'monthly');
    }

    /**
     * Scope a query to only include yearly prices.
     */
    public function scopeYearly($query)
    {
        return $query->where('interval', 'yearly');
    }

    /**
     * Get formatted price.
     */
    public function getFormattedPriceAttribute(): string
    {
        return $this->currency . ' ' . number_format($this->price, 2);
    }
}
