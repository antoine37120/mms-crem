<?php

namespace App\Filament\Widgets;

use App\Models\Item;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestItemsWidget extends BaseWidget
{
    protected static ?string $heading = 'Derniers ajouts d\'items';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Item::query()->latest()->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Titre')
                    ->limit(30)
                    ->tooltip(fn (Item $record): string => $record->title),
                Tables\Columns\TextColumn::make('code')
                    ->label('Code'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->label('Créé le'),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Par'),
            ])
            ->paginated(false);
    }
}
