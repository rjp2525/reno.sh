<?php

use App\Enums\ContentSection;
use App\Enums\SkillProficiency;
use App\Models\ContentPage;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Skill;
use App\Models\User;
use Database\Seeders\EducationSeeder;
use Database\Seeders\SkillSeeder;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config(['site.admin_emails' => ['admin@example.com']]);
    Sanctum::actingAs(User::factory()->create(['email' => 'admin@example.com']), ['admin']);
});

it('requires an admin token for every about endpoint', function () {
    app('auth')->forgetGuards();
    Sanctum::actingAs(User::factory()->create(['email' => 'fan@example.com']), ['admin']);

    foreach (['content-pages', 'experiences', 'education', 'skills'] as $resource) {
        $this->getJson("/api/v1/admin/{$resource}")->assertForbidden();
    }
});

describe('content pages', function () {
    it('creates a page from markdown with a generated slug at the end of its section', function () {
        ContentPage::factory()->create(['section' => ContentSection::HOBBIES, 'order' => 4]);

        $this->postJson('/api/v1/admin/content-pages', [
            'title' => 'Film Photography',
            'section' => 'hobbies',
            'markdown' => "Shooting **Portra 400** lately.\n\n## Cameras\n\n- Pentax 67",
        ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'film-photography')
            ->assertJsonPath('data.section', 'hobbies')
            ->assertJsonPath('data.order', 5)
            ->assertJsonPath('data.is_published', false)
            ->assertJsonPath('data.is_markdown', true)
            ->assertJsonPath('data.url', route('about.hobbies', 'film-photography'))
            ->assertJson(fn ($json) => $json->where('data.markdown', fn ($md) => str_contains($md, '**Portra 400**'))->etc());
    });

    it('keeps slugs unique within a section only', function () {
        ContentPage::factory()->create(['section' => ContentSection::PERSONAL, 'slug' => 'music', 'title' => 'Music']);

        $this->postJson('/api/v1/admin/content-pages', ['title' => 'Music', 'section' => 'personal'])
            ->assertCreated()->assertJsonPath('data.slug', 'music-2');
        $this->postJson('/api/v1/admin/content-pages', ['title' => 'Music', 'section' => 'hobbies'])
            ->assertCreated()->assertJsonPath('data.slug', 'music');
        $this->postJson('/api/v1/admin/content-pages', ['title' => 'X', 'section' => 'personal', 'slug' => 'music'])
            ->assertUnprocessable()->assertJsonValidationErrors('slug');
    });

    it('lists by section, including unpublished pages', function () {
        ContentPage::factory()->create(['title' => 'Bio', 'order' => 1, 'is_published' => false]);
        ContentPage::factory()->create(['title' => 'Family', 'order' => 0]);
        ContentPage::factory()->hobbies()->create(['title' => 'Hiking']);

        $this->getJson('/api/v1/admin/content-pages?section=personal')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Family')
            ->assertJsonPath('data.1.title', 'Bio');

        $this->getJson('/api/v1/admin/content-pages?section=nope')->assertUnprocessable();
    });

    it('updates only the fields sent and replaces content from markdown', function () {
        $page = ContentPage::factory()->create(['title' => 'Bio', 'is_published' => false]);

        $this->patchJson("/api/v1/admin/content-pages/{$page->id}", ['is_published' => true])
            ->assertOk()->assertJsonPath('data.is_published', true)->assertJsonPath('data.title', 'Bio');

        $this->patchJson("/api/v1/admin/content-pages/{$page->id}", ['markdown' => 'Just text.'])->assertOk();
        expect($page->fresh()->content)->toHaveCount(1)
            ->and($page->fresh()->content[0]['type'])->toBe('markdown');
    });

    it('reorders pages by the given ids', function () {
        [$a, $b, $c] = ContentPage::factory()->count(3)->sequence(fn ($s) => ['order' => $s->index])->create();

        $this->postJson('/api/v1/admin/content-pages/reorder', ['ids' => [(string) $c->id, (string) $a->id, (string) $b->id]])
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $c->id)
            ->assertJsonPath('data.2.id', (string) $b->id);

        expect([$c->fresh()->order, $a->fresh()->order, $b->fresh()->order])->toBe([0, 1, 2]);

        $this->postJson('/api/v1/admin/content-pages/reorder', ['ids' => ['999']])->assertUnprocessable();
    });

    it('deletes a page', function () {
        $page = ContentPage::factory()->create();

        $this->deleteJson("/api/v1/admin/content-pages/{$page->id}")->assertNoContent();
        expect(ContentPage::withoutGlobalScopes()->count())->toBe(0);
    });
});

describe('experiences', function () {
    it('creates, updates and deletes a role with date strings and responsibilities', function () {
        $id = $this->postJson('/api/v1/admin/experiences', [
            'title' => 'Staff Engineer',
            'company' => 'Acme',
            'location' => 'Remote',
            'start' => '2024-03-01',
            'responsibilities' => ['Led the platform team', 'Shipped the API'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.start', '2024-03-01')
            ->assertJsonPath('data.end', null)
            ->assertJsonPath('data.responsibilities.1', 'Shipped the API')
            ->json('data.id');

        $this->patchJson("/api/v1/admin/experiences/{$id}", ['end' => '2026-01-15'])
            ->assertOk()->assertJsonPath('data.end', '2026-01-15')->assertJsonPath('data.title', 'Staff Engineer');

        $this->patchJson("/api/v1/admin/experiences/{$id}", ['end' => '2020-01-01'])
            ->assertUnprocessable()->assertJsonValidationErrors('end');

        $this->patchJson("/api/v1/admin/experiences/{$id}", ['responsibilities' => ['ok', '']])
            ->assertUnprocessable()->assertJsonValidationErrors('responsibilities.1');

        $this->deleteJson("/api/v1/admin/experiences/{$id}")->assertNoContent();
        expect(Experience::count())->toBe(0);
    });

    it('lists current roles first', function () {
        Experience::create(['title' => 'Old', 'company' => 'A', 'start' => '2015-01-01', 'end' => '2018-01-01']);
        Experience::create(['title' => 'Now', 'company' => 'B', 'start' => '2022-01-01', 'end' => null]);
        Experience::create(['title' => 'Recent', 'company' => 'C', 'start' => '2018-01-01', 'end' => '2021-12-01']);

        $this->getJson('/api/v1/admin/experiences')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Now')
            ->assertJsonPath('data.1.title', 'Recent')
            ->assertJsonPath('data.2.title', 'Old');
    });
});

describe('education', function () {
    it('allows a degree in progress and validates string lists', function () {
        $id = $this->postJson('/api/v1/admin/education', [
            'school_name' => 'Night School',
            'degree' => 'MS, Computer Science',
            'started' => '2025-09-01',
            'achievements' => ["Dean's list"],
        ])
            ->assertCreated()
            ->assertJsonPath('data.graduated', null)
            ->assertJsonPath('data.achievements.0', "Dean's list")
            ->assertJsonPath('data.projects', [])
            ->json('data.id');

        $this->patchJson("/api/v1/admin/education/{$id}", ['graduated' => '2024-01-01'])
            ->assertUnprocessable()->assertJsonValidationErrors('graduated');

        $this->patchJson("/api/v1/admin/education/{$id}", ['graduated' => '2027-05-15', 'projects' => ['Thesis']])
            ->assertOk()->assertJsonPath('data.graduated', '2027-05-15')->assertJsonPath('data.projects.0', 'Thesis');

        $this->getJson('/api/v1/admin/education')->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson("/api/v1/admin/education/{$id}")->assertNoContent();
        expect(Education::count())->toBe(0);
    });
});

describe('skills', function () {
    it('lists skills with category and proficiency metadata', function () {
        Skill::create(['category' => 'Frontend', 'name' => 'Vue', 'proficiency' => SkillProficiency::EXPERT, 'started' => '2016-01-01']);
        Skill::create(['category' => 'Backend', 'name' => 'PHP', 'proficiency' => SkillProficiency::EXPERT, 'started' => '2010-01-01']);
        Skill::create(['category' => 'Backend', 'name' => 'Go', 'proficiency' => SkillProficiency::PROFICIENT, 'started' => '2020-01-01']);

        $this->getJson('/api/v1/admin/skills')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Go')
            ->assertJsonPath('data.0.level', 'Proficient')
            ->assertJsonPath('data.2.category', 'Frontend')
            ->assertJsonPath('meta.categories', ['Backend', 'Frontend'])
            ->assertJsonPath('meta.proficiencies.0', ['value' => 'beginner', 'label' => 'Beginner'])
            ->assertJsonCount(4, 'meta.proficiencies');
    });

    it('creates, updates and deletes a skill', function () {
        $id = $this->postJson('/api/v1/admin/skills', [
            'category' => 'Backend', 'name' => 'Rust', 'proficiency' => 'beginner', 'started' => '2025-06-01',
        ])->assertCreated()->assertJsonPath('data.started', '2025-06-01')->json('data.id');

        $this->patchJson("/api/v1/admin/skills/{$id}", ['proficiency' => 'intermediate'])
            ->assertOk()->assertJsonPath('data.proficiency', 'intermediate')->assertJsonPath('data.name', 'Rust');

        $this->patchJson("/api/v1/admin/skills/{$id}", ['proficiency' => 'wizard'])
            ->assertUnprocessable()->assertJsonValidationErrors('proficiency');

        $this->deleteJson("/api/v1/admin/skills/{$id}")->assertNoContent();
        expect(Skill::count())->toBe(0);
    });
});

it('can re-run the profile seeders without duplicating rows', function () {
    $this->seed(EducationSeeder::class);
    $this->seed(SkillSeeder::class);
    $counts = [Education::count(), Skill::count()];

    $this->seed(EducationSeeder::class);
    $this->seed(SkillSeeder::class);

    expect([Education::count(), Skill::count()])->toBe($counts);
});

it('sanitizes rich text and stores image paths when blocks come from the app', function () {
    $page = ContentPage::factory()->create();
    $url = Storage::disk('public')->url('content-pages/a.jpg');

    $this->patchJson("/api/v1/admin/content-pages/{$page->id}", ['blocks' => [
        ['type' => 'rich-text', 'data' => ['content' => '<p onclick="x()">Hi <strong>there</strong><script>alert(1)</script> <a href="javascript:alert(1)">bad</a> <a href="https://reno.sh">ok</a></p><ul><li>one</li></ul>']],
        ['type' => 'two-columns', 'data' => ['left' => '<p>L<img src=x onerror=alert(1)></p>', 'right' => '<p><em>R</em></p>', 'ratio' => '50-50']],
        ['type' => 'image', 'data' => ['image' => $url, 'alt' => 'A', 'alignment' => 'center']],
        ['type' => 'image-gallery', 'data' => ['images' => [['image' => $url], ['image' => 'https://example.com/b.jpg']], 'columns' => 2]],
    ]])->assertOk()
        ->assertJsonPath('data.blocks.2.data.image', $url);

    $content = $page->fresh()->content;
    expect($content[0]['data']['content'])
        ->toContain('<strong>there</strong>', '<ul><li>one</li></ul>', 'href="https://reno.sh"')
        ->not->toContain('script', 'onclick', 'javascript:')
        ->and($content[1]['data']['left'])->not->toContain('onerror')
        ->and($content[1]['data']['right'])->toBe('<p><em>R</em></p>')
        ->and($content[2]['data']['image'])->toBe('content-pages/a.jpg')
        ->and($content[3]['data']['images'][0]['image'])->toBe('content-pages/a.jpg')
        ->and($content[3]['data']['images'][1]['image'])->toBe('https://example.com/b.jpg');
});
