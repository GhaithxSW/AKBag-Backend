<?php

namespace App\Filament\Resources\CollectionResource\Pages;

use App\Filament\Resources\CollectionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCollection extends EditRecord
{
    protected static string $resource = CollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete Collection')
                ->modalDescription(fn (): string => $this->record->canBeDeleted()
                        ? 'Are you sure you want to delete this collection?'
                        : $this->record->getDeletionBlockReason()
                )
                ->modalSubmitActionLabel('Delete')
                ->action(function (Actions\DeleteAction $action) {
                    if (! $this->record->canBeDeleted()) {
                        \Filament\Notifications\Notification::make()
                            ->title('Cannot Delete Collection')
                            ->body($this->record->getDeletionBlockReason())
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->record->delete();
                    $this->redirect(CollectionResource::getUrl('index'));
                })
                ->disabled(fn (): bool => ! $this->record->canBeDeleted())
                ->color(fn (): string => $this->record->canBeDeleted() ? 'danger' : 'gray'),
        ];
    }
}
