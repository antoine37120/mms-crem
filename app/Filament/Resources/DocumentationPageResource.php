<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentationPageResource\Pages;
use App\Models\DocumentationPage;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Openplain\FilamentTreeView\Tree;
use Openplain\FilamentTreeView\Fields\TextField;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use BackedEnum;
use UnitEnum;

class DocumentationPageResource extends Resource
{
    protected static ?string $model = DocumentationPage::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Gestion de la documentation';

    protected static ?string $modelLabel = 'Page de documentation';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('title')
                    ->label('Titre')
                    ->required()
                    ->maxLength(255),
                Select::make('parent_id')
                    ->label('Page parente')
                    ->relationship('parent', 'title')
                    ->searchable()
                    ->preload(),
                MarkdownEditor::make('content')
                    ->label('Contenu')
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(4)
                    ->schema([
                        Section::make('Contenu')
                            ->schema([
                                TextEntry::make('content')
                                    ->hiddenLabel()
                                    ->markdown()
                                    ->prose()
                            ])
                            ->columnSpan(['sm' => 4, 'md' => 3]),
                        Section::make('Navigation')
                            ->schema([
                                ViewEntry::make('navigation')
                                    ->view('filament.infolists.entries.documentation-navigation')
                            ])
                            ->columnSpan(['sm' => 4, 'md' => 1]),
                    ])->columnSpanFull(),
            ]);
    }

    public static function tree(Tree $tree): Tree
    {
        return $tree
            ->fields([
                TextField::make('title'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentationPages::route('/'),
            'create' => Pages\CreateDocumentationPage::route('/create'),
            'edit' => Pages\EditDocumentationPage::route('/{record}/edit'),
            'view' => Pages\ViewDocumentationPage::route('/{record}'),
        ];
    }
}
