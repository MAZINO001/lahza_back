<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'pack_id',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the pack that owns this plan.
     */
    public function pack(): BelongsTo
    {
        return $this->belongsTo(Pack::class);
    }

    /**
     * Get all prices for this plan.
     */
    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class);
    }

    /**
     * Get the monthly price.
     */
    public function monthlyPrice(): HasMany
    {
        return $this->prices()->where('interval', 'monthly');
    }

    /**
     * Get the yearly price.
     */
    public function yearlyPrice(): HasMany
    {
        return $this->prices()->where('interval', 'yearly');
    }

    /**
     * Get all custom fields for this plan.
     */
    public function customFields(): HasMany
    {
        return $this->hasMany(SubscriptionCustomField::class);
    }

    /**
     * Get all subscriptions for this plan.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Scope a query to only include active plans.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
      public function invoiceSubscriptions()
    {
        return $this->hasMany(InvoiceSubscription::class);
    }

    public function quoteSubscriptions()
    {
        return $this->hasMany(QuoteSubscription::class);
    }
}
