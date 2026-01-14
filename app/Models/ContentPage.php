<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentSection;
use App\Models\Scopes\OrderedScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ScopedBy([OrderedScope::class])]
class ContentPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'section',
        'slug',
        'title',
        'icon',
        'content',
        'order',
        'is_published',
    ];

    protected $casts = [
        'section' => ContentSection::class,
        'content' => 'array',
        'order' => 'integer',
        'is_published' => 'boolean',
    ];

    /**
     * Scope to filter by section
     */
    public function scopeSection(Builder $query, ContentSection $section): Builder
    {
        return $query->where('section', $section->value);
    }

    /**
     * Scope to filter published content only
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Get the route key name for model binding
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
