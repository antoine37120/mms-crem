<?php

namespace App\Filament\Resources\ItemTypes\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ItemTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->required(),
                TextInput::make('suffix')
                    ->label('Suffixe')
                    ->required(),
                Textarea::make('description')
                    ->label('Description')
                    ->default(null)
                    ->columnSpanFull(),
                Toggle::make('requires_language')
                    ->label('Nécessite une langue')
                    ->required(),
                TagsInput::make('allowed_extensions')
                    ->label('Extensions autorisées')
                    ->placeholder('Ajouter une extension')
                    ->helperText('Appuyez sur Entrée pour ajouter une extension (ex: pdf, jpg, mp4). Laissez vide pour tout autoriser.')
                    ->separator(',')
                    ->suggestions([
                        'pdf', 'jpg', 'jpeg', 'png', 'gif', 'svg',
                        'mp4', 'mkv', 'avi', 'mov', 'webm',
                        'mp3', 'wav', 'ogg', 'flac',
                        'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv', 'vtt',
                    ])
                    ->default(null)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Actif')
                    ->required(),
                Select::make('created_by')
                    ->label('Créé par')
                    ->relationship('creator', 'name')
                    ->default(auth()->id())
                    ->required()
                    ->searchable()
                    ->preload(),
            ]);
    }
}
