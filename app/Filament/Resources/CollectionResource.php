<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AlbumsResource\RelationManagers\AlbumsRelationManager;
use App\Filament\Resources\CollectionResource\Pages;
use App\Models\Collection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CollectionResource extends Resource
{
    protected static ?string $model = Collection::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Content Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('cover_image')
                    ->image()
                    ->disk('s3')
                    ->directory('collections/covers')
                    ->visibility('public')
                    ->imageResizeMode('cover')
                    ->imageCropAspectRatio('16:9')
                    ->imageResizeTargetWidth('1920')
                    ->imageResizeTargetHeight('1080'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('cover_image')
                    ->disk('s3')
                    ->width(60)
                    ->height(40)
                    ->defaultImageUrl('/images/placeholder-collection.png'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('albums_count')
                    ->label('Albums')
                    ->counts('albums')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Collection')
                    ->modalDescription(fn (Collection $record): string => $record->canBeDeleted()
                            ? 'Are you sure you want to delete this collection?'
                            : $record->getDeletionBlockReason()
                    )
                    ->modalSubmitActionLabel('Delete')
                    ->action(function (Tables\Actions\DeleteAction $action, Collection $record) {
                        if (! $record->canBeDeleted()) {
                            \Filament\Notifications\Notification::make()
                                ->title('Cannot Delete Collection')
                                ->body($record->getDeletionBlockReason())
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->delete();

                        \Filament\Notifications\Notification::make()
                            ->title('Collection Deleted')
                            ->body('The collection has been deleted successfully.')
                            ->success()
                            ->send();
                    })
                    ->disabled(fn (Collection $record): bool => ! $record->canBeDeleted())
                    ->color(fn (Collection $record): string => $record->canBeDeleted() ? 'danger' : 'gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Collections')
                        ->modalDescription('Are you sure you want to delete the selected collections?')
                        ->modalSubmitActionLabel('Delete Selected')
                        ->action(function (Tables\Actions\DeleteBulkAction $action, $records) {
                            $collectionsWithAlbums = $records->filter(fn ($record) => ! $record->canBeDeleted());

                            if ($collectionsWithAlbums->count() > 0) {
                                $blockedNames = $collectionsWithAlbums->pluck('name')->join(', ');

                                \Filament\Notifications\Notification::make()
                                    ->title('Cannot Delete Some Collections')
                                    ->body("The following collections have albums and cannot be deleted: {$blockedNames}. Remove or reassign all albums first.")
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $deletedCount = 0;
                            foreach ($records as $record) {
                                if ($record->canBeDeleted()) {
                                    $record->delete();
                                    $deletedCount++;
                                }
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Collections Deleted')
                                ->body("{$deletedCount} collection(s) deleted successfully.")
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AlbumsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCollections::route('/'),
            'create' => Pages\CreateCollection::route('/create'),
            'edit' => Pages\EditCollection::route('/{record}/edit'),
        ];
    }
}
