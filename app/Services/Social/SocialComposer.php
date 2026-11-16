<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Enums\SocialPostStatus;
use App\Enums\SocialTargetStatus;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Creates/updates social posts and their per-account targets. Shared by the
 * Filament composer and the JSON API so both enforce the same rules.
 *
 * $data keys: body, account_ids[], overrides{account_id: text}, media[] (public disk paths),
 * link_url, scheduled_at, publish_now (bool), draft (bool).
 */
class SocialComposer
{
    public function __construct(private readonly SocialScheduler $scheduler) {}

    public function create(array $data, ?int $userId = null, ?Model $source = null): SocialPost
    {
        return $this->save(new SocialPost(['created_by' => $userId]), $data, $source);
    }

    public function update(SocialPost $post, array $data): SocialPost
    {
        if (! $post->status->isEditable()) {
            throw ValidationException::withMessages(['status' => 'Published posts can no longer be edited.']);
        }

        return $this->save($post, $data);
    }

    private function save(SocialPost $post, array $data, ?Model $source = null): SocialPost
    {
        $accountIds = array_values(array_unique(array_map('strval', $data['account_ids'] ?? $post->targets()->pluck('social_account_id')->all())));
        $overrides = array_filter($data['overrides'] ?? [], fn ($v) => filled($v));
        $media = $this->media($data['media'] ?? collect($post->media ?? [])->pluck('path')->all());
        $body = array_key_exists('body', $data) ? trim((string) $data['body']) : (string) $post->body;
        $draft = (bool) ($data['draft'] ?? false);
        $now = (bool) ($data['publish_now'] ?? false);
        $scheduledAt = $now ? now() : (filled($data['scheduled_at'] ?? null) ? Carbon::parse($data['scheduled_at']) : $post->scheduled_at);

        $accounts = SocialAccount::whereIn('id', $accountIds)->get()->keyBy(fn ($a) => (string) $a->id);

        $this->validate($accounts, $accountIds, $body, $overrides, $media, $data['link_url'] ?? $post->link_url, $draft, $now, $scheduledAt);

        $post = DB::transaction(function () use ($post, $data, $body, $media, $draft, $scheduledAt, $accounts, $overrides, $source) {
            $post->fill([
                'body' => $body,
                'link_url' => $data['link_url'] ?? $post->link_url,
                'media' => $media,
                'scheduled_at' => $draft ? ($scheduledAt ?? null) : $scheduledAt,
                'status' => $draft ? SocialPostStatus::DRAFT : SocialPostStatus::SCHEDULED,
            ]);

            if ($source) {
                $post->source()->associate($source);
            }

            $post->save();

            $post->targets()->whereNotIn('social_account_id', $accounts->keys())->delete();

            foreach ($accounts as $id => $account) {
                $post->targets()->updateOrCreate(
                    ['social_account_id' => $id],
                    ['body_override' => $overrides[$id] ?? null, 'status' => SocialTargetStatus::PENDING, 'error' => null],
                );
            }

            return $post;
        });

        if (! $draft && $scheduledAt->lte(now())) {
            $this->scheduler->dispatch($post);
        }

        return $post->fresh(['targets.account']);
    }

    /** @return list<array{path: string, mime: string}> */
    private function media(array $paths): array
    {
        $disk = Storage::disk('public');

        return collect($paths)
            ->map(fn ($p) => is_array($p) ? ($p['path'] ?? null) : $p)
            ->filter()
            ->map(function (string $path) use ($disk) {
                if (! $disk->exists($path)) {
                    throw ValidationException::withMessages(['media' => "Media file not found: {$path}"]);
                }

                return ['path' => $path, 'mime' => (string) $disk->mimeType($path)];
            })
            ->values()
            ->all();
    }

    private function validate($accounts, array $accountIds, string $body, array $overrides, array $media, ?string $link, bool $draft, bool $now, ?Carbon $scheduledAt): void
    {
        $errors = [];

        if ($accountIds === [] && ! $draft) {
            $errors['account_ids'][] = 'Pick at least one account.';
        }

        if (count($accounts) !== count($accountIds)) {
            $errors['account_ids'][] = 'One or more accounts no longer exist.';
        }

        if (! $draft && ! $now && ! $scheduledAt) {
            $errors['scheduled_at'][] = 'Choose when to publish, or publish now.';
        }

        if (! $draft && $scheduledAt && ! $now && $scheduledAt->lt(now()->subMinute())) {
            $errors['scheduled_at'][] = 'That time is in the past.';
        }

        foreach ($accounts as $id => $account) {
            $text = trim(($overrides[$id] ?? null) ?: $body);
            if ($link && ! str_contains($text, $link)) {
                $text = trim($text."\n\n".$link);
            }

            $limits = $account->provider->limits();
            $label = $account->displayName();
            $length = $account->provider->length($text);

            if ($draft) {
                continue;
            }

            if (! $account->is_active || $account->needs_reauth) {
                $errors['account_ids'][] = "{$label} needs to be reconnected.";
            }
            if ($text === '' && $media === []) {
                $errors["overrides.{$id}"][] = "{$label}: write something or attach media.";
            }
            if ($length > $limits['max_chars']) {
                $errors["overrides.{$id}"][] = "{$label}: {$length}/{$limits['max_chars']} characters.";
            }
            if (count($media) > $limits['max_media']) {
                $errors['media'][] = "{$label} allows at most {$limits['max_media']} attachments.";
            }
            if ($limits['requires_media'] && $media === []) {
                $errors['media'][] = "{$label} requires at least one image.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(array_map('array_values', array_map('array_unique', $errors)));
        }
    }
}
