<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class HierarchyExplorer extends Page
{

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

}
