<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Quotes;
use App\Models\User;
use App\Models\Invoice;
use App\Traits\LogsActivity;
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
     public function paymentAllocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
