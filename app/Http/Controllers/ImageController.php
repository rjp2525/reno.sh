<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

class ImageController extends Controller
{
    public function __invoke(string $path)
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            abort(404);
        }

        return $disk->response($path, null, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Last-Modified' => gmdate('D, d M Y H:i:s', $disk->lastModified($path)).' GMT',
        ]);
    }
}
