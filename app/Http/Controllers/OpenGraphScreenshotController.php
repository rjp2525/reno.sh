<?php

namespace App\Http\Controllers;

use App\Models\Screenshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;

class OpenGraphScreenshotController extends Controller
{
    public function __invoke(Screenshot $screenshot, Request $request)
    {
        $ogWidth = 1200;
        $ogHeight = 627;

        $file = Storage::disk('s3')->get($screenshot->stored_path);
        $manager = new ImageManager(new Driver);
        $image = $manager->read($file);

        $image->resize($ogWidth, null, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });

        if ($image->height() > $ogHeight) {
            $image->crop($ogWidth, $ogHeight);
        }

        return $image->response($screenshot->extension);
    }
}
