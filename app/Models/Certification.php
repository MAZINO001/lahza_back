<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Certification extends Model
{
    protected $fillable = [
        'owner_type',
        'owner_id',
        'title',
        'description',
        'source_type',
        'file_path',
        'url',
        'preview_image',
        'issued_by',
        'issued_at',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
    ];

    // Optional polymorphic relation
    public function owner()
    {
        return $this->morphTo();
    }
}
