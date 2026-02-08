<?php

namespace Database\Factories;

use App\Models\PhotoCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PhotoCategoryFactory extends Factory
{
    protected $model = PhotoCategory::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Landscapes',
            'Portraits',
            'Wildlife',
            'Street',
            'Architecture',
            'Nature',
            'Travel',
            'Night',
            'Macro',
            'Abstract',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->optional()->sentence(),
            'color' => fake()->optional()->hexColor(),
            'icon' => null,
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
