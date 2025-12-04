<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\LogsActivity;
class TeamUser extends Model
{
    use LogsActivity;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'team_users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',   // needed for your registration logic
        'department',// department information
        'poste',     // position/role
    ];

    /**
     * Get the user that owns the team user record.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function assignments()
{
    return $this->hasMany(ProjectAssignment::class, 'team_id');
}
}
