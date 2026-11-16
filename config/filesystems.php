<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
        ],

        // Site images (photo renditions, blog/page uploads). On S3/R2 this bucket must allow public reads;
        // set FILESYSTEM_PUBLIC_URL to its public domain (e.g. https://cdn.reno.sh) so images are served
        // straight from the CDN. Without it they're proxied through /img/{path} (ImageController).
        'public' => [
            'driver' => env('FILESYSTEM_PUBLIC_DRIVER', 'local'),
            'root' => env('FILESYSTEM_PUBLIC_DRIVER', 'local') === 's3'
                ? ''
                : storage_path('app/public'),
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'auto'),
            'bucket' => env('AWS_BUCKET'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'url' => rtrim((string) env('FILESYSTEM_PUBLIC_URL', env('APP_URL').'/img'), '/'),
            'throw' => true,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

        // Full-resolution originals. Keep these in a separate, PRIVATE bucket: R2/S3 public access is
        // per bucket, so sharing the public bucket would make every original downloadable.
        'photos_originals' => [
            'driver' => env('PHOTO_ORIGINALS_DRIVER', 'local'),
            'root' => env('PHOTO_ORIGINALS_DRIVER', 'local') === 's3'
                ? 'originals'
                : storage_path('app/photos/originals'),
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'auto'),
            'bucket' => env('PHOTO_ORIGINALS_BUCKET', env('AWS_BUCKET')),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            // Private: only reachable through signed temporaryUrl()s (e.g. print lab asset fetches).
            'serve' => true,
            'url' => env('APP_URL').'/originals',
            'visibility' => 'private',
            'throw' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
