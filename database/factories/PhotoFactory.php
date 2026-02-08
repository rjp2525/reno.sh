<?php

namespace Database\Factories;

use App\Enums\PhotoProcessingStatus;
use App\Models\Photo;
use App\Models\PhotoCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PhotoFactory extends Factory
{
    protected $model = Photo::class;

    public function definition(): array
    {
        $title = fake()->sentence(3);
        $cameras = ['Canon EOS R5', 'Sony A7 IV', 'Nikon Z8', 'Fujifilm X-T5', 'Canon EOS 5D Mark IV'];
        $lenses = ['24-70mm f/2.8', '70-200mm f/2.8', '50mm f/1.4', '35mm f/1.8', '85mm f/1.2', '16-35mm f/4'];

        return [
            'title' => $title,
            'description' => fake()->optional()->paragraph(),
            'slug' => Str::slug($title).'-'.Str::random(6),
            'photo_category_id' => null,
            'original_path' => 'photos/originals/'.fake()->uuid().'.jpg',
            'web_path' => null,
            'thumbnail_path' => null,
            'iso' => fake()->randomElement([100, 200, 400, 800, 1600, 3200]),
            'aperture' => fake()->randomElement(['f/1.4', 'f/1.8', 'f/2.8', 'f/4', 'f/5.6', 'f/8', 'f/11']),
            'shutter_speed' => fake()->randomElement(['1/8000', '1/4000', '1/2000', '1/1000', '1/500', '1/250', '1/125', '1/60', '1/30']),
            'focal_length' => fake()->randomElement(['24mm', '35mm', '50mm', '85mm', '135mm', '200mm']),
            'camera_body' => fake()->randomElement($cameras),
            'lens' => fake()->randomElement($lenses),
            'gps_latitude' => fake()->optional()->latitude(),
            'gps_longitude' => fake()->optional()->longitude(),
            'taken_at' => fake()->optional()->dateTimeBetween('-2 years', 'now'),
            'width' => fake()->numberBetween(4000, 8000),
            'height' => fake()->numberBetween(2500, 6000),
            'file_size' => fake()->numberBetween(5000000, 50000000),
            'instagram_link' => null,
            'is_favorite' => fake()->boolean(20),
            'is_published' => true,
            'sort_order' => fake()->numberBetween(0, 100),
            'processing_status' => PhotoProcessingStatus::PENDING,
        ];
    }

    public function withCategory(?PhotoCategory $category = null): static
    {
        return $this->state(fn (array $attributes) => [
            'photo_category_id' => $category?->id ?? PhotoCategory::factory(),
        ]);
    }

    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'processing_status' => PhotoProcessingStatus::COMPLETED,
            'web_path' => 'photos/web/'.Str::random(20).'.jpg',
            'thumbnail_path' => 'photos/thumbnails/'.Str::random(20).'.jpg',
        ]);
    }

    public function favorite(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_favorite' => true,
        ]);
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'processing_status' => PhotoProcessingStatus::FAILED,
        ]);
    }
}
