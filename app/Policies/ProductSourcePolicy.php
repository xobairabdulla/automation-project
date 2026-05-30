<?php

namespace App\Policies;

use App\Models\ProductSource;
use App\Models\User;

class ProductSourcePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProductSource $productSource): bool
    {
        return $productSource->user_id === $user->effectiveTenantId();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ProductSource $productSource): bool
    {
        return $productSource->user_id === $user->effectiveTenantId();
    }

    public function delete(User $user, ProductSource $productSource): bool
    {
        return $productSource->user_id === $user->effectiveTenantId();
    }
}
