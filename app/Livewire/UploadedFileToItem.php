<?php

namespace App\Livewire;

use App\Models\Collection;
use App\Models\Corpus;
use App\Models\Fond;
use App\Models\Item;
use App\Models\ItemType;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rules\Unique;
use Livewire\Component;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Http\File;
use Filament\Notifications\Notification;

class UploadedFileToItem extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public ?array $data = [];
    public $pending_file_to_item;

    public function mount($pending_file_to_item): void
    {
        $this->pending_file_to_item = $pending_file_to_item;
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
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
                    ->modifyTypeSelectUsing(fn (Select $select): Select => $select->default('App\Models\Collection'))

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
                                        return $rule->where('code', $get('code_prefix').'_'.$get('code_suffix'));
                                    }
                                    return $rule->where('code', $get('code_prefix'));
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
                                //->unique(ignoreRecord: true)
                                ->unique(modifyRuleUsing: function (Unique $rule, Get $get) {
                                    if($get('code_suffix') != '') {
                                        return $rule->where('code', $get('code_prefix').'_'.$get('code_suffix'))
                                            ->where('file_extension',$get('file_extension'));
                                    }
                                    return $rule->where('code', $get('code_prefix'))
                                        ->where('file_extension',$get('file_extension'));
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
                    ->default(null),

                Hidden::make('is_sub')
                    ->default(false),
                TextInput::make('file_path')
                    ->default($this->pending_file_to_item->file_path)
                    ->required(),
                TextInput::make('file_name')
                    ->default($this->pending_file_to_item->original_name),
                TextInput::make('file_size')
                    ->default($this->pending_file_to_item->file_size)
                    ->required(),
                TextInput::make('file_type')
                    ->default($this->pending_file_to_item->file_type)
                    ->required(),
                TextInput::make('file_extension')
                    ->default($this->pending_file_to_item->file_extension)
                    ->required(),
                TextInput::make('duration')
                    ->default(null),
                TextInput::make('upload_date')
                    ->default($this->pending_file_to_item->created_at)
                    ->required(),
                TextInput::make('uploaded_by')
                    ->default($this->pending_file_to_item->user_id)
                    ->required(),
                TextInput::make('created_by')
                    ->default(auth()->user()->id)
                    ->required(),
            ])
            ->statePath('data')
            ->model(Item::class)
            ->columns(2);
    }

    public function createItem(): void
    {
        $data = $this->form->getState();

        $data['code'] = $data['code_prefix'] ;
        if (isset($data['code_suffix']) && $data['code_suffix'] != '') {
            $data['code'] = $data['code'].'_'.$data['code_suffix'] ;
        }

        // Log avant l'opération
        Log::info('Tentative de création d\'un item', [
            'form_data' => $data,
            'user_id' => auth()->id(),
            'timestamp' => now()
        ]);

        try {


            // Créer le chemin basé sur la date de création du pending file
            $createdAt = Carbon::parse($this->pending_file_to_item->created_at);
            //$datePath = 'items/' . $createdAt->format('Y/m/d') . '';
            // Pas de rangement spécifique à ce stade, le modèle s'en chargera au hook de sauvegarde
            $datePath = '';

            // Générer un nom de fichier unique pour éviter les conflits
            $fileName = $data['code']  . '.' . $data['file_extension'] ;
            $newFilePath = $fileName;

            // Déplacer le fichier depuis le storage temporaire vers original_medias
            $currentFilePath = $this->pending_file_to_item->file_path;

            // Vérifier si le fichier source existe
            if (!Storage::disk('local')->exists($currentFilePath)) {
                throw new \Exception("Le fichier source n'existe pas : " . $currentFilePath);
            }

            // Créer le répertoire de destination s'il n'existe pas
            Storage::disk('original_medias')->makeDirectory($datePath);
            //$new_path = Storage::disk('original_medias')->path($datePath);

            $old_file_path = Storage::disk('local')->path($currentFilePath) ;

            // Log avant l'opération
            Log::info('Tentative de création du nouveau fichier', [
                'old_file_path' => $old_file_path,
                //'new_path' => $new_path,
                'file_name' => $fileName,
                '$newFilePath' => $newFilePath,
            ]);

            // Copier le fichier vers le nouveau storage
            //$fileContent = Storage::disk('local')->get($currentFilePath);
            //Storage::disk('original_medias')->put($newFilePath, $fileContent);
            // Ici, on met à la racine du dossier et on laisse le modèle ranger au hook d'enregistrement
             Storage::disk('original_medias')->putFileAs($datePath, new File($old_file_path), $fileName);

            // Mettre à jour le chemin du fichier dans les données
            $data['file_path'] = $newFilePath;

            // Log du déplacement de fichier
            Log::info('Fichier déplacé vers original_medias', [
                'ancien_chemin' => $currentFilePath,
                'nouveau_chemin' => $newFilePath,
                'user_id' => auth()->id(),
                'timestamp' => now()
            ]);

            $record = Item::create($data);

            // Supprimer l'ancien fichier après création réussie de l'item
            Storage::disk('local')->delete($currentFilePath);

            Log::info('Ancien fichier temporaire supprimé', [
                'chemin_supprime' => $currentFilePath,
                'user_id' => auth()->id(),
                'timestamp' => now()
            ]);

            // Log après succès
            Log::info('Item créé avec succès', [
                'item_id' => $record->id,
                'item_data' => $record->toArray(),
                'user_id' => auth()->id(),
                'timestamp' => now()
            ]);

            Notification::make()
                ->title('Item '.$record->code.' créé avec succès.')
                ->success()
                ->send();

            $this->dispatch('pending-file-to-item-end') ;

            //$this->dispatch('close-modal', id: 'pending-files-to-item-modal');
;

        } catch (\Exception $e) {
            // Log de l'erreur
            Log::error('Erreur lors de la création de l\'item', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'form_data' => $this->form->getState(),
                'user_id' => auth()->id(),
                'timestamp' => now()
            ]);

            $this->addError('form', 'Erreur lors de la création de l\'item : ' . $e->getMessage());
            return;
        }



        $this->form->model($record)->saveRelationships();
    }

    public function render(): View
    {
        return view('livewire.uploaded-file-to-item');
    }
}
