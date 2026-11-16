<?php

declare(strict_types=1);

namespace App\Filament\Resources\SocialPostResource\Pages;

use App\Filament\Resources\SocialPostResource;
use App\Services\Social\SocialComposer;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CreateSocialPost extends CreateRecord
{
    use ComposesSocialPosts;

    protected static string $resource = SocialPostResource::class;

    protected static ?string $title = 'Compose';

    protected static bool $canCreateAnother = false;

    /** Prefill from ?date=YYYY-MM-DD (calendar) and ?account= links. */
    protected function fillForm(): void
    {
        parent::fillForm();

        if ($date = request()->query('date')) {
            $this->data['scheduled_at'] = Carbon::parse($date)->setTime(9, 0);
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(SocialComposer::class)->create($this->composerPayload($data), auth()->id());
        } catch (ValidationException $e) {
            $this->rethrowAsFormErrors($e);
        }
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label(fn () => match ($this->data['when'] ?? 'later') {
            'now' => 'Publish now',
            'draft' => 'Save draft',
            default => 'Schedule',
        })->icon('heroicon-m-paper-airplane');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return match ($this->data['when'] ?? 'later') {
            'now' => 'Publishing…',
            'draft' => 'Draft saved',
            default => 'Scheduled',
        };
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
