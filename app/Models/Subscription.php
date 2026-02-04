<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'plan_id',
        'plan_price_id',
        'status',
        'started_at',
        'ends_at',
        'next_billing_at',
        'cancelled_at',
        'stripe_subscription_id',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ends_at' => 'datetime',
        'next_billing_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Get the client that owns this subscription.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the plan for this subscription.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the price for this subscription.
     */
    public function planPrice(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class);
    }

    /**
     * Get all custom field values for this subscription.
     */
    public function customFieldValues(): HasMany
    {
        return $this->hasMany(SubscriptionCustomFieldValue::class);
    }

    /**
     * Get all invoices for this subscription.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Check if subscription is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if subscription is on trial.
     */
    public function onTrial(): bool
    {
        return $this->status === 'trial';
    }

    /**
     * Check if subscription is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if subscription is past due.
     */
    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    /**
     * Check if subscription is expired.
     */
    public function isExpired(): bool
    {
        return $this->status === 'expired';
    }

    /**
     * Get custom field value by key.
     */
    public function getCustomFieldValue(string $key)
    {
        $customFieldValue = $this->customFieldValues()
            ->whereHas('customField', function ($query) use ($key) {
                $query->where('key', $key);
            })
            ->with('customField')
            ->first();

        if (!$customFieldValue) {
            return null;
        }

        return $customFieldValue->customField->castValue($customFieldValue->value);
    }

    /**
     * Scope a query to only include active subscriptions.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include trial subscriptions.
     */
    public function scopeTrial($query)
    {
        return $query->where('status', 'trial');
    }

    /**
     * Scope a query to only include cancelled subscriptions.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope a query to only include past due subscriptions.
     */
    public function scopePastDue($query)
    {
        return $query->where('status', 'past_due');
    }

    /**
     * Scope a query to only include expired subscriptions.
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }
      public function invoiceSubscriptions()
    {
        return $this->hasMany(InvoiceSubscription::class);
    }
}
