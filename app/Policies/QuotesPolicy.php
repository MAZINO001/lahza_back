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
 public function view(User $user, Quotes $quotes)
{
    return $user->role === 'admin' || $user->id === $quotes->client_id;
}

public function update(User $user, Quotes $quotes)
{
    return $user->role === 'admin';
}

public function create(User $user)
{
    return $user->role === 'admin';
}

}
