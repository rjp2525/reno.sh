<?php

use App\Filament\Resources\PhotoCategoryResource\Pages\ManagePhotoCategories;
use App\Filament\Resources\PostResource\Pages\CreatePost;
use App\Filament\Resources\ProjectResource\Pages\CreateProject;
use App\Filament\Resources\ProjectResource\Pages\EditProject;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    config(['site.admin_emails' => ['admin@example.com']]);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.com']));
});

it('fills the slug from the title as you type', function () {
    Livewire::test(CreateProject::class)
        ->fillForm(['title' => 'Laravel Queue Go'])
        ->assertFormSet(['slug' => 'laravel-queue-go'])
        ->fillForm(['title' => 'Laravel Queue Go v2'])
        ->assertFormSet(['slug' => 'laravel-queue-go-v2']);
});

it('stops following the title once the slug is edited by hand', function () {
    Livewire::test(CreateProject::class)
        ->fillForm(['title' => 'Apex'])
        ->fillForm(['slug' => 'apex-cad'])
        ->fillForm(['title' => 'Apex Public Safety Suite'])
        ->assertFormSet(['slug' => 'apex-cad']);
});

it('makes taken slugs unique, counting soft-deleted rows', function () {
    Post::create(['title' => 'Hello', 'slug' => 'hello', 'status' => 'draft']);
    Post::create(['title' => 'Hello', 'slug' => 'hello-2', 'status' => 'draft'])->delete();

    Livewire::test(CreatePost::class)
        ->fillForm(['title' => 'Hello'])
        ->assertFormSet(['slug' => 'hello-3'])
        ->fillForm(['title' => 'Hello world'])
        ->assertFormSet(['slug' => 'hello-world']);
});

it('keeps an existing custom slug when the title changes on edit, but follows a generated one', function () {
    $custom = Project::factory()->create(['title' => 'Old', 'slug' => 'my-own-slug']);
    Livewire::test(EditProject::class, ['record' => $custom->getRouteKey()])
        ->fillForm(['title' => 'New name'])
        ->assertFormSet(['slug' => 'my-own-slug']);

    $generated = Project::factory()->create(['title' => 'Old title', 'slug' => 'old-title']);
    Livewire::test(EditProject::class, ['record' => $generated->getRouteKey()])
        ->fillForm(['title' => 'Better title'])
        ->assertFormSet(['slug' => 'better-title']);
});

it('fills the slug in the photo category modal and regenerates on demand', function () {
    $page = Livewire::test(ManagePhotoCategories::class)->mountAction('create');
    $modal = $page->instance()->getMountedActionSchemaName();

    $page->fillForm(['name' => 'Night Sky'])
        ->assertFormSet(['slug' => 'night-sky'], $modal)
        ->fillForm(['slug' => 'whatever'])
        ->fillForm(['name' => 'Night Sky Again'])
        ->assertFormSet(['slug' => 'whatever'], $modal)
        ->callAction(TestAction::make('regenerateSlug')->schemaComponent('slug', schema: $modal))
        ->assertFormSet(['slug' => 'night-sky-again'], $modal);
});
