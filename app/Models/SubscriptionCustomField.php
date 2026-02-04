<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionCustomField extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'key',
        'label',
        'type',
        'default_value',
        'required',
    ];

    protected $casts = [
        'required' => 'boolean',
    ];

    /**
     * Get the plan that owns this custom field.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get all values for this custom field.
     */
    public function values(): HasMany
    {
        return $this->hasMany(SubscriptionCustomFieldValue::class, 'custom_field_id');
    }

    /**
     * Cast the value based on the field type.
     */
    public function castValue($value)
{
    return match ($this->type) {
        'number'  => $value !== null ? (int) $value : null,
        'boolean' => $value === null ? null : filter_var($value, FILTER_VALIDATE_BOOLEAN),
        'json'    => $value ? json_decode($value, true) : [],
        default   => (string) $value,
    };
}


    /**
     * Prepare value for storage.
     */
    public function prepareValue($value): string
    {
        return match ($this->type) {
            'json' => json_encode($value),
            'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };
    }
}
