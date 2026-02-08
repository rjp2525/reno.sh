<?php

namespace App\Observers;

use App\Enums\PostStatus;
use App\Models\Post;

class PostObserver
{
    public function saving(Post $post): void
    {
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
