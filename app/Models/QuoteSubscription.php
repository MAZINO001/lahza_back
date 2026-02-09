<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'quote_id',
        'plan_id',
        'price_snapshot',
        'billing_cycle',
    ];

    protected $casts = [
        'price_snapshot' => 'decimal:2',
    ];

    /**
     * Get the quote this subscription belongs to.
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quotes::class, 'quote_id');
    }

    /**
     * Get the plan for this quote subscription.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
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
}