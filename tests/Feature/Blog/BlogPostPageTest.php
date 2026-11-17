<?php

use App\Enums\PostStatus;
use App\Models\Post;
use Inertia\Testing\AssertableInertia as Assert;

it('sends markdown blocks with rendered HTML, a canonical url and a word count', function () {
    $post = Post::factory()->create([
        'status' => PostStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'blog_category_id' => null,
        'content' => [
            ['type' => 'markdown', 'data' => ['markdown' => "I love Laravel's queue.\n\n```go\nfunc main() {}\n```"]],
            ['type' => 'heading', 'data' => ['content' => 'What it is', 'level' => 'h2']],
        ],
    ]);

    $this->get("/blog/post/{$post->slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('BlogPost')
            ->where('post.url', $post->url())
            ->where('post.word_count', fn ($n) => $n >= 4)
            ->where('post.content.0.type', 'markdown')
            ->where('post.content.0.data.content', fn ($html) => str_contains($html, '<p>I love Laravel')
                && str_contains($html, '<pre><code class="language-go">'))
            ->etc());
});

it('prints a single fallback title when the page is not server-rendered', function () {
    $post = Post::factory()->create(['status' => PostStatus::PUBLISHED, 'published_at' => now()->subDay(), 'blog_category_id' => null]);

    expect(substr_count($this->get("/blog/post/{$post->slug}")->getContent(), '<title'))->toBe(1);
});
