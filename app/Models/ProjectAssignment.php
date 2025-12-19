<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectAssignment extends Model
{
     protected $fillable = ['project_id', 'team_id', 'assigned_by'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function teamUser()
    {
        return $this->belongsTo(TeamUser::class,'team_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
