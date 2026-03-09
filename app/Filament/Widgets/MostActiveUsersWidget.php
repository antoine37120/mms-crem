<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class MostActiveUsersWidget extends BaseWidget
{
    protected static ?string $heading = 'Utilisateurs les plus actifs';

    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->withCount(['createdItems', 'createdFonds', 'createdCorpuses', 'createdCollections'])
                    ->havingRaw('(created_items_count + created_fonds_count + created_corpuses_count + created_collections_count) > 0')
                    ->orderByRaw('(created_items_count + created_fonds_count + created_corpuses_count + created_collections_count) DESC')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Utilisateur')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('created_items_count')
                    ->label('Items')
                    ->sortable()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('created_fonds_count')
                    ->label('Fonds')
                    ->sortable()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('created_corpuses_count')
                    ->label('Corpus')
                    ->sortable()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('created_collections_count')
                    ->label('Collections')
                    ->sortable()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('total_activity')
                    ->label('Total')
                    ->state(fn (User $record): int => $record->created_items_count + $record->created_fonds_count + $record->created_corpuses_count + $record->created_collections_count)
                    ->badge()
                    ->color('primary'),
            ])
            ->paginated(false);
    }
}
