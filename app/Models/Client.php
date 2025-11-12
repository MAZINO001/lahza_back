<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class Client extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'company',
        'address',
        'phone',
        'city',
        'country',
        'currency',
        'client_type',
        'siren',
        'vat',
        'ice',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function quotes()
    {
        return $this->hasMany(Quotes::class);
    }
}
