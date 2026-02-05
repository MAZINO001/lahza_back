<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Quotes;
use App\Models\User;
use App\Models\Invoice;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasFactory;
    use LogsActivity;
    protected $fillable = [
        'invoice_id',
        'client_id',
        'stripe_session_id',
        'stripe_payment_intent_id',
        'total',
        'amount',
        'currency',
        'status',
        'payment_method',
        'payment_url',
        'updated_at',
        'percentage'
    ];

    // Relationships
    public function quotes()
    {
        return $this->belongsTo(Quotes::class);
    }

    // public function user()
    // {
    //     return $this->belongsTo(User::class);
    // }

     public function user()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

   public function invoice()
{
    return $this->belongsTo(Invoice::class, 'invoice_id');
}

public function files()
{
    return $this->morphMany(File::class, 'fileable');
}
   public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
  
    public function allocations(): HasMany
{
    return $this->hasMany(PaymentAllocation::class);
}

/**
 * Get subscription allocations only.
 */
public function subscriptionAllocations(): HasMany
{
    return $this->allocations()->where('allocatable_type', 'subscription');
}

/**
 * Get invoice (services) allocations only.
 */
public function invoiceAllocations(): HasMany
{
    return $this->allocations()->where('allocatable_type', 'invoice');
}

/**
 * Check if this payment has subscription allocations.
 */
public function hasSubscriptionAllocations(): bool
{
    return $this->subscriptionAllocations()->exists();
}

/**
 * Get total allocated to subscriptions.
 */
public function getSubscriptionAllocationTotal(): float
{
    return $this->subscriptionAllocations()->sum('amount');
}

/**
 * Get total allocated to invoice services.
 */
public function getInvoiceAllocationTotal(): float
{
    return $this->invoiceAllocations()->sum('amount');
}
}
