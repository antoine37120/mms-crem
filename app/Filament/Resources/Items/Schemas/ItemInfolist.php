<?php

namespace App\Filament\Resources\Items\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ItemInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('itemable_type'),
                TextEntry::make('itemable.full_code')
                    ->numeric(),
                TextEntry::make('itemType.name')
                    ->numeric(),
                TextEntry::make('code'),
                TextEntry::make('title'),
                TextEntry::make('language_code'),
                TextEntry::make('file_path'),
                TextEntry::make('file_name'),
                TextEntry::make('file_size')
                    ->numeric(),
                TextEntry::make('file_type'),
                TextEntry::make('file_extension'),
                TextEntry::make('duration')
                    ->numeric(),
                TextEntry::make('upload_date')
                    ->date(),
                TextEntry::make('uploaded_by')
                    ->numeric(),
                TextEntry::make('created_by')
                    ->numeric(),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('deleted_at')
                    ->dateTime(),
            ]);
    }
}
