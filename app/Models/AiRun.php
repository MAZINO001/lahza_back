<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'run_type',
        'input_data',
        'output_data',
        'status',
        'ran_at',
    ];

    protected $casts = [
        'input_data' => 'array',
        'output_data' => 'array',
        'ran_at' => 'datetime',
    ];
}
