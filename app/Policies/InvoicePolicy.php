<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InvoicePolicy
{
    use HandlesAuthorization;

    public function view(User $user, Invoice $invoice)
    {
        return $user->role === 'admin' || $user->id === $invoice->client_id;
    }

    public function update(User $user, Invoice $invoice)
    {
        return $user->role === 'admin' || $user->id === $invoice->client_id;
    }

    public function delete(User $user, Invoice $invoice)
    {
        return $user->role === 'admin' || $user->id === $invoice->client_id;
    }

    public function create(User $user)
    {
        return $user->role === 'admin';
    }
}
