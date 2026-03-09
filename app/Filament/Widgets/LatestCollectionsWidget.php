<?php

namespace App\Filament\Widgets;

use App\Models\Collection;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestCollectionsWidget extends BaseWidget
{
    protected static ?string $heading = 'Dernières collections';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Collection::query()->latest()->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Titre')
                    ->limit(30),
                Tables\Columns\TextColumn::make('code')
                    ->label('Cote'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y')
                    ->label('Créé le'),
            ])
            ->paginated(false);
    }
}
