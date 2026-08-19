<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    /**
     * Determine if the user can view the contact.
     */
    public function view(User $user, Contact $contact): bool
    {
        return $user->id === $contact->user_id;
    }

    /**
     * Determine if the user can update the contact.
     */
    public function update(User $user, Contact $contact): bool
    {
        return $user->id === $contact->user_id;
    }

    /**
     * Determine if the user can delete the contact.
     */
    public function delete(User $user, Contact $contact): bool
    {
        return $user->id === $contact->user_id;
    }
}
