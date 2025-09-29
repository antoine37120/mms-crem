<?php

namespace App\Filament\Pages;

use App\Models\Fond;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

use Filament\Facades\Filament;


class HierarchyExplorer extends Page
{
    use InteractsWithForms;

    // Icône pour la navigation
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-folder';

    // Groupe de navigation selon votre documentation
    protected static string|null|\UnitEnum $navigationGroup = 'Explorateur';

    // Label dans la navigation
    protected static ?string $navigationLabel = 'Vue Hiérarchique';

    // Titre de la page
    protected static ?string $title = 'Explorateur Hiérarchique';

    // Ordre dans la navigation
    protected static ?int $navigationSort = 1;
    // Slug de la page pour l'URL
    protected static ?string $slug = 'hierarchy-explorer';

    protected string $view = 'filament.pages.hierarchy-explorer';

    public string $panelUrl = '';
    public ?int $selectedFondId = null;


    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Select::make('selectedFondId')
                    //->label('Sélectionner un fonds')
                    ->hiddenLabel()
                    ->placeholder('Choisir un fonds...')
                    ->options(function () {
                        return Fond::all()->pluck('code', 'id')->toArray();
                    })
                    //->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn () => $this->dispatch('fond-changed', fondId: $this->selectedFondId))
                    ->prefixIcon('heroicon-o-building-library')
                    ->prefixIconColor('gray'),
            ])
            ->columns(1);
    }

    public function mount(): void
    {
        $defaultFondId = request()->get('fond_id');

        if (!$defaultFondId) {
            $defaultFondId = Fond::orderBy('code')->first()?->id;
        }
        $this->selectedFondId = $defaultFondId;

        $this->form->fill([
            'selectedFondId' => $defaultFondId,
        ]);


        $this->panelUrl = Filament::getUrl();
    }
    public function getTitle(): string|Htmlable
    {
        return 'Explorateur Hiérarchique';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Navigation dans l\'arborescence complète';
    }

    protected function getViewData(): array
    {
        return [
            'focusType' => request()->get('focus'),
            'focusId' => request()->get('id'),
        ];
    }

}
