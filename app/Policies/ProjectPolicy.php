<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function view(User $user, Project $project)
{
    return $user->role === 'admin' || $project->client_id === $user->id;
}

public function update(User $user, Project $project)
{
    return $user->role === 'admin';
}

}
