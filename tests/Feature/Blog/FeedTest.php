<?php

use App\Enums\PostStatus;
use App\Models\BlogCategory;
use App\Models\Post;

it('serves a valid RSS feed with full content, guids and categories', function () {
    $category = BlogCategory::create(['name' => 'Open Source', 'slug' => 'open-source']);
    $post = Post::factory()->create([
        'title' => 'Go workers & Laravel',
        'status' => PostStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'blog_category_id' => $category->id,
        'content' => [['type' => 'markdown', 'data' => ['markdown' => "Hello **world**.\n\n```go\nfunc main() {}\n```"]]],
    ]);
    $post->syncTags(['go']);
    Post::factory()->create(['status' => PostStatus::DRAFT, 'published_at' => null, 'title' => 'Secret draft']);

    $response = $this->get('/blog/feed.xml')->assertOk();
    $xml = simplexml_load_string($response->getContent());
    expect($xml)->not->toBeFalse();

    $channel = $xml->channel;
    $item = $channel->item[0];
    $content = (string) $item->children('http://purl.org/rss/1.0/modules/content/')->encoded;

    expect((string) $channel->link)->toBe(route('blog.index'))
        ->and($channel->item)->toHaveCount(1)
        ->and((string) $item->title)->toBe('Go workers & Laravel')
        ->and((string) $item->guid)->toBe($post->url())
        ->and((string) $item->children('http://purl.org/dc/elements/1.1/')->creator)->toBe('Reno Philibert')
        ->and(array_map('strval', iterator_to_array($item->category, false)))->toBe(['Open Source', 'go'])
        ->and($content)->toContain('<strong>world</strong>')->toContain('<pre><code class="language-go">');
});
