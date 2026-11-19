<?php

return [
    /*
    | Driver for Laravel's native Image API (Illuminate\Support\Facades\Image).
    | Imagick is preferred when installed: it handles SVG watermarks, OG text and
    | keeps colour profiles when stripping metadata. GD is the fallback.
    */
    'default' => env('IMAGE_DRIVER', extension_loaded('imagick') ? 'imagick' : 'gd'),
];
