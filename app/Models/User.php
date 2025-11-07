<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable; // important for auth
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    // Fields you allow to be mass-assigned
    protected $fillable = [
        'name',
        'email',
        'password',
        'role', 
        'user_type',

    ];

    // Hide sensitive fields when returning JSON
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // Cast fields if needed
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'user_permissions');
    }

    public function teams()
    {
        return $this->belongsToMany(TeamUser::class, 'team_users')
                    ->withPivot('poste')
                    ->withTimestamps();
    }

    public function histories()
    {
        return $this->hasMany(History::class);
    }

    public function files()
    {
        return $this->hasMany(File::class);
    }
}
