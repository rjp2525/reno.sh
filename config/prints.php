<?php

return [

    /*
    | Print-on-demand: Stripe Checkout takes payment, Prodigi prints & ships.
    | Products (SKU, size, price) are managed in /admin/print-products.
    */

    'currency' => env('PRINTS_CURRENCY', 'USD'),

    // Countries Stripe Checkout will accept a shipping address for (Prodigi ships worldwide).
    'shipping_countries' => array_filter(explode(',', env('PRINTS_SHIPPING_COUNTRIES', 'US,CA,GB,IE,AU,NZ,DE,FR,NL,BE,AT,ES,IT,PT,SE,DK,NO,FI,CH'))),

    // Flat shipping (minor units) added at checkout. 0 = shipping is baked into product prices.
    'shipping_flat' => (int) env('PRINTS_SHIPPING_FLAT', 0),
    'shipping_label' => env('PRINTS_SHIPPING_LABEL', 'Tracked shipping'),
    'shipping_days' => [5, 12],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'automatic_tax' => (bool) env('STRIPE_AUTOMATIC_TAX', false),
    ],

    'prodigi' => [
        'key' => env('PRODIGI_API_KEY'),
        'environment' => env('PRODIGI_ENVIRONMENT', 'sandbox'),
        'base_urls' => [
            'sandbox' => 'https://api.sandbox.prodigi.com/v4.0',
            'live' => 'https://api.prodigi.com/v4.0',
        ],
        // Prodigi callbacks are unsigned, so the callback URL carries this token and
        // every callback is re-fetched from the API before we trust it.
        'callback_token' => env('PRODIGI_WEBHOOK_SECRET'),
        'shipping_method' => env('PRODIGI_SHIPPING_METHOD', 'Standard'),
    ],

    // Who gets the "new print order" email.
    'notify' => env('PRINTS_NOTIFY_EMAIL', env('SITE_EMAIL', 'hello@reno.sh')),

];
