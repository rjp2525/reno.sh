<?php

namespace App\Models;

use App\Enums\ProjectType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Tags\HasTags;

class Project extends Model
{
    use HasFactory;
    use HasTags;
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'summary',
        'description',
        'type',
        'status',
        'featured_image',
        'gallery',
        'url',
        'github_url',
        'demo_url',
        'start_date',
        'end_date',
        'is_ongoing',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'og_image',
        'is_featured',
        'sort_order',
        'is_published',
    ];

    protected $casts = [
        'type' => ProjectType::class,
        'gallery' => 'array',
        'meta_keywords' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_ongoing' => 'boolean',
        'is_featured' => 'boolean',
        'is_published' => 'boolean',
    ];

    public function technologies(): BelongsToMany
    {
        return $this->belongsToMany(Technology::class)
            ->withPivot(['proficiency_level', 'is_primary'])
            ->withTimestamps();
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
