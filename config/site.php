<?php

/*
|--------------------------------------------------------------------------
| Site profile
|--------------------------------------------------------------------------
|
| Shared by the web front end, the JSON API (/api/v1/site), the curl/ANSI
| terminal renderer and the SSH TUI so the identity lives in one place.
|
*/

return [

    'name' => 'Reno Philibert',
    'title' => 'Full Stack Software Engineer',
    'tagline' => 'Building awesome things for the web since 2011.',
    'location' => env('SITE_LOCATION'),
    'email' => env('SITE_EMAIL', 'hello@reno.sh'),

    'socials' => [
        ['label' => 'GitHub', 'url' => 'https://github.com/rjp2525'],
        ['label' => 'X', 'url' => 'https://x.com/RenoPhilibert'],
        ['label' => 'Instagram', 'url' => 'https://instagram.com/renophilibert'],
    ],

    'ssh' => [
        'host' => env('TUI_SSH_HOST', 'reno.sh'),
        'port' => (int) env('TUI_SSH_PORT', 2222),
    ],

    /*
    | Only these accounts can sign in to /admin or mint API tokens.
    */
    'admin_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ADMIN_EMAILS', ''))
    ))),

];
