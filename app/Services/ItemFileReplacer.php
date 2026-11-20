<?php

namespace App\Services;

use App\Exceptions\ItemFileReplacementException;
use App\Models\Item;
use App\Models\PendingFile;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Remplace le fichier original d'un item (ou d'un média associé) sans changer
 * son identifiant, sa cote ni ses métadonnées descriptives.
 *
 * Seules les métadonnées techniques du fichier sont recalculées. Les dérivés
 * (HLS, forme d'onde, MediaVariation, ItemProcessingState) sont purgés puis
 * régénérés via MediaProcessor, une seule fois.
 */
final class ItemFileReplacer
{
    private const DISK_ORIGINAL = 'original_medias';

    private const DISK_LOCAL = 'local';

    private const DIFFUSION_DISK_DEFAULT = 'diffusion_medias';

    public function __construct(
        private readonly MediaProcessor $mediaProcessor,
        private readonly MediaVariationPathResolver $pathResolver,
    ) {}

    /**
     * @throws AuthorizationException quand l'utilisateur courant ne peut pas modifier l'item
     * @throws ItemFileReplacementException quand le fichier envoyé n'est pas utilisable
     */
    public function replace(Item $item, PendingFile $file, bool $deleteOld = false): ItemFileReplacementResult
    {
        // 1. Autorisation : rien n'est lu ni écrit avant ce contrôle.
        Gate::authorize('update', $item);

        // 2. Validation complète du PendingFile et de l'extension.
        $this->assertPendingFileUsable($file);

        $extension = $this->resolveExtension($file);
        $this->assertExtensionAllowed($item, $extension);
        $this->normalizeAndAssertCode($item);

        // 3. Empreinte et avertissement de doublon (non bloquant).
        $md5 = $this->resolveMd5($file);
        $warnings = $this->duplicateMd5Warnings($item, $md5);

        $disk = Storage::disk(self::DISK_ORIGINAL);
        $oldPath = filled($item->file_path) ? $item->file_path : null;
        $oldExists = $oldPath !== null && $disk->exists($oldPath);
        $oldExtension = $this->oldExtension($item, $oldPath);

        // 4. Copie vers un nom temporaire dans le dossier cible du jour.
        $datePath = 'items/'.now()->format('Y/m/d');
        $newPath = $datePath.'/'.$item->code.'.'.$extension;
        $tempPath = $datePath.'/.tmp-'.Str::uuid().'.'.$extension;

        $this->copyToTemporaryFile($file, $disk, $datePath, $tempPath);

        // 5. Métadonnées techniques calculées depuis le fichier temporaire.
        $absoluteTempPath = $disk->path($tempPath);
        $fileSize = (int) filesize($absoluteTempPath);
        $mimeType = $this->detectMimeType($absoluteTempPath, $file);
        $duration = $this->refreshDuration($item, $absoluteTempPath, $mimeType, $extension);

        // 6. Dossiers dérivés calculés AVANT de changer file_path.
        $derivedDirs = $this->derivedDirectories($item);

        $archivePath = null;
        $trashPath = null;
        $stagedOldPath = null;
        $newFileMoved = false;
        $pendingFilePath = $file->file_path;

        try {
            // 7. Opérations BDD + déplacements de fichiers, avec compensation.
            DB::transaction(function () use (
                $item,
                $file,
                $disk,
                $oldPath,
                $oldExists,
                $oldExtension,
                $deleteOld,
                $tempPath,
                $newPath,
                $fileSize,
                $mimeType,
                $extension,
                $md5,
                $duration,
                &$archivePath,
                &$trashPath,
                &$stagedOldPath,
                &$newFileMoved,
            ): void {
                // 7a. Purge des données dérivées.
                $item->mediaVariations()->delete();
                $item->processingStates()->delete();

                // 7b. Détacher l'ancien original avant de poser le nouveau :
                // couvre le cas où ancien et nouveau chemins coïncident.
                if ($oldExists && $oldPath !== null) {
                    if ($deleteOld) {
                        $trashPath = 'items/_remplaces/.trash/'.now()->format('Y/m/d')
                            .'/.'.Str::uuid().'.'.$oldExtension;

                        if (! $disk->move($oldPath, $trashPath)) {
                            throw ItemFileReplacementException::fileOperationFailed(
                                "mise à l'écart de l'ancien fichier"
                            );
                        }

                        $stagedOldPath = $trashPath;
                    } else {
                        $archivePath = $this->buildArchivePath($item->code, $oldExtension);

                        if (! $disk->move($oldPath, $archivePath)) {
                            throw ItemFileReplacementException::fileOperationFailed(
                                "archivage de l'ancien fichier"
                            );
                        }

                        $stagedOldPath = $archivePath;
                    }
                }

                // 7c. Déplacement atomique du temporaire vers la cible.
                if (! $disk->move($tempPath, $newPath)) {
                    throw ItemFileReplacementException::fileOperationFailed(
                        'déplacement du nouveau fichier vers sa destination'
                    );
                }

                $newFileMoved = true;

                // 7d. Un seul save() → un seul enregistrement d'audit.
                $item->forceFill([
                    'file_path' => $newPath,
                    'file_name' => $file->original_name,
                    'file_size' => $fileSize,
                    'file_type' => $mimeType,
                    'file_extension' => $extension,
                    'md5' => $md5,
                    'duration' => $duration,
                    'uploaded_by' => auth()->id() ?? $item->uploaded_by,
                    'upload_date' => now(),
                ]);

                // Le service dispatche lui-même les traitements média.
                $item->skipMediaProcessingOnUpdate = true;
                $item->save();

                // 7e. Le PendingFile consommé est supprimé (enregistrement ;
                // le fichier local est supprimé après le commit).
                $file->delete();
            });
        } catch (Throwable $exception) {
            $this->compensate($disk, $newPath, $newFileMoved, $stagedOldPath, $oldPath, $tempPath);

            throw $exception;
        } finally {
            $item->skipMediaProcessingOnUpdate = false;
        }

        // 8. Après commit : suppressions destructives et relance des traitements.
        if ($deleteOld && $trashPath !== null && ! $disk->delete($trashPath)) {
            Log::warning("ItemFileReplacer : impossible de supprimer la corbeille {$trashPath}.");
        }

        if ($pendingFilePath && ! Storage::disk(self::DISK_LOCAL)->delete($pendingFilePath)) {
            Log::warning("ItemFileReplacer : impossible de supprimer le fichier temporaire {$pendingFilePath}.");
        }

        $this->deleteDerivedDirectories($derivedDirs);
        $this->mediaProcessor->processItem($item);

        return new ItemFileReplacementResult(
            item: $item,
            oldPath: $oldPath,
            newPath: $newPath,
            archivePath: $archivePath,
            oldDeleted: $deleteOld && $oldExists,
            warnings: $warnings,
        );
    }

    private function assertPendingFileUsable(PendingFile $file): void
    {
        if ($file->upload_status !== PendingFile::STATUS_COMPLETED) {
            throw ItemFileReplacementException::pendingFileNotCompleted((string) $file->upload_status);
        }

        $local = Storage::disk(self::DISK_LOCAL);

        if (! $file->file_path || ! $local->exists($file->file_path)) {
            throw ItemFileReplacementException::pendingFileMissing();
        }

        $user = auth()->user();

        if ($user === null) {
            throw new AuthorizationException('Authentification requise pour remplacer un fichier.');
        }

        if (! $user->isSuperAdmin() && (int) $file->user_id !== (int) $user->id) {
            throw ItemFileReplacementException::pendingFileNotOwned();
        }

        $actualSize = (int) $local->size($file->file_path);

        if ($file->file_size !== null && (int) $file->file_size !== $actualSize) {
            throw ItemFileReplacementException::pendingFileSizeMismatch((int) $file->file_size, $actualSize);
        }
    }

    private function resolveExtension(PendingFile $file): string
    {
        $extension = strtolower(trim((string) $file->file_extension));

        if ($extension === '') {
            $extension = strtolower((string) pathinfo((string) $file->original_name, PATHINFO_EXTENSION));
        }

        if (! preg_match('/^[a-z0-9]{1,10}$/', $extension)) {
            throw ItemFileReplacementException::invalidExtension($extension);
        }

        return $extension;
    }

    private function assertExtensionAllowed(Item $item, string $extension): void
    {
        if (! $item->item_type_id) {
            return;
        }

        $itemType = $item->itemType;

        if ($itemType !== null && ! $itemType->isExtensionAllowed($extension)) {
            throw ItemFileReplacementException::extensionNotAllowed($extension, $itemType);
        }
    }

    /**
     * Vérifie que la cote restera identique après le passage du hook
     * Item::updating (qui réécrit code depuis code_prefix/code_suffix).
     */
    private function normalizeAndAssertCode(Item $item): void
    {
        $code = trim((string) $item->code);

        if ($code === '') {
            throw ItemFileReplacementException::missingCode();
        }

        if (str_contains($code, '/') || str_contains($code, '\\') || str_contains($code, '..')) {
            throw ItemFileReplacementException::invalidCode($code);
        }

        $prefix = (string) $item->code_prefix;
        $suffix = filled($item->code_suffix) ? (string) $item->code_suffix : null;

        if ($prefix === '') {
            // Réparation : le préfixe reprend la cote, sans changer celle-ci.
            $item->code_prefix = $code;
            $prefix = $code;

            // Un suffixe non vide serait réappliqué par Item::updating et
            // changerait la cote : on refuse plutôt que de la modifier.
            if ($suffix !== null && $prefix.'_'.$suffix !== $code) {
                throw ItemFileReplacementException::inconsistentCode($code, $prefix, $suffix);
            }
        }

        $computed = $suffix !== null ? $prefix.'_'.$suffix : $prefix;

        if ($computed !== $code) {
            throw ItemFileReplacementException::inconsistentCode($code, $prefix, $suffix);
        }
    }

    private function resolveMd5(PendingFile $file): string
    {
        $local = Storage::disk(self::DISK_LOCAL);
        $actual = md5_file($local->path($file->file_path));

        if ($actual === false) {
            throw ItemFileReplacementException::fileOperationFailed('calcul du md5');
        }

        if (filled($file->client_signature)) {
            $expected = strtolower((string) $file->client_signature);

            if (! hash_equals($expected, strtolower($actual))) {
                throw ItemFileReplacementException::pendingFileChecksumMismatch();
            }

            return $expected;
        }

        return $actual;
    }

    /**
     * @return list<string>
     */
    private function duplicateMd5Warnings(Item $item, string $md5): array
    {
        $duplicate = Item::query()
            ->where('md5', $md5)
            ->whereKeyNot($item->getKey())
            ->first();

        if ($duplicate === null) {
            return [];
        }

        return [
            "Le fichier est identique (md5) à l'item « {$duplicate->code} » : doublon possible.",
        ];
    }

    private function oldExtension(Item $item, ?string $oldPath): string
    {
        $extension = $oldPath !== null
            ? strtolower((string) pathinfo($oldPath, PATHINFO_EXTENSION))
            : '';

        if ($extension === '') {
            $extension = strtolower((string) $item->file_extension);
        }

        return $extension !== '' ? $extension : 'bin';
    }

    private function copyToTemporaryFile(PendingFile $file, $disk, string $datePath, string $tempPath): void
    {
        $source = Storage::disk(self::DISK_LOCAL)->readStream($file->file_path);

        if (! is_resource($source)) {
            throw ItemFileReplacementException::fileOperationFailed('lecture du fichier envoyé');
        }

        $disk->makeDirectory($datePath);

        try {
            $copied = $disk->writeStream($tempPath, $source);
        } catch (Throwable $exception) {
            $disk->delete($tempPath);

            throw ItemFileReplacementException::fileOperationFailed(
                'copie du nouveau fichier ('.$exception->getMessage().')'
            );
        } finally {
            if (is_resource($source)) {
                fclose($source);
            }
        }

        if (! $copied || ! $disk->exists($tempPath)) {
            $disk->delete($tempPath);

            throw ItemFileReplacementException::fileOperationFailed('copie du nouveau fichier');
        }
    }

    private function detectMimeType(string $absolutePath, PendingFile $file): string
    {
        $detected = mime_content_type($absolutePath);

        $generic = [
            false,
            'application/octet-stream',
            'application/x-empty',
            'inode/x-empty',
        ];

        if (! in_array($detected, $generic, true)) {
            return (string) $detected;
        }

        return (string) ($file->file_type ?: 'application/octet-stream');
    }

    private function refreshDuration(Item $item, string $absolutePath, string $mimeType, string $extension): ?int
    {
        $item->file_type = $mimeType;
        $item->file_extension = $extension;
        $item->duration = null;

        if (! $item->isAudio() && ! $item->isVideo()) {
            return null;
        }

        $item->processByFileType($absolutePath);

        return $item->duration;
    }

    /**
     * @return list<string>
     */
    private function derivedDirectories(Item $item): array
    {
        if (! filled($item->file_path)) {
            return [];
        }

        return [
            $this->pathResolver->variationDir($item, 'diffusion'),
            $this->pathResolver->variationDir($item, 'waveform'),
        ];
    }

    private function buildArchivePath(string $code, string $extension): string
    {
        $now = now();
        $base = 'items/_remplaces/'.$now->format('Y/m/d').'/'.$code.'_'.$now->format('YmdHis');
        $path = $base.'.'.$extension;
        $suffix = 2;

        while (Storage::disk(self::DISK_ORIGINAL)->exists($path)) {
            $path = $base.'_'.$suffix.'.'.$extension;
            $suffix++;
        }

        return $path;
    }

    /**
     * @param  list<string>  $directories
     */
    private function deleteDerivedDirectories(array $directories): void
    {
        if ($directories === []) {
            return;
        }

        $diskName = $this->diffusionDisk();

        foreach ($directories as $directory) {
            try {
                Storage::disk($diskName)->deleteDirectory($directory);
            } catch (Throwable $exception) {
                Log::warning(
                    "ItemFileReplacer : impossible de supprimer le dossier dérivé {$directory} : "
                    .$exception->getMessage()
                );
            }
        }
    }

    private function diffusionDisk(): string
    {
        $local = Storage::disk(self::DISK_LOCAL);
        $settingsPath = 'mms_settings.json';

        if (! $local->exists($settingsPath)) {
            return self::DIFFUSION_DISK_DEFAULT;
        }

        $settings = json_decode((string) $local->get($settingsPath), true);

        return is_array($settings) && isset($settings['diffusion_disk'])
            ? (string) $settings['diffusion_disk']
            : self::DIFFUSION_DISK_DEFAULT;
    }

    private function compensate(
        $disk,
        string $newPath,
        bool $newFileMoved,
        ?string $stagedOldPath,
        ?string $oldPath,
        string $tempPath,
    ): void {
        try {
            if ($newFileMoved && $disk->exists($newPath)) {
                $disk->delete($newPath);
            }

            if ($stagedOldPath !== null && $oldPath !== null && $disk->exists($stagedOldPath)) {
                $disk->move($stagedOldPath, $oldPath);
            }

            if ($disk->exists($tempPath)) {
                $disk->delete($tempPath);
            }
        } catch (Throwable $exception) {
            Log::error(
                'ItemFileReplacer : échec de la compensation après erreur : '.$exception->getMessage()
            );
        }
    }
}
