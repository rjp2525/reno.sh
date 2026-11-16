<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Support\Content\Blocks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    public const DIRECTORIES = ['blog/images', 'blog/featured', 'social', 'content-pages', 'projects'];

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,image/heic,image/heif,video/mp4,video/quicktime', 'max:51200'],
            'directory' => ['nullable', 'string', 'in:'.implode(',', self::DIRECTORIES)],
        ]);

        $file = $data['file'];
        $directory = $data['directory'] ?? 'blog/images';
        $name = Str::ulid()->toBase32().'.'.strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
        $path = $file->storeAs($directory, $name, 'public');
        $size = str_starts_with((string) $file->getMimeType(), 'image/') ? @getimagesize($file->getRealPath()) : false;

        return response()->json(['data' => [
            'path' => $path,
            'url' => Blocks::url($path),
            'width' => $size[0] ?? null,
            'height' => $size[1] ?? null,
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]], 201);
    }
}
