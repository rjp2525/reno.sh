<?php

declare(strict_types=1);

namespace App\Support\Feed;

use Spatie\Feed\FeedItem;

/** A feed item that also carries the full post HTML for <content:encoded>. */
class PostFeedItem extends FeedItem
{
    protected string $content = '';

    public function content(string $html): self
    {
        $this->content = $html;

        return $this;
    }
}
