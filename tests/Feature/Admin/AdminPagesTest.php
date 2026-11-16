<?php

use App\Enums\SocialProvider;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\User;

beforeEach(function () {
    config(['site.admin_emails' => ['admin@example.com']]);
    $this->admin = User::factory()->create(['email' => 'admin@example.com']);
});

it('keeps non-admins out of the panel', function () {
    $this->actingAs(User::factory()->create(['email' => 'someone@example.com']))
        ->get('/admin')
        ->assertForbidden();
});

it('renders every admin page', function (string $path) {
    SocialAccount::factory()->create(['provider' => SocialProvider::MASTODON]);

    $this->actingAs($this->admin)->get($path)->assertOk();
})->with([
    '/admin',
    '/admin/posts',
    '/admin/posts/create',
    '/admin/photos',
    '/admin/photos/create',
    '/admin/social-posts',
    '/admin/social-posts/create',
    '/admin/social-accounts',
    '/admin/social/calendar',
]);

it('renders edit pages for existing records', function () {
    $post = Post::create(['title' => 'Hello', 'slug' => 'hello', 'status' => 'draft', 'content' => [['type' => 'markdown', 'data' => ['markdown' => '# Hi']]]]);
    $social = SocialPost::factory()->create();

    $this->actingAs($this->admin)->get("/admin/posts/{$post->slug}/edit")->assertOk()->assertSee('Hello');
    $this->actingAs($this->admin)->get("/admin/social-posts/{$social->id}/edit")->assertOk();
});
