<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'user_role',
        'action',
        'table_name',
        'record_id',
        'old_values',
        'new_values',
        'changes',
        'ip_address',
        'ip_country',
        'user_agent',
        'device',
        'url',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changes' => 'array',
    ];

    /**
     * Relationship with the User who performed the action.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
