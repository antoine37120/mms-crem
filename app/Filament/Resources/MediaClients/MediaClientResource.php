<?php

namespace App\Filament\Resources\MediaClients;

use App\Filament\Resources\MediaClients\Pages\CreateMediaClient;
use App\Filament\Resources\MediaClients\Pages\EditMediaClient;
use App\Filament\Resources\MediaClients\Pages\ListMediaClients;
use App\Filament\Resources\MediaClients\Schemas\MediaClientForm;
use App\Filament\Resources\MediaClients\Tables\MediaClientsTable;
use App\Models\MediaClient;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MediaClientResource extends Resource
{
    protected static ?string $model = MediaClient::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Clients Media';

    public static function form(Schema $schema): Schema
    {
        return MediaClientForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MediaClientsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMediaClients::route('/'),
            'create' => CreateMediaClient::route('/create'),
            'edit' => EditMediaClient::route('/{record}/edit'),
        ];
    }
}
