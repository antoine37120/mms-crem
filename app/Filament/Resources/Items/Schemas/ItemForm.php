<?php

namespace App\Filament\Resources\Items\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Schema;
use Filament\Forms\Components\MorphToSelect;

use App\Models\Fond;
use App\Models\Corpus;
use App\Models\Collection;;
use App\Models\Item;;

class ItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                MorphToSelect::make('itemable')
                    ->label('Item pour')
                    ->types([
                        MorphToSelect\Type::make(Fond::class)
                            ->titleAttribute('code'), // Fond utilise le code simple
                        MorphToSelect\Type::make(Corpus::class)
                            ->titleAttribute('code')
                            ->getOptionLabelFromRecordUsing(fn (Corpus $record): string => "{$record->full_code}"),
                        MorphToSelect\Type::make(Collection::class)
                            ->titleAttribute('code')
                            ->getOptionLabelFromRecordUsing(fn (Collection $record): string => "{$record->full_code}"),
                        MorphToSelect\Type::make(Item::class)
                            ->titleAttribute('code')
                            ->getOptionLabelFromRecordUsing(fn (Item $record): string => "{$record->full_code}"),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->required(),
                Select::make('item_type_id')
                    ->relationship('itemType', 'name')
                    ->default(null),
                TextInput::make('code')
                    ->required(),
                TextInput::make('title')
                    ->default(null),
                TextInput::make('language_code')
                    ->default(null),
                FileUpload::make('file_path')
                    ->required()
                    ->acceptedFileTypes(['audio/*', 'video/*', 'image/*', 'application/pdf'])
                    ->storeFileNamesIn('file_name'),

                /*TextInput::make('file_name')
                    ->required(),
                TextInput::make('file_size')
                    ->required()
                    ->numeric(),
                TextInput::make('file_type')
                    ->required(),
                TextInput::make('file_extension')
                    ->required(),
                TextInput::make('duration')
                    ->numeric()
                    ->default(null),
                DatePicker::make('upload_date')
                    ->required(),
                TextInput::make('uploaded_by')
                    ->required()
                    ->numeric(),
                TextInput::make('created_by')
                    ->required()
                    ->numeric(),*/
            ]);
    }
}
