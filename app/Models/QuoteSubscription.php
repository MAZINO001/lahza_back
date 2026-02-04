<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuoteSubscription extends Model
{
       protected $fillable = [
        'quote_id',
        'plan_id',
        'price_snapshot',
        'billing_cycle', // 'monthly' or 'yearly'
        'quantity',
    ];
    public function quote()
    {
        return $this->belongsTo(Quotes::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
