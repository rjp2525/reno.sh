<?php

namespace App\Models;

use App\Enums\SocialTargetStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One post going to one account.
 *
 * @property SocialTargetStatus $status
 */
class SocialPostTarget extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'social_post_id', 'social_account_id', 'body_override', 'status',
        'provider_post_id', 'provider_url', 'error', 'attempts', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SocialTargetStatus::class,
            'published_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'social_post_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    /** The text that actually goes out: override, else post body, plus the link when it isn't already in there. */
    public function resolvedBody(): string
    {
        $body = trim((string) ($this->body_override ?: $this->post->body));
        $link = $this->post->link_url;

        if ($link && ! str_contains($body, $link)) {
            $body = trim($body."\n\n".$link);
        }

        return $body;
    }
}
