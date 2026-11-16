<?php

namespace App\Observers;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Support\Content\Blocks;

class PostObserver
{
    public function saving(Post $post): void
    {
        if ($post->isDirty('content')) {
            $post->content = Blocks::normalize($post->content);
        }

        $post->reading_time = $post->calculateReadingTime();

        if (
            $post->isDirty('status')
            && $post->status === PostStatus::PUBLISHED
            && ! $post->published_at
        ) {
            $post->published_at = now();
        }
    }
}
