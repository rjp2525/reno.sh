<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ContentSection;
use App\Http\Controllers\Controller;
use App\Http\Resources\About\ContentPageResource;
use App\Models\ContentPage;
use App\Support\Content\Blocks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** About → Personal / Hobbies pages. */
class ContentPageController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['section' => ['sometimes', Rule::enum(ContentSection::class)]]);

        return ContentPageResource::collection($this->ordered()
            ->when($request->query('section'), fn ($query, $section) => $query->where('section', $section))
            ->get());
    }

    public function store(Request $request): JsonResponse
    {
        $page = $this->persist(new ContentPage, $this->validated($request));

        return (new ContentPageResource($page))->response()->setStatusCode(201);
    }

    public function show(ContentPage $contentPage): ContentPageResource
    {
        return new ContentPageResource($contentPage);
    }

    public function update(Request $request, ContentPage $contentPage): ContentPageResource
    {
        return new ContentPageResource($this->persist($contentPage, $this->validated($request, $contentPage)));
    }

    public function destroy(ContentPage $contentPage): Response
    {
        $contentPage->delete();

        return response()->noContent();
    }

    /** Sets `order` to each id's position in the list (ids may span sections). */
    public function reorder(Request $request): AnonymousResourceCollection
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'distinct', 'exists:content_pages,id'],
        ])['ids'];

        DB::transaction(function () use ($ids) {
            foreach (array_values($ids) as $position => $id) {
                ContentPage::query()->whereKey($id)->update(['order' => $position]);
            }
        });

        return ContentPageResource::collection($this->ordered()->get());
    }

    private function ordered()
    {
        return ContentPage::query()->withoutGlobalScopes()->orderBy('section')->orderBy('order')->orderBy('id');
    }

    private function validated(Request $request, ?ContentPage $page = null): array
    {
        $creating = $page === null;
        $section = $request->input('section', $page?->section?->value);

        return $request->validate([
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'alpha_dash', 'max:255',
                Rule::unique('content_pages', 'slug')->where('section', $section)->ignore($page?->id)],
            'section' => [$creating ? 'required' : 'sometimes', Rule::enum(ContentSection::class)],
            'icon' => ['sometimes', 'nullable', 'string', 'max:64'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'is_published' => ['sometimes', 'boolean'],
            'markdown' => ['sometimes', 'nullable', 'string', 'max:200000'],
            'blocks' => ['sometimes', 'nullable', 'array'],
            'blocks.*.type' => ['required_with:blocks', 'string', Rule::in(['rich-text', 'markdown', 'heading', 'image', 'image-gallery', 'two-columns', 'callout', 'code', 'divider'])],
            'blocks.*.data' => ['nullable', 'array'],
        ]);
    }

    private function persist(ContentPage $page, array $data): ContentPage
    {
        $attributes = Arr::except($data, ['markdown', 'blocks']);

        if (array_key_exists('markdown', $data)) {
            $attributes['content'] = Blocks::fromMarkdown((string) $data['markdown']);
        } elseif (array_key_exists('blocks', $data)) {
            $attributes['content'] = Blocks::fromApi($data['blocks'] ?? []);
        }

        $section = $attributes['section'] ?? $page->section?->value;

        if (! $page->exists) {
            $attributes['content'] ??= [];
            $attributes['is_published'] ??= false;
            $attributes['order'] ??= (int) ContentPage::query()->withoutGlobalScopes()->where('section', $section)->max('order') + 1;
        }

        if (blank($attributes['slug'] ?? null) && (! $page->exists || array_key_exists('slug', $attributes))) {
            $attributes['slug'] = $this->uniqueSlug($attributes['title'] ?? $page->title, $section, $page->id);
        }

        $page->fill($attributes)->save();

        return $page->refresh();
    }

    private function uniqueSlug(string $title, string $section, ?int $ignore): string
    {
        $base = Str::slug($title) ?: Str::lower(Str::random(8));
        $slug = $base;

        for ($i = 2; ContentPage::query()->withoutGlobalScopes()->where('section', $section)->where('slug', $slug)
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore))->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
