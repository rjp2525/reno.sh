<?php

/*
| Laravel AI SDK (laravel/ai). Only what this site uses; the package's own config fills the rest.
| Claude is called with an API key from console.anthropic.com (billed per use). Claude Pro/Max
| subscription logins can't be used by apps: Anthropic only allows those in its own apps.
*/

return [

    'default' => 'anthropic',

    'providers' => [
        'anthropic' => [
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
            // Lets a declined request be re-run on a fallback model server-side (see PhotoDetailsAgent).
            'anthropic_beta' => 'server-side-fallback-2026-07-01',
        ],
    ],

];
