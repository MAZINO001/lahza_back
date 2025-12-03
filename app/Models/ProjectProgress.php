<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model; 

class ProjectProgress extends Model
{
      protected $fillable = [
        'team_id', 'project_id', 'task_id', 'accumlated_percentage'
    ];

    public function teamUser()
    {
        return $this->belongsTo(TeamUser::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
