<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAllocation extends Model
{
      protected $fillable = [
        'payment_id',
        'invoice_subscription_id',
        'allocatable_type', // 'invoice' or 'subscription'
        'amount',
    ];
    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoiceSubscription()
    {
        return $this->belongsTo(InvoiceSubscription::class);
    }
}
