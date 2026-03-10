<?php
use App\Models\Collection;
use Filament\Tables\Columns\TextColumn;

$col = TextColumn::make('corpuses.code')
    ->listWithLineBreaks()
    ->formatStateUsing(function ($state, $record) {
        var_dump(['state' => $state, 'record_class' => get_class($record)]);
        return $state;
    });

$col->record(Collection::first());
$state = $col->getState();
$formatted = $col->getFormattedState();
echo json_encode(['state' => $state, 'formatted' => $formatted]);
