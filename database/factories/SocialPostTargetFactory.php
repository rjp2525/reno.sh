<?php

namespace Database\Factories;

use App\Enums\SocialProvider;
use App\Enums\SocialTargetStatus;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialPostTarget>
 */
class SocialPostTargetFactory extends Factory
{
    protected $model = SocialPostTarget::class;

    public function definition(): array
    {
        return [
            'social_post_id' => SocialPost::factory(),
            'social_account_id' => SocialAccount::factory(),
            'body_override' => null,
            'status' => SocialTargetStatus::PENDING,
            'attempts' => 0,
        ];
    }

    /** Attach to a fresh account of the given network. */
    public function provider(SocialProvider $provider): static
    {
        return $this->state(fn () => [
            'social_account_id' => SocialAccount::factory()->provider($provider),
        ]);
    }

    public function failed(string $error = 'Something went wrong'): static
    {
        return $this->state(fn () => ['status' => SocialTargetStatus::FAILED, 'error' => $error]);
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => SocialTargetStatus::PUBLISHED,
            'provider_post_id' => '123',
            'published_at' => now(),
        ]);
    }
}
