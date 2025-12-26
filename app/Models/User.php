<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable; // important for auth
use Illuminate\Notifications\Notifiable;
use App\Traits\LogsActivity;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Auth\Passwords\CanResetPassword;
use App\Notifications\ResetPasswordNotification;

class User extends Authenticatable
{
    use HasApiTokens ,HasFactory, Notifiable;
    use LogsActivity;
    
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'user_type',
        'preferences',
        'remember_token',
        'email_verified_at',

    ];
    public function client()
    {
        return $this->hasOne(Client::class);
    }

    // Fields you allow to be mass-assigned

    // Hide sensitive fields when returning JSON
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // Cast fields if needed
    protected $casts = [
        'email_verified_at' => 'datetime',
        'preferences' => 'array',  
    ];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'user_permissions');
    }

    /**
     * Get the team user record associated with the user.
     */
    public function teamUser()
    {
        return $this->hasOne(TeamUser::class, 'user_id');
    }
    public function intern()
    {
        return $this->hasOne(Intern::class, 'user_id');
    }

    /**
     * Get the user's department.
     */
    public function getDepartmentAttribute()
    {
        return $this->teamUser ? $this->teamUser->department : null;
    }

    /**
     * Get the user's position.
     */
    public function getPosteAttribute()
    {
        return $this->teamUser ? $this->teamUser->poste : null;
    }


    public function files()
    {
        return $this->morphMany(File::class, 'fileable');
    }
       public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
    public function certifications()
    {
        return $this->morphMany(Certification::class, 'owner');
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
