<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Watermark Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the logo watermark that will be applied to web-sized images.
    | The logo should be stored in the local storage disk.
    |
    */

    'watermark' => [
        'logo_path' => env('PHOTO_WATERMARK_LOGO', 'watermarks/logo.png'),
        'opacity' => env('PHOTO_WATERMARK_OPACITY', 40),
        'position' => 'bottom-right', // top-left, top-right, bottom-left, bottom-right
        'scale' => 0.05, // 5% of image width
    ],

    /*
    |--------------------------------------------------------------------------
    | Web Image Settings
    |--------------------------------------------------------------------------
    |
    | Settings for the watermarked web-resolution images.
    |
    */

    'web' => [
        'max_width' => 2000,
        'quality' => 85,
    ],

    /*
    |--------------------------------------------------------------------------
    | Thumbnail Settings
    |--------------------------------------------------------------------------
    |
    | Settings for thumbnail generation. Thumbnails are cropped to fit.
    |
    */

    'thumbnail' => [
        'width' => 400,
        'height' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which disk to use for original files (private) and
    | processed files (public). Originals should be kept private.
    |
    */

    'storage' => [
        'originals_disk' => env('PHOTO_ORIGINALS_DISK', 'photos_originals'),
        'public_disk' => 'public',
    ],

    /*
    |--------------------------------------------------------------------------
    | AI suggestions
    |--------------------------------------------------------------------------
    |
    | After a photo is uploaded in the admin, Claude suggests a title, description,
    | category and tags. Needs ANTHROPIC_API_KEY; off automatically without one.
    |
    */

    'ai' => [
        'enabled' => (bool) env('PHOTO_AI_ENABLED', filled(env('ANTHROPIC_API_KEY'))),
        'model' => env('PHOTO_AI_MODEL', 'claude-opus-5-5'),
        // low | medium | high: how hard Claude thinks before answering.
        'effort' => env('PHOTO_AI_EFFORT', 'medium'),
        // Longest edge of the copy sent to Claude (it works at ~1568px; bigger only costs more).
        'max_edge' => 1568,
    ],

];
