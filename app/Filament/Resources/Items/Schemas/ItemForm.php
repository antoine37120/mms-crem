<?php

namespace App\Filament\Resources\Items\Schemas;

use App\Models\ItemType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Forms\Components\MorphToSelect;
use App\Models\Fond;
use App\Models\Corpus;
use App\Models\Collection;;
use App\Models\Item;
use Illuminate\Validation\Rules\Unique;
use Filament\Schemas\Components\Grid;

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
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                        // Réinitialiser le champ langue si le type change
                        if ($get('itemable_type') && $get('itemable_id')) {

                            $itemableType = $get('itemable_type') ;
                            $itemableId = $get('itemable_id') ;
                            $model = app($itemableType)->find($itemableId);
                            $set('code_prefix', $model->code);
                        }
                    })
                    ->required(),

                Select::make('item_type_id')
                    ->label('Type d\'Item')
                    ->relationship('itemType', 'name')
                    ->placeholder('Sélectionner un type (optionnel)')
                    ->searchable()
                    ->preload()
                    ->live() // ← IMPORTANT : remplace "reactive()"
                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                        // Réinitialiser le champ langue si le type change
                        if (!$state) {
                            $set('language_code', null);
                        }
                        if (!$state) {
                            return ;
                        }
                        $suffix = ItemType::find($state)->suffix ;
                        $itemLang = $get('language_code');
                        if ($suffix) {
                            $set('code_suffix', '_'.$suffix);
                        } else {
                            $set('code_suffix', '_'.$state.'_'.$itemLang);
                        }
                    }),
                TextInput::make('language_code')
                    ->label('Code Langue')
                    ->placeholder('Ex: fr, en')
                    ->maxLength(5)
                    ->live()
                    ->visible(function (Get $get): bool {
                        $itemTypeId = $get('item_type_id');
                        if (!$itemTypeId) {
                            return false;
                        }
                        $itemType = ItemType::find($itemTypeId);
                        return $itemType && $itemType->requires_language;
                    })
                    ->required(function (Get $get): bool {
                        $itemTypeId = $get('item_type_id');
                        if (!$itemTypeId) {
                            return false;
                        }
                        $itemType = ItemType::find($itemTypeId);
                        return $itemType && $itemType->requires_language;
                    })
                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                        // Réinitialiser le champ langue si le type change
                        $itemTypeId = $get('item_type_id');
                        $itemType = ItemType::find($itemTypeId)->suffix ;
                        if($itemType) {
                            if (!$state) {
                                $set('code_suffix', $itemType);
                            } else {
                                $set('code_suffix', $itemType . '_' . $state);
                            }
                        }
                    }),
                Grid::make()
                    ->schema([
                        FusedGroup::make([
                            TextInput::make('code_prefix')
                                ->label('Code de l\'Item')
                                ->autofocus(false)
                                ->default(function ($state, Set $set, Get $get): string {
                                    if (!$get('itemable_type') || !$get('itemable_id')) {
                                        return '';
                                    }
                                    $itemableType = $get('itemable_type') ;
                                    $itemableId = $get('itemable_id') ;
                                    $model = app($itemableType)->find($itemableId);

                                    return $model->code ;
                                })
                                ->disabled()
                                ->dehydrated()
                                ->required()
                                //->unique(ignoreRecord: true)
                                ->unique(modifyRuleUsing: function (Unique $rule, Get $get) {
                                    if($get('code_suffix') != '') {
                                        return $rule->where('code', $get('code_prefix').'_'.$get('code_suffix'))
                                            ->where('file_extension',$get('file_extension'));
                                    }
                                    return $rule->where('code', $get('code_prefix'))
                                        ->where('file_extension',$get('file_extension'));
                                })
                                ->placeholder('Ex: CNRSMH_Arnaud_001')
                                ->columnSpan(1),
                            TextInput::make('code_suffix')
                                ->label('Code de l\'Item')
                                ->prefix('_')
                                ->autofocus(false)
                                /*->visible(function (Get $get): bool {
                                    $itemTypeId = $get('item_type_id');

                                    if (!$itemTypeId) {
                                        return false;
                                    }
                                    return true;
                                })*/
                                ->required(function (Get $get): bool {
                                    $itemTypeId = $get('item_type_id');

                                    if (!$itemTypeId) {
                                        return false;
                                    }
                                    return true;
                                })
                                ->placeholder('Ex: TRA_en ou 02')
                                ->columnSpan(1),
                            ])
                            ->label('code')
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
                FileUpload::make('file_path')
                    ->disk('original_medias')
                    ->required()
                    ->acceptedFileTypes(['audio/*', 'video/*', 'image/*', 'application/pdf'])
                    ->storeFileNamesIn('file_name')
                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                        //connaite l'extention du fichier uploadé si $state n'est pas un string
                        if (is_string($state) || $state === null) {
                            return ;
                        }
                        $file = $state;
                        $extension = $file->getClientOriginalExtension();
                        $set('file_extension', $extension);
                    }),

                TextInput::make('file_extension')
                    ->required(),
                Hidden::make('is_sub')
                    ->default(false),
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
