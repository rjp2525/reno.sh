<?php

use App\Filament\Resources\PhotoCategoryResource\Pages\ManagePhotoCategories;
use App\Models\BlogCategory;
use App\Models\PhotoCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('stores and reads colors with a leading hash', function () {
    $category = PhotoCategory::create(['name' => 'Astro', 'slug' => 'astro', 'color' => '4338CA']);

    expect(DB::table('photo_categories')->where('id', $category->id)->value('color'))->toBe('#4338CA')
        ->and(BlogCategory::create(['name' => 'Go', 'slug' => 'go', 'color' => ' #15803d '])->color)->toBe('#15803d')
        ->and(BlogCategory::create(['name' => 'X', 'slug' => 'x', 'color' => ''])->color)->toBeNull();

    // Legacy rows written before the cast still read back as valid CSS.
    DB::table('photo_categories')->where('id', $category->id)->update(['color' => '1E293B']);
    expect($category->fresh()->color)->toBe('#1E293B');
});

it('shows the swatch for a color entered without a hash and rejects non-colors', function () {
    config(['site.admin_emails' => ['admin@example.com']]);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.com']));

    Livewire::test(ManagePhotoCategories::class)
        ->callAction('create', ['name' => 'Night', 'slug' => 'night', 'color' => '1E293B', 'sort_order' => 0, 'is_active' => true])
        ->assertHasNoActionErrors()
        ->assertSeeHtml('background-color: #1E293B');

    Livewire::test(ManagePhotoCategories::class)
        ->callAction('create', ['name' => 'Bad', 'slug' => 'bad', 'color' => 'greenish', 'sort_order' => 0, 'is_active' => true])
        ->assertHasActionErrors(['color']);
});

it('backfills stored colors missing the hash', function () {
    $id = PhotoCategory::create(['name' => 'Nature', 'slug' => 'nature'])->id;
    DB::table('photo_categories')->where('id', $id)->update(['color' => '65A30D']);

    (require database_path('migrations/2026_11_19_200000_add_hash_to_stored_hex_colors.php'))->up();

    expect(DB::table('photo_categories')->where('id', $id)->value('color'))->toBe('#65A30D');
});
