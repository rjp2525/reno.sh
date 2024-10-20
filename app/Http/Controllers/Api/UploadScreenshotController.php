<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ScreenshotUploadRequest;
use App\Models\Screenshot;
use Illuminate\Support\Carbon;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;

class UploadScreenshotController extends Controller
{
    public function __invoke(ScreenshotUploadRequest $request)
    {
        $file = $request->file('file');
        $modifiedTimestamp = $file->getMTime();
        $bytes = $file->getSize();
        $name = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $mime = $file->getMimeType();
        $context = hash_init('xxh3');
        hash_update($context, $name);
        hash_update($context, $mime);
        hash_update($context, $bytes);
        hash_update($context, $modifiedTimestamp);
        $hash = hash_final($context);
        $height = null;
        $width = null;

        // Attempt to read height/width if file is an image
        if (in_array($mime, ['image/jpeg', 'image/jpg', 'image/png'])) {
            $manager = new ImageManager(new Driver);
            $image = $manager->read($file->get());

            $width = $image->width();
            $height = $image->height();
        }

        $path = $file->storeAs('screenshots', "{$hash}.{$extension}", 's3');

        $screenshot = Screenshot::create([
            'original_filename' => $name,
            'user_id' => $request->user()?->id,
            'stored_hash' => $hash,
            'stored_filename' => "{$hash}.{$extension}",
            'stored_path' => $path,
            'filesize' => $bytes,
            'private' => $request->input('private', false),
            'original_extension' => $extension,
            'mime_type' => $mime,
            'height' => $height,
            'width' => $width,
            'created_at' => Carbon::createFromTimestamp($modifiedTimestamp),
        ]);

        return response()->json([
            'success' => true,
            'url' => route('screenshot', compact('screenshot')),
        ]);
    }
}
