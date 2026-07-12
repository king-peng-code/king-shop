<?php

namespace App\Application\Identity\Logout;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\PersonalAccessToken;

class LogoutHandler
{
    public function handle(UserModel $user, ?PersonalAccessToken $accessToken = null): void
    {
        if ($accessToken !== null) {
            $accessToken->delete();

            return;
        }

        $user->currentAccessToken()?->delete();
    }
}
