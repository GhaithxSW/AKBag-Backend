<?php

namespace App\Filament\Resources\FeaturedImageResource\Pages;

use App\Filament\Resources\FeaturedImageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFeaturedImage extends EditRecord
{
    protected static string $resource = FeaturedImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
