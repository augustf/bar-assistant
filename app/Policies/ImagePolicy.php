<?php

declare(strict_types=1);

namespace Kami\Cocktail\Policies;

use Kami\Cocktail\Models\User;
use Kami\Cocktail\Models\Image;
use Illuminate\Auth\Access\HandlesAuthorization;

class ImagePolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): bool|null
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function edit(User $user, Image $image): bool
    {
        return $user->id === $image->user_id;
    }

    public function delete(User $user, Image $image): bool
    {
        return $user->id === $image->user_id;
    }
}
