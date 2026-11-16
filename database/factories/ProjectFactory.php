<?php

namespace Database\Factories;

use App\Enums\ProjectType;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);

        return [
            'title' => Str::title($title),
            'slug' => Str::slug($title),
            'summary' => fake()->sentence(),
            'description' => fake()->paragraphs(2, true),
            'type' => fake()->randomElement(ProjectType::cases()),
            'status' => 'active',
            'url' => fake()->optional()->url(),
            'github_url' => null,
            'demo_url' => null,
            'start_date' => fake()->dateTimeBetween('-3 years', '-1 year'),
            'end_date' => null,
            'is_ongoing' => true,
            'is_featured' => false,
            'sort_order' => fake()->numberBetween(0, 100),
            'is_published' => true,
        ];
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => ['is_featured' => true]);
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => ['is_published' => false]);
    }
}
