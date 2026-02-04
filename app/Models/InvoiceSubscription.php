<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'plan_id',
        'subscription_id',
        'price_snapshot',
        'billing_cycle',
    ];

    protected $casts = [
        'price_snapshot' => 'decimal:2',
    ];

    /**
     * Get the invoice this subscription belongs to.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the plan for this invoice subscription.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the created subscription (once payment is completed).
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get payment allocations for this invoice subscription.
     */
    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * Check if this invoice subscription has been converted to an active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscription_id !== null;
    }

    /**
     * Get the plan price for this billing cycle.
     */
    public function getPlanPrice(): ?PlanPrice
    {
        return PlanPrice::where('plan_id', $this->plan_id)
            ->where('interval', $this->billing_cycle)
            ->first();
    }

    /**
     * Calculate total paid for this specific subscription.
     */
    public function getTotalPaid(): float
    {
        return $this->paymentAllocations()
            ->whereHas('payment', function ($query) {
                $query->where('status', 'paid');
            })
            ->sum('amount');
    }

    /**
     * Check if this subscription is fully paid.
     */
    public function isFullyPaid(): bool
    {
        return $this->getTotalPaid() >= $this->price_snapshot;
    }
}