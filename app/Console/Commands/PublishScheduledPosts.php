<?php

namespace App\Console\Commands;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Console\Command;

class PublishScheduledPosts extends Command
{
    protected $signature = 'blog:publish-scheduled';

    protected $description = 'Publish scheduled blog posts whose published_at time has passed';

    public function handle(): int
    {
        $posts = Post::where('status', PostStatus::SCHEDULED)
            ->where('published_at', '<=', now())
            ->get();

        if ($posts->isEmpty()) {
            $this->components->info('No scheduled posts to publish.');

            return self::SUCCESS;
        }

        foreach ($posts as $post) {
            $post->update(['status' => PostStatus::PUBLISHED]);
            $this->components->info("Published: {$post->title}");
        }

        $this->components->info("Published {$posts->count()} post(s).");

        return self::SUCCESS;
    }
}
