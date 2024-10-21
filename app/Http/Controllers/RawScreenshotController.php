<?php

namespace App\Http\Controllers;

use App\Models\Screenshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RawScreenshotController extends Controller
{
    public function __invoke(Screenshot $screenshot, Request $request)
    {
        $stream = Storage::disk('s3')->readStream($screenshot->stored_path);

        return response()->stream(
            fn () => fpassthru($stream),
            200, [
                'Content-Type' => $screenshot->mime_type,
                //'Content-Length' => $screenshot->size,
                //'Last-Modified' => $screenshot->updated_at,
                //'Content-Disposition' => "attachment; filename={$screenshot->stored_filename}"
            ]
        );
    }
}
