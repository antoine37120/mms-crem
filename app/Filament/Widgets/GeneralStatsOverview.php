<?php

namespace App\Filament\Widgets;

use App\Models\Item;
use App\Models\ItemView;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GeneralStatsOverview extends BaseWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        return [
            Stat::make('Total Items', Item::count())
                ->description('Nombre total d\'items dans la base')
                ->descriptionIcon('heroicon-m-document-duplicate')
                ->chart([Item::where('created_at', '>=', now()->subDays(7))->count(), Item::count()])
                ->color('success'),

            Stat::make('Total Vues', ItemView::count())
                ->description('Consultations totales')
                ->descriptionIcon('heroicon-m-eye')
                ->chart([
                    ItemView::where('created_at', '>=', now()->subDays(7))->count(),
                    ItemView::count()
                ])
                ->color('info'),
        ];
    }
}
