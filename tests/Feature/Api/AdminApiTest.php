<?php

use App\Enums\SocialPostStatus;
use App\Enums\SocialProvider;
use App\Jobs\ProcessPhotoJob;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['site.admin_emails' => ['admin@example.com']]);
    $this->admin = User::factory()->create(['email' => 'admin@example.com', 'password' => 'secret-pass']);
});

function token(): string
{
    return test()->postJson('/api/v1/auth/token', [
        'email' => 'admin@example.com', 'password' => 'secret-pass', 'device_name' => 'iPhone',
    ])->assertCreated()->json('data.token');
}

it('issues tokens only to admins with the right password', function () {
    $this->postJson('/api/v1/auth/token', ['email' => 'admin@example.com', 'password' => 'nope', 'device_name' => 'x'])
        ->assertUnprocessable();

    User::factory()->create(['email' => 'fan@example.com', 'password' => 'secret-pass']);
    $this->postJson('/api/v1/auth/token', ['email' => 'fan@example.com', 'password' => 'secret-pass', 'device_name' => 'x'])
        ->assertUnprocessable();

    $this->withToken(token())->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.email', 'admin@example.com');
});

it('blocks admin endpoints without a token or once revoked', function () {
    $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();

    $t = token();
    $this->withToken($t)->deleteJson('/api/v1/auth/token')->assertNoContent();
    $this->app['auth']->forgetGuards();
    $this->withToken($t)->getJson('/api/v1/admin/dashboard')->assertUnauthorized();
});

it('denies tokens of users removed from ADMIN_EMAILS', function () {
    $t = token();
    config(['site.admin_emails' => []]);

    $this->withToken($t)->getJson('/api/v1/admin/dashboard')->assertForbidden();
});

it('creates a post from Markdown with safe HTML and a social share', function () {
    Queue::fake();
    $account = SocialAccount::factory()->create(['provider' => SocialProvider::MASTODON, 'is_active' => true, 'needs_reauth' => false]);

    $response = $this->withToken(token())->postJson('/api/v1/admin/posts', [
        'title' => 'Shipping from my phone',
        'status' => 'scheduled',
        'published_at' => now()->addDay()->toIso8601String(),
        'tags' => ['ios', 'laravel'],
        'markdown' => "## Hello\n\nWritten on **iOS**. <script>alert(1)</script>",
        'share' => ['account_ids' => [$account->id], 'body' => 'New post!'],
    ])->assertCreated()
        ->assertJsonPath('data.slug', 'shipping-from-my-phone')
        ->assertJsonPath('data.status', 'scheduled')
        ->assertJsonPath('data.is_markdown', true)
        ->assertJsonPath('data.tags', ['ios', 'laravel']);

    $post = Post::findOrFail($response->json('data.id'));
    $html = $post->content[0]['data']['content'];
    expect($html)->toContain('<strong>iOS</strong>')->not->toContain('<script>')
        ->and($response->json('data.markdown'))->toContain('**iOS**');

    $social = SocialPost::with('targets')->sole();
    expect($social->status)->toBe(SocialPostStatus::SCHEDULED)
        ->and($social->link_url)->toBe($post->url())
        ->and($social->scheduled_at->toDateString())->toBe(now()->addDay()->toDateString())
        ->and($social->targets)->toHaveCount(1);

    // Not public until its publish time.
    $this->getJson('/api/v1/posts/shipping-from-my-phone')->assertNotFound();
});

it('updates posts partially and keeps the slug unique', function () {
    $t = token();
    $first = $this->withToken($t)->postJson('/api/v1/admin/posts', ['title' => 'Same', 'status' => 'draft'])->json('data');
    $second = $this->withToken($t)->postJson('/api/v1/admin/posts', ['title' => 'Same', 'status' => 'draft'])->json('data');
    expect($second['slug'])->toBe('same-2');

    $this->withToken($t)->patchJson("/api/v1/admin/posts/{$first['id']}", ['status' => 'published'])
        ->assertOk()->assertJsonPath('data.status', 'published')->assertJsonPath('data.title', 'Same');

    expect(Post::find($first['id'])->published_at)->not->toBeNull();
    $this->getJson('/api/v1/posts/same')->assertOk()->assertJsonPath('data.title', 'Same');
});

it('uploads photos and queues processing', function () {
    Queue::fake();
    Storage::fake('photos_originals');

    $this->withToken(token())->post('/api/v1/admin/photos', [
        'file' => UploadedFile::fake()->image('Golden Hour.jpg', 3000, 2000),
        'tags' => ['colorado'],
        'prints_enabled' => '0',
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Golden Hour')
        ->assertJsonPath('data.processing_status', 'pending')
        ->assertJsonPath('data.prints_enabled', false)
        ->assertJsonPath('data.tags', ['colorado']);

    Queue::assertPushed(ProcessPhotoJob::class);
});

it('uploads media and enforces per-network limits on social posts', function () {
    Storage::fake('public');
    $x = SocialAccount::factory()->create(['provider' => SocialProvider::X, 'is_active' => true, 'needs_reauth' => false]);
    $ig = SocialAccount::factory()->create(['provider' => SocialProvider::INSTAGRAM, 'is_active' => true, 'needs_reauth' => false]);
    $t = token();

    $this->withToken($t)->postJson('/api/v1/admin/social/posts', [
        'body' => str_repeat('a', 300),
        'account_ids' => [$x->id, $ig->id],
        'scheduled_at' => now()->addHour()->toIso8601String(),
    ])->assertUnprocessable()->assertJsonValidationErrors(["overrides.{$x->id}", 'media']);

    $path = $this->withToken($t)->post('/api/v1/admin/media', [
        'file' => UploadedFile::fake()->image('a.jpg'), 'directory' => 'social',
    ], ['Accept' => 'application/json'])->assertCreated()->json('data.path');

    $this->withToken($t)->postJson('/api/v1/admin/social/posts', [
        'body' => 'Short and sweet',
        'account_ids' => [$x->id, $ig->id],
        'media' => [$path],
        'scheduled_at' => now()->addHour()->toIso8601String(),
    ])->assertCreated()->assertJsonPath('data.status', 'scheduled')->assertJsonCount(2, 'data.targets');

    $this->withToken($t)->getJson('/api/v1/admin/social/calendar?from='.now()->toDateString().'&to='.now()->addDays(2)->toDateString())
        ->assertOk()->assertJsonPath('data.0.posts.0.body', 'Short and sweet');
});

it('serves the public content endpoints', function (string $path) {
    $this->getJson("/api/v1/{$path}")->assertOk()->assertJsonStructure(['data']);
})->with(['site', 'about', 'posts', 'projects', 'photos', 'photo-categories', 'blog/categories']);
