<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\Social\SocialComposer;
use App\Support\Content\Blocks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $posts = Post::with(['category', 'series', 'tags'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('q'), fn ($q, $term) => $q->whereLike('title', "%{$term}%"))
            ->latest('updated_at')
            ->paginate($this->perPage($request));

        return PostResource::collection($posts);
    }

    public function store(Request $request, SocialComposer $composer): JsonResponse
    {
        $post = $this->persist(new Post, $this->validated($request), $request, $composer);

        return (new PostResource($post))->admin()->response()->setStatusCode(201);
    }

    public function show(Post $post): PostResource
    {
        return (new PostResource($post->load(['category', 'series', 'tags'])))->admin();
    }

    public function update(Request $request, Post $post, SocialComposer $composer): PostResource
    {
        $post = $this->persist($post, $this->validated($request, $post), $request, $composer);

        return (new PostResource($post))->admin();
    }

    public function destroy(Post $post): Response
    {
        $post->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?Post $post = null): array
    {
        $creating = $post === null;

        return $request->validate([
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'alpha_dash', 'max:255', Rule::unique('posts', 'slug')->ignore($post?->id)],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:500'],
            'blog_category_id' => ['sometimes', 'nullable', 'exists:blog_categories,id'],
            'blog_series_id' => ['sometimes', 'nullable', 'exists:blog_series,id'],
            'series_order' => ['sometimes', 'integer', 'min:0'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],
            'status' => [$creating ? 'required' : 'sometimes', Rule::enum(PostStatus::class)],
            'published_at' => ['sometimes', 'nullable', 'date', Rule::requiredIf(fn () => $request->input('status') === PostStatus::SCHEDULED->value)],
            'is_featured' => ['sometimes', 'boolean'],
            'featured_image' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'blocks' => ['sometimes', 'nullable', 'array'],
            'blocks.*.type' => ['required_with:blocks', 'string', Rule::in(['rich-text', 'markdown', 'heading', 'image', 'image-gallery', 'two-columns', 'callout', 'code', 'divider'])],
            'blocks.*.data' => ['nullable', 'array'],
            'markdown' => ['sometimes', 'nullable', 'string', 'max:200000'],
            'share' => ['sometimes', 'nullable', 'array'],
            'share.account_ids' => ['required_with:share', 'array', 'min:1'],
            'share.account_ids.*' => ['string', 'exists:social_accounts,id'],
            'share.body' => ['nullable', 'string', 'max:5000'],
            'share.scheduled_at' => ['nullable', 'date'],
        ]);
    }

    private function persist(Post $post, array $data, Request $request, SocialComposer $composer): Post
    {
        $attributes = Arr::except($data, ['tags', 'blocks', 'markdown', 'share']);

        if (array_key_exists('markdown', $data)) {
            $attributes['content'] = Blocks::fromMarkdown((string) $data['markdown']);
        } elseif (array_key_exists('blocks', $data)) {
            $attributes['content'] = $data['blocks'];
        }

        if (! $post->exists && blank($attributes['slug'] ?? null)) {
            $attributes['slug'] = $this->uniqueSlug($attributes['title']);
        }

        $post->fill($attributes)->save();

        if (array_key_exists('tags', $data)) {
            $post->syncTags($data['tags']);
        }

        if (filled($data['share'] ?? null)) {
            $post->load('category');
            $when = $data['share']['scheduled_at'] ?? $post->published_at;
            $composer->create([
                'body' => $data['share']['body'] ?? $post->title.($post->excerpt ? "\n\n".$post->excerpt : ''),
                'link_url' => $post->url(),
                'account_ids' => $data['share']['account_ids'],
                'media' => array_filter([$post->featured_image]),
                'scheduled_at' => $when ? Carbon::parse($when)->max(now()) : null,
                'publish_now' => $when === null,
            ], $request->user()->id, $post);
        }

        return $post->fresh(['category', 'series', 'tags']);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: Str::lower(Str::random(8));
        $slug = $base;
        $i = 2;
        while (Post::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
