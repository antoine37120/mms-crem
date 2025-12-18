<?php

namespace App\Filament\Widgets;

use App\Models\ItemView;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Carbon\Carbon;

class ViewsOverTimeChart extends ChartWidget
{
    protected static ?string $heading = 'Vues par jour';

    protected static ?int $sort = 1;

    // Make it span full width if needed, or stick to default
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $data = Trend::model(ItemView::class)
            ->between(
                start: now()->subDays(30),
                end: now(),
            )
            ->perDay()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Vues',
                    'data' => $data->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#3b82f6',
                ],
            ],
            'labels' => $data->map(fn (TrendValue $value) => Carbon::parse($value->date)->format('d/m')),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
