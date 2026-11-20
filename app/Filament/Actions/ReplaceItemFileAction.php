<?php

namespace App\Filament\Actions;

use App\Exceptions\ItemFileReplacementException;
use App\Models\Item;
use App\Models\PendingFile;
use App\Services\ItemFileReplacer;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

/**
 * Action Filament partagée « Remplacer le fichier ».
 *
 * Utilisée sur les fiches item / média associé et sur les lignes des
 * SubItemsRelationManager. Le fichier est choisi parmi les PendingFile
 * complétés de l'utilisateur (upload par morceaux existant).
 */
final class ReplaceItemFileAction
{
    /**
     * @param  (Closure(): ?Item)|null  $recordResolver  Repli si aucun $record n'est injecté
     */
    public static function make(?Closure $recordResolver = null): Action
    {
        $resolveItem = static function (?Model $record = null) use ($recordResolver): ?Item {
            if ($record instanceof Item) {
                return $record;
            }

            if ($recordResolver !== null) {
                $resolved = $recordResolver();

                return $resolved instanceof Item ? $resolved : null;
            }

            return null;
        };

        return Action::make('replaceFile')
            ->label('Remplacer le fichier')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->modalHeading('Remplacer le fichier original')
            ->modalDescription(
                'L’identifiant, la cote et les métadonnées descriptives sont conservés. '
                .'Le fichier, ses métadonnées techniques et ses dérivés (HLS, forme d’onde) sont remplacés.'
            )
            ->modalSubmitActionLabel('Remplacer')
            ->schema([
                Select::make('pending_file_id')
                    ->label('Fichier envoyé')
                    ->placeholder('Choisir un fichier dans « Mes fichiers »')
                    ->required()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => self::pendingFileOptions($search))
                    ->getOptionLabelUsing(fn ($value): ?string => self::pendingFileLabel($value)),
                Checkbox::make('delete_old')
                    ->label("Supprimer l'ancien original")
                    ->helperText(
                        'Décoché, l’ancien fichier est archivé dans items/_remplaces. '
                        .'Cochée, il est définitivement supprimé.'
                    )
                    ->default(false),
            ])
            ->visible(fn (?Model $record = null): bool => self::canReplace($resolveItem($record)))
            ->action(function (array $data, ?Model $record = null) use ($resolveItem): void {
                $item = $resolveItem($record);

                if ($item === null) {
                    return;
                }

                $pendingFile = PendingFile::find($data['pending_file_id'] ?? null);

                if (! $pendingFile instanceof PendingFile) {
                    Notification::make()
                        ->title('Fichier introuvable')
                        ->body("Le fichier envoyé sélectionné n'existe plus.")
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $result = app(ItemFileReplacer::class)->replace(
                        $item,
                        $pendingFile,
                        (bool) ($data['delete_old'] ?? false),
                    );
                } catch (ItemFileReplacementException $exception) {
                    Notification::make()
                        ->title('Remplacement impossible')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                $body = $result->oldPath !== null
                    ? "Ancien fichier : {$result->oldPath} → nouveau : {$result->newPath}."
                    : "Nouveau fichier : {$result->newPath}.";

                if ($result->archivePath !== null) {
                    $body .= " Ancien original archivé dans {$result->archivePath}.";
                }

                if ($result->oldDeleted) {
                    $body .= ' Ancien original supprimé.';
                }

                Notification::make()
                    ->title("Fichier de l'item {$result->item->code} remplacé")
                    ->body($body)
                    ->success()
                    ->send();

                foreach ($result->warnings as $warning) {
                    Notification::make()
                        ->title('Avertissement')
                        ->body($warning)
                        ->warning()
                        ->send();
                }

                $item->refresh();
            });
    }

    private static function canReplace(?Item $item): bool
    {
        return $item instanceof Item
            && filled($item->file_path)
            && (auth()->user()?->can('update', $item) ?? false);
    }

    /**
     * @return array<int, string>
     */
    private static function pendingFileOptions(string $search): array
    {
        $user = auth()->user();

        if ($user === null) {
            return [];
        }

        $query = PendingFile::query()->completed();

        if (! $user->isSuperAdmin()) {
            $query->where('user_id', $user->id);
        }

        if (filled($search)) {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('original_name', 'like', "%{$search}%")
                    ->orWhere('file_extension', 'like', "%{$search}%");
            });
        }

        return $query
            ->latest('id')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (PendingFile $file): array => [$file->id => self::formatPendingFile($file)])
            ->all();
    }

    private static function pendingFileLabel(mixed $value): ?string
    {
        $file = PendingFile::find($value);

        return $file instanceof PendingFile ? self::formatPendingFile($file) : null;
    }

    private static function formatPendingFile(PendingFile $file): string
    {
        return sprintf('%s (%s)', $file->original_name, Number::fileSize((int) $file->file_size));
    }
}
