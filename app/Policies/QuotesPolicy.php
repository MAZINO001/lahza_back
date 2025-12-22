<?php

namespace App\Policies;

use App\Models\Quotes;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class QuotesPolicy
{
    /**
     * Determine whether the user can view any models.
     */
public function viewAny(User $user): bool
{
    return $user->role === 'admin' || $user->role === 'client';
}
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Quotes $quotes): bool
    {
        return $user->role === 'admin' || $user->clients->id === $quotes->client_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Quotes $quotes): bool
    {
        return $user->role === 'admin';
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
    public function restore(User $user, Quotes $quotes): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Quotes $quotes): bool
    {
        return $user->role === 'admin';
    }
}
