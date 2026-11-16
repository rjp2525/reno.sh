<?php

declare(strict_types=1);

namespace App\Filament\Resources\SocialPostResource\Pages;

use App\Enums\SocialPostStatus;
use App\Filament\Resources\SocialPostResource;
use App\Services\Social\SocialComposer;
use App\Services\Social\SocialScheduler;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditSocialPost extends EditRecord
{
    use ComposesSocialPosts;

    protected static string $resource = SocialPostResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->formStateFor($this->record);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(SocialComposer::class)->update($record, $this->composerPayload($data));
        } catch (ValidationException $e) {
            $this->rethrowAsFormErrors($e);
        }
    }

    public function getSubheading(): ?string
    {
        return $this->record->status->getLabel()
            .($this->record->scheduled_at ? ' · '.$this->record->scheduled_at->timezone(config('app.timezone'))->format('D M j, g:i A') : '');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('retry')
                ->label('Retry failed')
                ->icon('heroicon-m-arrow-path')
                ->color('warning')
                ->visible(fn () => in_array($this->record->status, [SocialPostStatus::FAILED, SocialPostStatus::PARTIALLY_PUBLISHED], true))
                ->action(function (SocialScheduler $scheduler) {
                    $scheduler->dispatch($this->record, onlyFailed: true);
                    $this->refreshFormData([]);
                })
                ->successNotificationTitle('Retrying'),
            Actions\DeleteAction::make(),
        ];
    }

    /** Published posts are read-only. */
    protected function getFormActions(): array
    {
        return $this->record->status->isEditable() ? parent::getFormActions() : [];
    }
}
