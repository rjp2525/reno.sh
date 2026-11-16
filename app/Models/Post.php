<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Observers\PostObserver;
use App\Support\Content\Blocks;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Feed\Feedable;
use Spatie\Feed\FeedItem;
use Spatie\Tags\HasTags;

#[ObservedBy(PostObserver::class)]
class Post extends Model implements Feedable
{
    use HasFactory;
    use HasTags;
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'featured_image',
        'blog_category_id',
        'blog_series_id',
        'series_order',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'og_image',
        'status',
        'published_at',
        'reading_time',
        'is_featured',
    ];

    protected $casts = [
        'content' => 'array',
        'meta_keywords' => 'array',
        'status' => PostStatus::class,
        'published_at' => 'datetime',
        'is_featured' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(BlogSeries::class, 'blog_series_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', PostStatus::PUBLISHED)
            ->where('published_at', '<=', now());
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', PostStatus::SCHEDULED);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('blog_category_id', $categoryId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('published_at', 'desc');
    }

    public function calculateReadingTime(): int
    {
        if (! $this->content || ! is_array($this->content)) {
            return 0;
        }

        $wordCount = str_word_count(Blocks::toText($this->content));

        return max(1, (int) ceil($wordCount / 200));
    }

    /** Canonical public URL (posts without a category live under /blog/post/{slug}). */
    public function url(): string
    {
        return $this->category
            ? route('blog.show', [$this->category, $this])
            : route('blog.show.uncategorized', $this);
    }

    public function getOgImageUrlAttribute(): ?string
    {
        return $this->og_image ?? $this->featured_image;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function toFeedItem(): FeedItem
    {
        $link = $this->url();

        return FeedItem::create()
            ->id($link)
            ->title($this->title)
            ->summary($this->excerpt ?? '')
            ->updated($this->published_at)
            ->link($link)
            ->authorName('Reno Philibert');
    }

    public static function getFeedItems()
    {
        return static::published()->ordered()->with('category')->limit(20)->get();
    }
}
