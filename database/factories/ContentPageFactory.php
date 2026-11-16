<?php

namespace Database\Factories;

use App\Enums\ContentSection;
use App\Models\ContentPage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ContentPage>
 */
class ContentPageFactory extends Factory
{
    protected $model = ContentPage::class;

    public function definition(): array
    {
        $title = fake()->unique()->word();

        return [
            'section' => ContentSection::PERSONAL,
            'slug' => Str::slug($title),
            'title' => $title,
            'icon' => null,
            'content' => [
                ['type' => 'heading', 'data' => ['title' => Str::title($title), 'subtitle' => fake()->sentence(), 'level' => 'h2']],
                ['type' => 'rich-text', 'data' => ['content' => '<p>'.fake()->paragraph().'</p>']],
            ],
            'order' => 0,
            'is_published' => true,
        ];
    }

    public function hobbies(): static
    {
        return $this->state(fn (array $attributes) => ['section' => ContentSection::HOBBIES]);
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => ['is_published' => false]);
    }
}
