<?php

/*
|--------------------------------------------------------------------------
| Social publishing
|--------------------------------------------------------------------------
|
| App credentials and API settings for the native social scheduler. OAuth
| callbacks for every provider go to {APP_URL}/admin/social/callback/{provider}.
| Bluesky and Mastodon are connected by hand (no app registration needed).
| See docs/social.md for per-network setup.
|
*/

return [

    'http' => [
        // Seconds. Uploads (binary media) get their own, longer budget.
        'timeout' => (int) env('SOCIAL_HTTP_TIMEOUT', 30),
        'connect_timeout' => (int) env('SOCIAL_HTTP_CONNECT_TIMEOUT', 10),
        'upload_timeout' => (int) env('SOCIAL_HTTP_UPLOAD_TIMEOUT', 120),
    ],

    // Container/media processing polls (Instagram, Threads, Mastodon async media).
    'polling' => [
        'attempts' => (int) env('SOCIAL_POLL_ATTEMPTS', 10),
        'interval' => (int) env('SOCIAL_POLL_INTERVAL', 3), // seconds
    ],

    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'graph_version' => env('META_GRAPH_VERSION', 'v24.0'),
        'graph_url' => 'https://graph.facebook.com',
        'dialog_url' => 'https://www.facebook.com',
    ],

    'linkedin' => [
        'client_id' => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        // YYYYMM. LinkedIn sunsets versions roughly a year after release.
        'version' => env('LINKEDIN_API_VERSION', '202610'),
        // Also connect Company Pages you administer (needs the Community Management API product).
        'organizations' => (bool) env('LINKEDIN_ORGANIZATIONS', false),
        'api_url' => 'https://api.linkedin.com',
        'oauth_url' => 'https://www.linkedin.com/oauth/v2',
    ],

    'x' => [
        'client_id' => env('X_CLIENT_ID'),
        'client_secret' => env('X_CLIENT_SECRET'),
        'api_url' => 'https://api.x.com',
        'authorize_url' => 'https://x.com/i/oauth2/authorize',
    ],

    'threads' => [
        'app_id' => env('THREADS_APP_ID'),
        'app_secret' => env('THREADS_APP_SECRET'),
        'graph_url' => 'https://graph.threads.net',
        'graph_version' => env('THREADS_GRAPH_VERSION', 'v1.0'),
        'authorize_url' => 'https://threads.net/oauth/authorize',
    ],

    'bluesky' => [
        // Used when an account doesn't specify its own PDS.
        'default_pds' => env('BLUESKY_DEFAULT_PDS', 'https://bsky.social'),
        'max_image_bytes' => 1_000_000,
    ],

    'mastodon' => [
        'visibility' => env('MASTODON_VISIBILITY', 'public'),
    ],

    /*
    | OAuth scopes requested at connect time. Meta joins with commas, the
    | others with spaces; each OAuth provider class handles its own format.
    */
    'scopes' => [
        'facebook' => [
            'pages_show_list',
            'pages_read_engagement',
            'pages_manage_posts',
            'business_management',
        ],
        'instagram' => [
            'instagram_basic',
            'instagram_content_publish',
            'pages_show_list',
            'pages_read_engagement',
            'business_management',
        ],
        'linkedin' => ['openid', 'profile', 'w_member_social'],
        'linkedin_organizations' => ['r_organization_social', 'w_organization_social', 'rw_organization_admin'],
        'x' => ['tweet.read', 'tweet.write', 'users.read', 'media.write', 'offline.access'],
        'threads' => ['threads_basic', 'threads_content_publish'],
    ],

];
