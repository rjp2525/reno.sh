<?php

use App\Models\Post;

return [
    'feeds' => [
        'blog' => [
            'items' => [Post::class, 'getFeedItems'],
            'url' => '/blog/feed.xml',
            'title' => 'Reno Philibert - Blog',
            'description' => 'Latest blog posts from Reno Philibert.',
            'language' => 'en-US',
            'image' => '',
            'format' => 'rss',
            'view' => 'feeds.rss',
            'type' => '',
            'contentType' => '',
        ],
    ],
];
