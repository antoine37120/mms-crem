<?php

namespace App\Filament\Widgets;

use App\Models\ItemView;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CountryRankingWidget extends BaseWidget
{
    protected static ?string $heading = 'Classement des pays';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ItemView::query()
                    ->select('country', DB::raw('count(*) as views_count'), DB::raw('MAX(id) as id'))
                    ->groupBy('country')
                    ->orderByDesc('views_count')
            )
            ->columns([
                Tables\Columns\TextColumn::make('country')
                    ->label('Pays')
                    ->state(fn ($record) => $record->country ?? 'Inconnu')
                    ->sortable(),
                Tables\Columns\TextColumn::make('views_count')
                    ->label('Vues')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultPaginationPageOption(20)
            ->paginated([10, 20, 50, 'all']);
    }
}
