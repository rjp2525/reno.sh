<?php

namespace Database\Factories;

use App\Enums\SocialProvider;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    protected $model = SocialAccount::class;

    public function definition(): array
    {
        return [
            'provider' => SocialProvider::MASTODON,
            'provider_id' => (string) fake()->unique()->numberBetween(1_000_000, 9_999_999),
            'name' => fake()->company(),
            'username' => fake()->userName(),
            'avatar_url' => null,
            'credentials' => ['instance_url' => 'https://mastodon.example', 'access_token' => 'masto-token'],
            'token_expires_at' => null,
            'is_active' => true,
            'needs_reauth' => false,
            'last_error' => null,
            'meta' => [],
        ];
    }

    public function provider(SocialProvider $provider): static
    {
        return match ($provider) {
            SocialProvider::FACEBOOK => $this->facebook(),
            SocialProvider::INSTAGRAM => $this->instagram(),
            SocialProvider::LINKEDIN => $this->linkedin(),
            SocialProvider::X => $this->x(),
            SocialProvider::THREADS => $this->threads(),
            SocialProvider::BLUESKY => $this->bluesky(),
            SocialProvider::MASTODON => $this->mastodon(),
        };
    }

    public function facebook(): static
    {
        return $this->state(fn () => [
            'provider' => SocialProvider::FACEBOOK,
            'provider_id' => '1000'.fake()->unique()->numberBetween(1000, 9999),
            'credentials' => ['access_token' => 'fb-page-token'],
        ]);
    }

    public function instagram(): static
    {
        return $this->state(fn () => [
            'provider' => SocialProvider::INSTAGRAM,
            'provider_id' => '1784'.fake()->unique()->numberBetween(1000, 9999),
            'credentials' => ['access_token' => 'ig-page-token'],
            'meta' => ['page_id' => '1000'.fake()->numberBetween(1000, 9999)],
        ]);
    }

    public function linkedin(): static
    {
        return $this->state(fn () => [
            'provider' => SocialProvider::LINKEDIN,
            'provider_id' => 'abc'.fake()->unique()->numberBetween(1000, 9999),
            'credentials' => ['access_token' => 'li-token'],
            'token_expires_at' => now()->addDays(50),
        ]);
    }

    public function linkedinOrganization(string $id = '123456'): static
    {
        return $this->linkedin()->state(fn () => [
            'provider_id' => "urn:li:organization:{$id}",
            'meta' => ['author_urn' => "urn:li:organization:{$id}", 'type' => 'organization'],
        ]);
    }

    public function x(): static
    {
        return $this->state(fn () => [
            'provider' => SocialProvider::X,
            'username' => 'renosh',
            'credentials' => ['access_token' => 'x-token', 'refresh_token' => 'x-refresh'],
            'token_expires_at' => now()->addHours(2),
        ]);
    }

    public function threads(): static
    {
        return $this->state(fn () => [
            'provider' => SocialProvider::THREADS,
            'credentials' => ['access_token' => 'threads-token'],
            'token_expires_at' => now()->addDays(50),
        ]);
    }

    public function bluesky(): static
    {
        return $this->state(fn () => [
            'provider' => SocialProvider::BLUESKY,
            'provider_id' => 'did:plc:'.fake()->unique()->lexify('????????????'),
            'username' => 'reno.bsky.social',
            'credentials' => ['handle' => 'reno.bsky.social', 'app_password' => 'abcd-efgh-ijkl-mnop'],
        ]);
    }

    public function mastodon(): static
    {
        return $this->state(fn () => [
            'provider' => SocialProvider::MASTODON,
            'credentials' => ['instance_url' => 'https://mastodon.example', 'access_token' => 'masto-token'],
        ]);
    }

    public function expiringSoon(): static
    {
        return $this->state(fn () => ['token_expires_at' => now()->addMinutes(5)]);
    }

    public function needsReauth(): static
    {
        return $this->state(fn () => ['needs_reauth' => true, 'last_error' => 'Token revoked']);
    }
}
