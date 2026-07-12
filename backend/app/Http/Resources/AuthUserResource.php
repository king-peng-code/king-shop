<?php

namespace App\Http\Resources;

use App\Domain\Identity\Entities\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class AuthUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'role' => $user->role->value,
            'status' => $user->status->value,
            'avatar' => $user->avatar,
            'must_change_password' => $user->mustChangePassword,
        ];
    }
}
