<?php

namespace Database\Factories;

use App\Enums\SocialPostStatus;
use App\Models\SocialPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialPost>
 */
class SocialPostFactory extends Factory
{
    protected $model = SocialPost::class;

    public function definition(): array
    {
        return [
            'body' => fake()->sentence(12),
            'link_url' => null,
            'media' => null,
            'status' => SocialPostStatus::DRAFT,
            'scheduled_at' => null,
            'published_at' => null,
        ];
    }

    public function scheduled(?\DateTimeInterface $at = null): static
    {
        return $this->state(fn () => [
            'status' => SocialPostStatus::SCHEDULED,
            'scheduled_at' => $at ?? now()->subMinute(),
        ]);
    }

    /** @param  list<string>  $paths  public-disk paths */
    public function withMedia(array $paths = ['social/photo.jpg'], string $mime = 'image/jpeg'): static
    {
        return $this->state(fn () => [
            'media' => array_map(fn ($path) => ['path' => $path, 'mime' => $mime], $paths),
        ]);
    }

    public function withLink(string $url = 'https://reno.sh/photos/sunset'): static
    {
        return $this->state(fn () => ['link_url' => $url]);
    }
}
