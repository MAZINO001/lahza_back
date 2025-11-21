<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Quotes;
use App\Models\User;
use App\Models\Invoice; 
class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'quote_id',
        'user_id',
        'stripe_session_id',
        'stripe_payment_intent_id',
        'amount',
        'currency',
        'status'
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
