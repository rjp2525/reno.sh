<?php

namespace App\Models;

use App\Enums\SocialPostStatus;
use App\Enums\SocialTargetStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property SocialPostStatus $status
 * @property list<array{path: string, mime?: string}>|null $media
 */
class SocialPost extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'body', 'link_url', 'media', 'status', 'scheduled_at', 'published_at',
        'source_type', 'source_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'media' => 'array',
            'status' => SocialPostStatus::class,
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function targets(): HasMany
    {
        return $this->hasMany(SocialPostTarget::class);
    }

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(SocialAccount::class, 'social_post_targets')
            ->withPivot(['id', 'status', 'body_override'])
            ->withTimestamps();
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeDue($query)
    {
        return $query->where('status', SocialPostStatus::SCHEDULED)->where('scheduled_at', '<=', now());
    }

    /** @return list<array{path: string, url: string, mime: string}> */
    public function mediaItems(): array
    {
        $disk = Storage::disk('public');

        return collect($this->media ?? [])
            ->filter(fn ($m) => filled($m['path'] ?? null))
            ->map(fn ($m) => [
                'path' => $m['path'],
                'url' => $disk->url($m['path']),
                'mime' => $m['mime'] ?? ($disk->exists($m['path']) ? (string) $disk->mimeType($m['path']) : 'image/jpeg'),
            ])
            ->values()
            ->all();
    }

    /** Roll target outcomes up into the post status. */
    public function refreshStatus(): void
    {
        $statuses = $this->targets()->pluck('status')->map(fn ($s) => $s instanceof SocialTargetStatus ? $s : SocialTargetStatus::from($s));

        if ($statuses->isEmpty()) {
            return;
        }

        $published = $statuses->filter(fn ($s) => $s === SocialTargetStatus::PUBLISHED)->count();
        $failed = $statuses->filter(fn ($s) => $s === SocialTargetStatus::FAILED)->count();
        $pending = $statuses->count() - $published - $failed;

        $status = match (true) {
            $pending > 0 => SocialPostStatus::PUBLISHING,
            $published === $statuses->count() => SocialPostStatus::PUBLISHED,
            $published > 0 => SocialPostStatus::PARTIALLY_PUBLISHED,
            default => SocialPostStatus::FAILED,
        };

        $this->forceFill([
            'status' => $status,
            'published_at' => $published > 0 ? ($this->published_at ?? now()) : null,
        ])->save();
    }
}
