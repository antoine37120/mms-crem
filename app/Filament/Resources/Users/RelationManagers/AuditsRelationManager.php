<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager as BaseAuditsRelationManager;

class AuditsRelationManager extends BaseAuditsRelationManager
{
    protected static string $relationship = 'auditsAsUser';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return "Actions de l'utilisateur";
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->heading('Historique des actions')
            ->columns([
                TextColumn::make('auditable_type')
                    ->label("Type d'entité")
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->sortable()
                    ->searchable(),
                ...$table->getColumns(),
            ]);
    }
}
