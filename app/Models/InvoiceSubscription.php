<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceSubscription extends Model
{
        protected $fillable = [
        'invoice_id',
        'plan_id',
        'subscription_id', // nullable, set after full payment
        'price_snapshot',
        'billing_cycle',   // 'monthly' or 'yearly'
        'quantity',
    ];
     public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class); // nullable until paid
    }

    public function paymentAllocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
