<?php

declare(strict_types=1);

namespace App\Filament\Resources\SocialPostResource\Pages;

use App\Models\SocialPost;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/** Maps the Filament form to SocialComposer input. */
trait ComposesSocialPosts
{
    protected function composerPayload(array $data): array
    {
        $when = $data['when'] ?? 'later';

        return [
            'body' => $data['body'] ?? '',
            'account_ids' => $data['account_ids'] ?? [],
            'overrides' => $data['overrides'] ?? [],
            'media' => array_values(array_filter((array) ($data['media'] ?? []))),
            'link_url' => $data['link_url'] ?? null,
            'scheduled_at' => $when === 'now' ? null : ($data['scheduled_at'] ?? null),
            'publish_now' => $when === 'now',
            'draft' => $when === 'draft',
        ];
    }

    protected function formStateFor(SocialPost $post): array
    {
        $post->loadMissing('targets');

        return [
            'body' => $post->body,
            'link_url' => $post->link_url,
            'media' => collect($post->media ?? [])->pluck('path')->all(),
            'account_ids' => $post->targets->pluck('social_account_id')->map(fn ($id) => (string) $id)->all(),
            'overrides' => $post->targets->filter(fn ($t) => filled($t->body_override))
                ->mapWithKeys(fn ($t) => [(string) $t->social_account_id => $t->body_override])->all(),
            'scheduled_at' => $post->scheduled_at,
            'when' => $post->status->value === 'draft' ? 'draft' : 'later',
        ];
    }

    /** Turn composer validation errors into form field errors + a toast, then halt. */
    protected function rethrowAsFormErrors(ValidationException $e): never
    {
        Notification::make()->danger()->title('Check the post')->body(collect($e->errors())->flatten()->first())->send();

        throw ValidationException::withMessages(collect($e->errors())
            ->mapWithKeys(fn ($messages, $key) => ['data.'.$key => $messages])
            ->all());
    }
}
