<?php

namespace App\Http\Resources;

use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SocialAccount */
class SocialAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'provider' => $this->provider->value,
            'name' => $this->name,
            'username' => $this->username,
            'avatar_url' => $this->avatar_url,
            'is_active' => (bool) $this->is_active,
            'needs_reauth' => (bool) $this->needs_reauth,
            'limits' => $this->provider->limits(),
        ];
    }
}
