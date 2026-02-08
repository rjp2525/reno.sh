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

];
