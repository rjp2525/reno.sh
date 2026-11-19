<?php

use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

$markdown = "Go workers for **Laravel** queues.\n\n## Install\n\n```bash\ngo get github.com/rjp2525/laravel-queue-go/v2\n```";

it('renders markdown blocks to HTML on save and mirrors plain text into description', function () use ($markdown) {
    $project = Project::factory()->create(['content' => [['type' => 'markdown', 'data' => ['markdown' => $markdown]]]]);

    expect($project->content[0]['data']['content'])->toContain('<strong>Laravel</strong>')->toContain('class="language-bash"')
        ->and($project->description)->toContain('Go workers for Laravel queues.')->not->toContain('**');
});

it('turns a plain description into a markdown block', function () {
    $project = Project::factory()->create(['description' => 'Plain *text* description.', 'content' => null]);

    expect($project->content)->toHaveCount(1)
        ->and($project->content[0]['type'])->toBe('markdown')
        ->and($project->content[0]['data']['content'])->toContain('<em>text</em>');
});

it('sends blocks to the project page and the API', function () use ($markdown) {
    $project = Project::factory()->create(['content' => [['type' => 'markdown', 'data' => ['markdown' => $markdown]]]]);

    $this->get("/projects/{$project->slug}")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('ProjectShow')
            ->where('project.content.0.type', 'markdown')
            ->where('project.content.0.data.content', fn ($html) => str_contains($html, '<h2>Install</h2>'))
            ->etc());

    $this->getJson("/api/v1/projects/{$project->slug}")->assertOk()
        ->assertJsonPath('data.blocks.0.type', 'markdown')
        ->assertJson(fn ($json) => $json
            ->where('data.markdown', fn ($md) => str_contains($md, '**Laravel**') && str_contains($md, '```bash'))
            ->where('data.description_html', fn ($html) => str_contains($html, '<strong>Laravel</strong>'))
            ->etc());
});

it('renders project blocks in the terminal', function () use ($markdown) {
    $project = Project::factory()->create(['content' => [['type' => 'markdown', 'data' => ['markdown' => $markdown]]]]);

    $out = $this->get("/projects/{$project->slug}", ['User-Agent' => 'curl/8.7.1'])->assertOk()->getContent();

    expect(preg_replace('/\e\[[0-9;]*m/', '', $out))->toContain('Install')->toContain('go get github.com/rjp2525/laravel-queue-go/v2');
});

it('backfills existing descriptions into content', function () {
    $migration = require database_path('migrations/2026_11_19_000000_add_content_to_projects_table.php');
    $migration->down();

    $id = (string) Str::ulid();
    DB::table('projects')->insert([
        'id' => $id, 'title' => 'Old', 'slug' => 'old', 'type' => 'personal', 'status' => 'active',
        'description' => "Line one.\n\nLine **two**.", 'created_at' => now(), 'updated_at' => now(),
    ]);

    $migration->up();

    $content = json_decode(DB::table('projects')->where('id', $id)->value('content'), true);
    expect($content[0]['type'])->toBe('markdown')
        ->and($content[0]['data']['content'])->toContain('<strong>two</strong>');
});
