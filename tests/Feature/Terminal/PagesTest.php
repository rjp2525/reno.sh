<?php

use App\Enums\ContentSection;
use App\Enums\PhotoProcessingStatus;
use App\Enums\PostStatus;
use App\Http\Middleware\ServeTerminal;
use App\Models\BlogCategory;
use App\Models\BlogSeries;
use App\Models\ContentPage;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Photo;
use App\Models\PhotoCategory;
use App\Models\Post;
use App\Models\PrintProduct;
use App\Models\Project;
use App\Models\Skill;
use App\Terminal\Text;

beforeEach(function () {
    app('router')->prependMiddlewareToGroup('web', ServeTerminal::class);

    $this->category = BlogCategory::factory()->create(['name' => 'Laravel', 'slug' => 'laravel', 'description' => 'All things Laravel.']);
    $this->series = BlogSeries::factory()->published()->create(['title' => 'Building reno.sh', 'slug' => 'building-reno-sh']);

    $this->post = Post::factory()->published()->inCategory($this->category)->create([
        'title' => 'Serving ANSI pages to curl from Laravel middleware',
        'slug' => 'ansi-pages',
        'excerpt' => 'Why your personal site should answer curl with something nicer than HTML soup.',
        'blog_series_id' => $this->series->id,
        'series_order' => 1,
        'reading_time' => 4,
        'published_at' => now()->subDay(),
        'content' => [
            ['type' => 'heading', 'data' => ['title' => 'Why bother', 'subtitle' => 'Because terminals are fun', 'level' => 'h2']],
            ['type' => 'rich-text', 'data' => ['content' => '<p>Read the <a href="https://laravel.com/docs/middleware">middleware docs</a> and <strong>ship it</strong> with <code>curl</code>.</p><ul><li>one<ul><li>nested</li></ul></li><li>two</li></ul>']],
            ['type' => 'callout', 'data' => ['type' => 'warning', 'title' => 'Careful', 'content' => '<p>Do not break browsers.</p>']],
            ['type' => 'code', 'data' => ['language' => 'php', 'filename' => 'routes/web.php', 'code' => "Route::get('/', HomepageController::class);\n// a very long comment line that keeps going and going well past any reasonable terminal width limit"]],
            ['type' => 'two-columns', 'data' => ['left' => '<p>Left column</p>', 'right' => '<p>Right column</p>']],
            ['type' => 'image', 'data' => ['image' => 'blog/images/cat.jpg', 'alt' => 'A cat at a terminal', 'caption' => 'Supervising']],
            ['type' => 'image-gallery', 'data' => ['images' => [['image' => 'https://example.com/a.jpg', 'alt' => 'First']]]],
            ['type' => 'divider', 'data' => []],
            ['type' => 'markdown', 'data' => ['markdown' => "## From markdown\n\nSome *markdown* text."]],
        ],
    ]);

    $this->uncategorized = Post::factory()->published()->create(['title' => 'An uncategorized note', 'slug' => 'loose-note', 'published_at' => now()->subDays(3)]);
    $this->draft = Post::factory()->inCategory($this->category)->create(['title' => 'Secret draft', 'slug' => 'secret-draft', 'status' => PostStatus::DRAFT]);
    $this->scheduled = Post::factory()->scheduled()->inCategory($this->category)->create(['title' => 'Future post', 'slug' => 'future-post']);

    $this->project = Project::factory()->featured()->create([
        'title' => 'reno.sh', 'slug' => 'reno-sh', 'summary' => 'This very website.',
        'description' => '<p>Built with <strong>Laravel</strong> and <a href="https://vuejs.org">Vue</a>.</p>',
        'github_url' => 'https://github.com/rjp2525/reno.sh',
    ]);
    $this->hiddenProject = Project::factory()->unpublished()->create(['title' => 'Hidden project', 'slug' => 'hidden-project']);

    $photoCategory = PhotoCategory::factory()->create(['name' => 'Landscapes', 'slug' => 'landscapes']);
    $this->photo = Photo::factory()->processed()->withCategory($photoCategory)->create([
        'title' => 'Maroon Bells at dawn', 'slug' => 'maroon-bells', 'description' => '<p>Cold morning, <em>worth it</em>.</p>',
        'width' => 6000, 'height' => 4000, 'prints_enabled' => true,
        'camera_body' => 'Sony A7 IV', 'lens' => '24-70mm f/2.8', 'aperture' => 'f/8', 'shutter_speed' => '1/125', 'focal_length' => '35mm', 'iso' => 100,
    ]);
    $this->hiddenPhoto = Photo::factory()->processed()->unpublished()->create(['title' => 'Hidden photo', 'slug' => 'hidden-photo']);
    $this->pendingPhoto = Photo::factory()->create(['title' => 'Pending photo', 'slug' => 'pending-photo', 'processing_status' => PhotoProcessingStatus::PENDING]);

    PrintProduct::create([
        'name' => 'Fine Art Print', 'type' => 'fine_art', 'sku' => 'GLOBAL-FAP-12X18', 'width_in' => 12, 'height_in' => 18,
        'price' => 6500, 'currency' => 'USD', 'min_dpi' => 150, 'is_active' => true, 'sort_order' => 1,
    ]);

    Experience::create(['title' => 'Lead Software Developer', 'company' => 'Clean Program', 'location' => 'Remote', 'start' => now()->subYears(3), 'end' => null, 'responsibilities' => ['Built things that shipped and kept shipping for a long time']]);
    Skill::create(['category' => 'backend', 'name' => 'Laravel', 'proficiency' => 'expert', 'started' => now()->subYears(10)]);
    Education::create(['school_name' => 'Southern New Hampshire University', 'degree' => 'BS, Computer Science', 'started' => now()->subYears(10), 'graduated' => now()->subYears(6)]);

    ContentPage::factory()->create(['section' => ContentSection::PERSONAL, 'slug' => 'bio', 'title' => 'Bio']);
    ContentPage::factory()->create(['section' => ContentSection::HOBBIES, 'slug' => 'overlanding', 'title' => 'Overlanding']);
    ContentPage::factory()->unpublished()->create(['section' => ContentSection::HOBBIES, 'slug' => 'secret-hobby', 'title' => 'Secret hobby']);
});

function terminal(string $uri, string $agent = 'curl/8.7.1')
{
    return test()->get($uri, ['User-Agent' => $agent]);
}

dataset('pages', [
    'home' => ['/', "Howdy there, I'm", 'Full Stack Software Engineer'],
    'about' => ['/about', 'Clean Program', '_about'],
    'professional' => ['/about/professional', 'Clean Program', 'Laravel'],
    'professional tab' => ['/about/professional/skills', 'backend', 'Laravel'],
    'personal' => ['/about/personal', 'bio', '_about / personal'],
    'hobbies' => ['/about/hobbies', 'overlanding', '_about / hobbies'],
    'projects' => ['/projects', 'reno.sh', 'This very website.'],
    'project' => ['/projects/reno-sh', 'This very website.', 'https://github.com/rjp2525/reno.sh'],
    'gallery' => ['/gallery', 'Maroon Bells at dawn', 'landscapes'],
    'gallery filtered' => ['/gallery?category=landscapes', 'Maroon Bells at dawn', 'in landscapes'],
    'photo' => ['/gallery/maroon-bells', 'Sony A7 IV', 'Fine Art Print'],
    'blog' => ['/blog', 'Serving ANSI pages', 'laravel (1)'],
    'blog category' => ['/blog/laravel', 'All things Laravel.', 'Serving ANSI pages'],
    'post' => ['/blog/laravel/ansi-pages', 'Why bother', 'middleware docs'],
    'uncategorized post' => ['/blog/post/loose-note', 'An uncategorized note', '_blog / post'],
    'series' => ['/blog/series/building-reno-sh', 'Building reno.sh', 'Serving ANSI pages'],
    'contact' => ['/contact', 'curl -X POST', '/api/v1/contact'],
]);

it('renders every page as text', function (string $uri, string $expect, string $alsoExpect) {
    $response = terminal($uri.(str_contains($uri, '?') ? '&' : '?').'nocolor');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('text/plain; charset=utf-8')
        ->and($response->getContent())
        ->toContain($expect)
        ->toContain($alsoExpect)
        ->toContain('ssh reno.sh -p 2222')
        ->toContain('_contact-me');
})->with('pages');

it('has no escape codes with ?nocolor', function (string $uri) {
    $body = terminal($uri.(str_contains($uri, '?') ? '&' : '?').'nocolor')->getContent();

    expect($body)->not->toContain("\e[");
})->with('pages');

it('colors pages by default', function (string $uri) {
    expect(terminal($uri)->getContent())->toContain("\e[");
})->with('pages');

it('never exceeds the requested width', function (string $uri, string $expect, string $alsoExpect, int $width) {
    $sep = str_contains($uri, '?') ? '&' : '?';
    $body = terminal($uri.$sep.'width='.$width)->getContent();

    foreach (explode("\n", $body) as $line) {
        expect(Text::width($line))->toBeLessThanOrEqual($width, "Line too wide at {$width} on {$uri}: ".Text::strip($line));
    }
})->with('pages')->with([40, 57, 80, 100, 160]);

it('clamps the width', function () {
    $narrow = terminal('/blog?nocolor&width=5')->getContent();
    $wide = terminal('/blog?nocolor&width=5000')->getContent();

    expect(max(array_map(Text::width(...), explode("\n", $narrow))))->toBe(40)
        ->and(max(array_map(Text::width(...), explode("\n", $wide))))->toBeLessThanOrEqual(160);
});

it('carries display options into suggested commands', function () {
    $body = terminal('/?nocolor&width=100')->getContent();

    expect($body)->toMatch("#curl '[^/\\s]+/blog\\?width=100&nocolor'#");
});

it('footnotes links at the end of a post', function () {
    $body = terminal('/blog/laravel/ansi-pages?nocolor')->getContent();

    expect($body)
        ->toContain('middleware docs[1]')
        ->toContain('[1] https://laravel.com/docs/middleware')
        ->toContain('┃ ! Careful')
        ->toContain('┌ routes/web.php · php')
        ->toContain('[1] https://laravel.com/docs/middleware');
});

it('lists the series and keeps reading links on a post', function () {
    $body = terminal('/blog/laravel/ansi-pages?nocolor')->getContent();

    expect($body)->toContain('series: Building reno.sh')->toContain('you are here')->toContain('/blog/post/loose-note')->toContain('/blog/series/building-reno-sh');
});

it('shows print options with prices and the order url', function () {
    $body = terminal('/gallery/maroon-bells?nocolor')->getContent();

    expect($body)->toContain('Fine Art Print')->toContain('12×18 in')->toContain('$65.00')
        ->toContain('order a print on the web')->toContain('/gallery/maroon-bells')
        ->toContain('Sony A7 IV')->toContain('f/8')->toContain('1/125');
});

it('hides unpublished content from listings', function () {
    expect(terminal('/blog?nocolor')->getContent())->not->toContain('Secret draft')->not->toContain('Future post')
        ->and(terminal('/projects?nocolor')->getContent())->not->toContain('Hidden project')
        ->and(terminal('/gallery?nocolor')->getContent())->not->toContain('Hidden photo')->not->toContain('Pending photo')
        ->and(terminal('/about/hobbies?nocolor')->getContent())->not->toContain('Secret hobby');
});

it('404s unpublished or missing content with the terminal 404 page', function (string $uri) {
    $response = terminal($uri.'?nocolor');

    $response->assertNotFound();
    expect($response->headers->get('Content-Type'))->toBe('text/plain; charset=utf-8')
        ->and($response->getContent())->toContain('page not found')->not->toContain('Secret draft');
})->with([
    'draft post' => '/blog/laravel/secret-draft',
    'scheduled post' => '/blog/laravel/future-post',
    'draft post, uncategorized url' => '/blog/post/secret-draft',
    'post under the wrong category' => '/blog/nope/ansi-pages',
    'missing post' => '/blog/laravel/missing',
    'missing category' => '/blog/missing-category',
    'unpublished project' => '/projects/hidden-project',
    'missing project' => '/projects/nope',
    'unpublished photo' => '/gallery/hidden-photo',
    'unprocessed photo' => '/gallery/pending-photo',
    'missing series' => '/blog/series/nope',
]);

it('404s unpublished series', function () {
    BlogSeries::factory()->create(['slug' => 'draft-series', 'is_published' => false]);

    terminal('/blog/series/draft-series?nocolor')->assertNotFound();
});

it('renders a moved post category url as 404 rather than leaking it', function () {
    $other = BlogCategory::factory()->create(['slug' => 'other']);

    terminal('/blog/other/ansi-pages?nocolor')->assertNotFound();
    expect($other->exists)->toBeTrue();
});
