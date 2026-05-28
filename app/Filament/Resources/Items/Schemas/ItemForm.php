<?php

namespace App\Filament\Resources\Items\Schemas;

use App\Models\Collection;
use App\Models\Corpus;
use App\Models\Fond;
use App\Models\Item;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class ItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                MorphToSelect::make('itemable')
                    ->label('Item pour')
                    ->types([
                        /*MorphToSelect\Type::make(Fond::class)
                            ->titleAttribute('code'), // Fond utilise le code simple
                        MorphToSelect\Type::make(Corpus::class)
                            ->titleAttribute('code')
                            ->getOptionLabelFromRecordUsing(fn (Corpus $record): string => "{$record->full_code}"),*/
                        MorphToSelect\Type::make(Collection::class)
                            ->titleAttribute('code')
                            ->getOptionLabelFromRecordUsing(fn (Collection $record): string => "{$record->full_code}"),
                        /*MorphToSelect\Type::make(Item::class)
                            ->titleAttribute('code')
                            ->getOptionLabelFromRecordUsing(fn (Item $record): string => "{$record->full_code}"),*/
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->live()
                    ->preload()
                    ->searchable()->optionsLimit(50)
                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                        // Réinitialiser le champ langue si le type change
                        if ($get('itemable_type') && $get('itemable_id')) {

                            $itemableType = $get('itemable_type');
                            $itemableId = $get('itemable_id');
                            $model = app($itemableType)->find($itemableId);
                            $set('code_prefix', $model->code);
                        }
                    })
                    ->required(),
                Grid::make()
                    ->schema([
                        FusedGroup::make([
                            TextInput::make('code_prefix')
                                ->label('Code de l\'Item')
                                ->autofocus(false)
                                ->default(function ($state, Set $set, Get $get): string {
                                    if (! $get('itemable_type') || ! $get('itemable_id')) {
                                        return '';
                                    }
                                    $itemableType = $get('itemable_type');
                                    $itemableId = $get('itemable_id');
                                    $model = app($itemableType)->find($itemableId);

                                    return $model->code;
                                })
                                ->disabled()
                                ->dehydrated()
                                ->required()
                                // ->unique(ignoreRecord: true)
                                ->unique(modifyRuleUsing: function (Unique $rule, Get $get) {
                                    if ($get('code_suffix') != '') {
                                        return $rule->where('code', $get('code_prefix').'_'.$get('code_suffix'))
                                            ->where('file_extension', $get('file_extension'));
                                    }

                                    return $rule->where('code', $get('code_prefix'))
                                        ->where('file_extension', $get('file_extension'));
                                })
                                ->placeholder('Ex: CNRSMH_Arnaud_001')
                                ->columnSpan(1),
                            TextInput::make('code_suffix')
                                ->label('Code de l\'Item')
                                ->prefix('_')
                                ->autofocus(false)
                                ->required(false)
                                ->placeholder('Ex: TRA_en ou 02')
                                ->columnSpan(1),
                        ])
                            ->label('Cote')
                            ->extraAttributes(['class' => 'item_code_wrapper'])
                            ->columns(2)
                            ->columnSpan(2),
                        Text::make(<<<'JS'
                                    $get('code_suffix') ? `Cote enregistrée :
                                     ${$get('code_prefix')}_${$get('code_suffix')}` : `Cote enregistrée : ${$get('code_prefix')}`
                                    JS)
                            ->js()
                            ->columnSpan(2),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),

                TextInput::make('title')
                    ->label('Titre')
                    ->default(null),
                TextInput::make('file_path')
                    ->label('Chemin du fichier')
                    ->disabled()
                    ->visible(fn ($record) => $record && $record->file_path),
                Section::make('Accès')
                    ->schema([
                        \Filament\Forms\Components\Select::make('public_access')
                            ->label('Accès public')
                            ->options(config('mms.access.options'))
                            ->default(config('mms.access.defaults.item'))
                            ->helperText('Détermine si ce fichier est accessible publiquement. "Public" = accessible sans token. "Restreint" = token requis.'),
                    ]),

                TextInput::make('file_extension')
                    ->label('Extension du fichier'),
                Hidden::make('is_sub')
                    ->default(false),
                // Auto-remplir l'utilisateur connecté
                Hidden::make('created_by')
                    ->default(auth()->id()),
                /*TextInput::make('file_name')
                    ->required(),
                TextInput::make('file_size')
                    ->required()
                    ->numeric(),
                TextInput::make('file_type')
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
