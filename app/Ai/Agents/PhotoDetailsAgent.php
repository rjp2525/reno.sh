<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Looks at a newly uploaded photo and drafts its title, description, category and tags
 * for the admin form. Everything it returns is a suggestion the editor can change.
 */
#[Provider(Lab::Anthropic)]
#[MaxTokens(16000)]
#[Timeout(120)]
class PhotoDetailsAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    public const NO_CATEGORY = 'none';

    /**
     * @param  list<string>  $categories  Existing category names (the answer must be one of these, or null)
     * @param  list<string>  $knownTags  Tags already used on the site, to reuse where they fit
     */
    public function __construct(
        public readonly array $categories = [],
        public readonly array $knownTags = [],
    ) {}

    public function instructions(): Stringable|string
    {
        return <<<'TXT'
        You write the details for photographs in a landscape, nature and travel photographer's online portfolio.
        The photographer reviews and edits everything you suggest before it's published.

        - title: 2 to 6 words, evocative but specific to what is in the frame. No quotes, no trailing period,
          no camera settings, and don't start with "A" or "The" unless it reads naturally.
        - description: 1 to 3 short sentences in a calm, understated voice describing the subject, light and
          mood. Don't write "I" or "we". Plain text, no hashtags, no emoji, no superlatives like "stunning" or "breathtaking".
          Don't invent facts you can't see (exact locations, species you're unsure of, dates).
        - category: exactly one of the listed category names, or "none" if none fits.
        - tags: 3 to 8 lowercase tags, single words or short phrases, most specific first. Prefer an existing
          tag when it fits; only add new ones for things clearly in the photo.
        TXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required(),
            'description' => $schema->string()->required(),
            'category' => $schema->string()->enum([...$this->categories, self::NO_CATEGORY])->required(),
            'tags' => $schema->array()->items($schema->string())->required(),
        ];
    }

    public function providerOptions(Lab|string $provider): array
    {
        return [
            'output_config' => ['effort' => config('photography.ai.effort', 'medium')],
            // On a policy decline, Anthropic re-runs the request on a suitable fallback model.
            'fallbacks' => 'default',
        ];
    }

    /** The user turn: everything known about the photo besides the image itself. */
    public function promptFor(?string $filename, array $exif): string
    {
        $lines = array_filter([
            $filename ? 'Original filename: '.$filename : null,
            ($exif['camera_body'] ?? null) ? 'Camera: '.$exif['camera_body'].(($exif['lens'] ?? null) ? ' with '.$exif['lens'] : '') : null,
            collect([$exif['focal_length'] ?? null, $exif['aperture'] ?? null, $exif['shutter_speed'] ?? null, ($exif['iso'] ?? null) ? 'ISO '.$exif['iso'] : null])->filter()->implode(' · ') ?: null,
            ($exif['taken_at'] ?? null) ? 'Taken: '.$exif['taken_at'] : null,
            (($exif['gps_latitude'] ?? null) && ($exif['gps_longitude'] ?? null)) ? 'GPS: '.$exif['gps_latitude'].', '.$exif['gps_longitude'] : null,
            'Categories: '.($this->categories ? implode(', ', $this->categories) : '(none yet)'),
            $this->knownTags ? 'Existing tags: '.implode(', ', array_slice($this->knownTags, 0, 150)) : null,
        ]);

        return "Suggest the details for this photo.\n\n".implode("\n", $lines);
    }
}
