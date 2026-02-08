<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\BlogCategory;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $title = fake()->sentence();

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->paragraph(),
            'content' => [
                [
                    'type' => 'rich-text',
                    'data' => ['content' => '<p>'.fake()->paragraphs(3, true).'</p>'],
                ],
            ],
            'featured_image' => null,
            'blog_category_id' => null,
            'blog_series_id' => null,
            'series_order' => 0,
            'meta_title' => null,
            'meta_description' => null,
            'meta_keywords' => null,
            'og_image' => null,
            'status' => PostStatus::DRAFT,
            'published_at' => null,
            'reading_time' => 0,
            'is_featured' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostStatus::PUBLISHED,
            'published_at' => fake()->dateTimeBetween('-6 months'),
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    public function inCategory(BlogCategory $category): static
    {
        return $this->state(fn (array $attributes) => [
            'blog_category_id' => $category->id,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostStatus::SCHEDULED,
            'published_at' => fake()->dateTimeBetween('+1 hour', '+1 month'),
        ]);
    }
}
