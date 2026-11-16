<?php

namespace App\Models;

use App\Enums\SocialProvider;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A page/profile we can publish as. Credentials are encrypted at rest.
 *
 * @property SocialProvider $provider
 * @property array<string, mixed> $credentials
 */
class SocialAccount extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'provider', 'provider_id', 'name', 'username', 'avatar_url', 'credentials',
        'token_expires_at', 'is_active', 'needs_reauth', 'last_error', 'meta',
    ];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'provider' => SocialProvider::class,
            'credentials' => 'encrypted:array',
            'token_expires_at' => 'datetime',
            'is_active' => 'boolean',
            'needs_reauth' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function targets(): HasMany
    {
        return $this->hasMany(SocialPostTarget::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('needs_reauth', false);
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials, $key, $default);
    }

    public function tokenExpiresSoon(int $minutes = 10): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->lte(now()->addMinutes($minutes));
    }

    public function displayName(): string
    {
        return $this->provider->getLabel().' · '.($this->username ? '@'.ltrim($this->username, '@') : $this->name);
    }
}
