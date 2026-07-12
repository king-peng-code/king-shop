<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Domain\ExternalUser\Entities\ExternalUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ExternalUser */
class ExternalUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ExternalUser $user */
        $user = $this->resource;

        return [
            'id' => $user->id,
            'provider' => $user->provider->value,
            'external_id' => $user->externalId,
            'name' => $user->name,
            'phone' => $user->phone,
            'tags' => $user->tags,
        ];
    }
}
