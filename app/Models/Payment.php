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
         'quote_id',
        'client_id',
        'stripe_session_id',
        'stripe_payment_intent_id',
        'total',
        'amount',
        'currency',
        'status',
        'payment_method'
    ];

    // Relationships
    public function quotes()
    {
        return $this->belongsTo(Quotes::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }
}
