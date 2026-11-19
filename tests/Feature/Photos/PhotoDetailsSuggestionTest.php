<?php

use App\Ai\Agents\PhotoDetailsAgent;
use App\Filament\Resources\PhotoResource\Pages\CreatePhoto;
use App\Models\PhotoCategory;
use App\Models\User;
use App\Services\Photo\PhotoDetailsSuggester;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    config([
        'ai.providers.anthropic.key' => 'sk-ant-test',
        'photography.ai.enabled' => true,
        'photography.ai.model' => 'claude-opus-5-5',
        'photography.ai.effort' => 'medium',
    ]);
    PhotoCategory::create(['name' => 'Nature', 'slug' => 'nature', 'is_active' => true]);
    PhotoCategory::create(['name' => 'Landscapes', 'slug' => 'landscapes', 'is_active' => true]);
});

function claudeReplies(array $json, string $stopReason = 'end_turn'): void
{
    Http::fake(['api.anthropic.com/*' => Http::response([
        'id' => 'msg_test', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
        'content' => [['type' => 'text', 'text' => json_encode($json)]],
        'stop_reason' => $stopReason, 'usage' => ['input_tokens' => 1800, 'output_tokens' => 120],
    ])]);
}

function photoFile(): string
{
    $path = tempnam(sys_get_temp_dir(), 'photo').'.jpg';
    $im = new Imagick;
    $im->newPseudoImage(2400, 1600, 'gradient:red-green');
    $im->setImageFormat('jpeg');
    file_put_contents($path, $im->getImageBlob());

    return $path;
}

it('sends Opus 5.5 a downscaled image with structured output, effort and refusal fallbacks', function () {
    claudeReplies(['title' => 'Autumn Bramble', 'description' => 'Red leaves.', 'category' => 'Nature', 'tags' => ['leaves']]);

    app(PhotoDetailsSuggester::class)->suggest(photoFile(), 'DSC_0062.jpg', ['camera_body' => 'Nikon Z8', 'lens' => '105mm f/2.8', 'aperture' => 'f/4', 'iso' => 200]);

    Http::assertSent(function (Request $request) {
        $body = $request->data();
        $image = collect($body['messages'][0]['content'])->firstWhere('type', 'image');
        $text = collect($body['messages'][0]['content'])->firstWhere('type', 'text')['text'] ?? '';
        [$w, $h] = getimagesizefromstring(base64_decode($image['source']['data']));

        return $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->hasHeader('x-api-key', 'sk-ant-test')
            && str_contains($request->header('anthropic-beta')[0], 'server-side-fallback-2026-07-01')
            && $body['model'] === 'claude-opus-5-5'
            && $body['fallbacks'] === 'default'
            && $body['output_config']['effort'] === 'medium'
            && $body['output_config']['format']['type'] === 'json_schema'
            && $body['output_config']['format']['schema']['properties']['category']['enum'] === ['Landscapes', 'Nature', 'none']
            && ! isset($body['tool_choice'], $body['tools'], $body['temperature'], $body['top_p'])
            && $image['source']['media_type'] === 'image/jpeg' && max($w, $h) === 1568
            && str_contains($text, 'Camera: Nikon Z8 with 105mm f/2.8')
            && str_contains($text, 'Categories: Landscapes, Nature')
            && str_contains($body['system'], 'portfolio');
    });
});

it('cleans up the suggestion for the form', function () {
    claudeReplies([
        'title' => '"Autumn Bramble."',
        'description' => "Red leaves catch the last light.\n\nThe forest floor fades into soft green & shadow.",
        'category' => 'nature',
        'tags' => ['Leaves', '#autumn', 'leaves', '  forest floor ', ''],
    ]);

    expect(app(PhotoDetailsSuggester::class)->suggest(photoFile()))->toBe([
        'title' => 'Autumn Bramble',
        'description' => '<p>Red leaves catch the last light.</p><p>The forest floor fades into soft green &amp; shadow.</p>',
        'photo_category_id' => PhotoCategory::where('name', 'Nature')->value('id'),
        'tags' => ['leaves', 'autumn', 'forest floor'],
    ]);
});

it('returns nothing when disabled, on API errors, or when the model declines', function () {
    $suggester = app(PhotoDetailsSuggester::class);

    config(['ai.providers.anthropic.key' => null]);
    expect($suggester->enabled())->toBeFalse()->and($suggester->suggest(photoFile()))->toBeNull();

    config(['ai.providers.anthropic.key' => 'sk-ant-test']);
    Http::fake(['api.anthropic.com/*' => Http::response(['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'Overloaded']], 529)]);
    expect($suggester->suggest(photoFile()))->toBeNull();

    claudeReplies(['title' => '', 'description' => '', 'category' => 'none', 'tags' => []], 'refusal');
    expect($suggester->suggest(photoFile()))->toBeNull();
});

it('fills the empty fields after an upload in the admin, keeping what was typed', function () {
    config(['site.admin_emails' => ['admin@example.com']]);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.com']));
    Storage::fake('photos_originals');

    PhotoDetailsAgent::fake([[
        'title' => 'Autumn Bramble', 'description' => 'Red leaves catch the last light.',
        'category' => 'Nature', 'tags' => ['leaves', 'autumn'],
    ]]);

    Livewire::test(CreatePhoto::class)
        ->fillForm(['description' => '<p>My own words.</p>'])
        ->set('data.original_path', UploadedFile::fake()->image('DSC_0062.jpg', 1200, 800))
        ->assertFormSet([
            'title' => 'Autumn Bramble',
            'slug' => 'autumn-bramble',
            'photo_category_id' => PhotoCategory::where('name', 'Nature')->value('id'),
            'tags' => ['leaves', 'autumn'],
        ])
        ->assertFormSet(['description' => '<p>My own words.</p>'])
        ->assertNotified('Details suggested');

    PhotoDetailsAgent::assertPrompted(fn ($prompt) => $prompt->contains('Original filename: DSC_0062.jpg'));
});
