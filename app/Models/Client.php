<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use LogsActivity;
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
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments()
    {
        return $this->hasManyThrough(Payment::class, Quotes::class);
    }
      public function files()
    {
        return $this->morphMany(File::class, 'fileable');
    }
       public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
