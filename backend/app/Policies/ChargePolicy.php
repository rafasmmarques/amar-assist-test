<?php

namespace App\Policies;

use App\Models\Charge;
use App\Models\User;

class ChargePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Charge $charge): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function pay(User $user, Charge $charge): bool
    {
        return true;
    }
}
