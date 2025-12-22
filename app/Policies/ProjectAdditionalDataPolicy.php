<?php

namespace App\Policies;

use App\Models\ProjectAdditionalData;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProjectAdditionalDataPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ProjectAdditionalData $projectAdditionalData): bool
    {
        return $user->role === 'admin' || $user->clients->id === $projectAdditionalData->project->client_id;
    }

    /**
     * Determine whether the user can create models.
     */
public function create(User $user): bool
{
    return $user->role === 'client' || $user->role === 'admin'; 
}

public function update(User $user, ProjectAdditionalData $projectAdditionalData): bool
{
    // Ensure the client owns the project the data belongs to
    if($user->role === 'admin') {
        return true;
    }
    return $user->role === 'client' && $user->clients->id === $projectAdditionalData->project->client_id;
}
    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ProjectAdditionalData $projectAdditionalData): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ProjectAdditionalData $projectAdditionalData): bool
    {
        return $user->role === 'admin';
    }
}
