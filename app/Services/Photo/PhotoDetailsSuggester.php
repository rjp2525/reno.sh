<?php

declare(strict_types=1);

namespace App\Services\Photo;

use App\Ai\Agents\PhotoDetailsAgent;
use App\Models\PhotoCategory;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Files\Image as AiImage;
use Spatie\Tags\Tag;
use Throwable;

/**
 * Asks Claude for a photo's title, description, category and tags.
 * Returns null (and logs why) instead of throwing, so an upload never fails because of it.
 */
class PhotoDetailsSuggester
{
    public function enabled(): bool
    {
        return (bool) config('photography.ai.enabled') && filled(config('ai.providers.anthropic.key'));
    }

    /**
     * @param  array<string, mixed>  $exif  ExifExtractorService output
     * @return array{title: string, description: string, photo_category_id: string|int|null, tags: list<string>}|null
     */
    public function suggest(string $path, ?string $filename = null, array $exif = []): ?array
    {
        if (! $this->enabled() || ! is_file($path)) {
            return null;
        }

        $categories = PhotoCategory::active()->ordered()->pluck('name', 'id');
        $agent = new PhotoDetailsAgent(
            categories: $categories->values()->all(),
            knownTags: Tag::query()->where('type', 'photo')->orWhereNull('type')->limit(300)->get()
                ->map(fn (Tag $tag) => (string) $tag->name)->filter()->unique()->values()->all(),
        );

        try {
            $response = $agent->prompt(
                $agent->promptFor($filename, $exif),
                attachments: [AiImage::fromBase64(base64_encode($this->preview($path)), 'image/jpeg')],
                model: (string) config('photography.ai.model', 'claude-opus-5-5'),
            );
        } catch (Throwable $e) {
            Log::warning('Photo AI suggestion failed', ['file' => $filename, 'error' => $e->getMessage()]);

            return null;
        }

        $title = Str::of((string) ($response['title'] ?? ''))->trim()->trim('."\'')->limit(120, '')->toString();
        if ($title === '') {
            Log::warning('Photo AI suggestion was empty or declined', ['file' => $filename]);

            return null;
        }

        $category = (string) ($response['category'] ?? '');

        return [
            'title' => $title,
            'description' => $this->paragraphs((string) ($response['description'] ?? '')),
            'photo_category_id' => $categories->search(fn ($name) => strcasecmp($name, $category) === 0) ?: null,
            'tags' => collect($response['tags'] ?? [])
                ->map(fn ($tag) => Str::of((string) $tag)->lower()->trim()->trim('#')->squish()->toString())
                ->filter(fn ($tag) => $tag !== '' && mb_strlen($tag) <= 40)
                ->unique()->take(10)->values()->all(),
        ];
    }

    /** A JPEG no bigger than Claude looks at: smaller upload, fewer image tokens. */
    private function preview(string $path): string
    {
        $edge = (int) config('photography.ai.max_edge', 1568);

        return Image::fromPath($path)->orient()->scale($edge, $edge)->optimize('jpg', 85)->toBytes();
    }

    /** Plain text from Claude → the HTML the description editor stores. */
    private function paragraphs(string $text): string
    {
        return collect(preg_split("/\n\s*\n/", trim($text)) ?: [])
            ->map(fn ($p) => trim(preg_replace('/\s+/', ' ', $p) ?? ''))
            ->filter()
            ->map(fn ($p) => '<p>'.e($p).'</p>')
            ->implode('');
    }
}
