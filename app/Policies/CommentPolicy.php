<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CommentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
public function view(User $user, Comment $comment)
{
    return true; // anyone authenticated can see
}

public function create(User $user, Comment $comment)
{
    return $user->role === 'admin' || $user->role === 'client';
}

public function delete(User $user, Comment $comment)
{
    return $user->role === 'admin' || $comment->user_id === $user->id;
}

}
