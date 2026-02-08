<?php

return [
    'feeds' => [
        'blog' => [
            'items' => [App\Models\Post::class, 'getFeedItems'],
            'url' => '/blog/feed.xml',
            'title' => 'Reno Philibert - Blog',
            'description' => 'Latest blog posts from Reno Philibert.',
            'language' => 'en-US',
            'image' => '',
            'format' => 'rss',
            'view' => 'feed::rss',
            'type' => '',
            'contentType' => '',
        ],
    ],
];
