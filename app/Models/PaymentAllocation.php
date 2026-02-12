<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'invoice_subscription_id',
        'allocatable_type',
        'amount',
        'paid_percentage',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_percentage' => 'decimal:2',
    ];

    /**
     * Get the payment this allocation belongs to.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the invoice subscription if allocatable_type is 'subscription'.
     */
    public function invoiceSubscription(): BelongsTo
    {
        return $this->belongsTo(InvoiceSubscription::class);
    }

    /**
     * Check if this allocation is for a subscription.
     */
    public function isSubscriptionAllocation(): bool
    {
        return $this->allocatable_type === 'subscription';
    }

    /**
     * Check if this allocation is for an invoice (services).
     */
    public function isInvoiceAllocation(): bool
    {
        return $this->allocatable_type === 'invoice';
    }

    /**
     * Scope to only subscription allocations.
     */
    public function scopeSubscriptions($query)
    {
        return $query->where('allocatable_type', 'subscription');
    }

    /**
     * Scope to only invoice (services) allocations.
     */
    public function scopeInvoices($query)
    {
        return $query->where('allocatable_type', 'invoice');
    }
}