<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pack extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get all plans for this pack.
     */
    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    /**
     * Get only active plans.
     */
    public function activePlans(): HasMany
    {
        return $this->plans()->where('is_active', true);
    }

    /**
     * Scope a query to only include active packs.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
