<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionCustomFieldValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'custom_field_id',
        'value',
    ];

    /**
     * Get the subscription that owns this value.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the custom field definition.
     */
    public function customField(): BelongsTo
    {
        return $this->belongsTo(SubscriptionCustomField::class);
    }

    /**
     * Get the casted value based on field type.
     */
    public function getCastedValueAttribute()
    {
        if (!$this->customField) {
            return $this->value;
        }

        return $this->customField->castValue($this->value);
    }
}
