## Prompt 1 — Commande items:process-pending-media

**Contexte :** Le projet mms-crem dispose d'une pipeline de traitement complète : `MediaProcessor::processItem()` dispatch `GenerateDiffusionMedia` (HLS streaming via ffmpeg) et `GenerateAudiowaveform` (JSON waveform via audiowaveform) sur la queue `media_processing`. Le déclenchement actuel se fait via `ItemObserver::updated()` quand `file_path` est modifié.

Cependant, les items ont été importés avec leurs métadonnées (dont `file_path`) AVANT que les fichiers physiques n'arrivent sur le disque. Résultat : plus de 50 000 items ont `file_path` de renseigné mais :
- `file_type`, `file_size`, `duration`, `md5` sont NULL (la méthode `processFileUpload()` n'a pas pu s'exécuter)
- Les jobs `GenerateDiffusionMedia` et `GenerateAudiowaveform` n'ont jamais été dispatchés
- Aucune `MediaVariation` n'existe, aucun `ItemProcessingState` n'a été créé

Les fichiers sont maintenant disponibles sur le disque `original_medias`. Il faut une commande pour rattraper ça rétroactivement.

**Objectif :** Créer une commande Artisan `items:process-pending-media` qui scanne les items ayant `file_path` non nul mais dont le fichier est maintenant présent physiquement, met à jour leurs métadonnées manquantes (file_type, file_size, etc.), et dispatch les jobs de génération diffusion + waveform.

**Tâches à réaliser :**

1. **Créer la commande** (`app/Console/Commands/ProcessPendingMedia.php`) :
   - Signature : `items:process-pending-media {--force : Forcer le re-encodage même si des variations diffusion/waveform existent déjà}`
   - Description : « Rattrape les items dont le fichier est maintenant disponible sur le disque pour lancer la génération diffusion et waveform. »
   - Utiliser `chunkById(100, ...)` pour traiter les items par lots de 100 (50 000+ items en base)

2. **Requête de sélection des items :**
   ```php
   Item::query()
       ->whereNotNull('file_path')
       ->where('file_path', '!=', '')
   ```
   - Sans `--force` : filtrer les items qui n'ont PAS de `MediaVariation` avec `status = 'ready'` pour le profil `hls_standard` ET pas de `MediaVariation` pour `waveform_json` (ce qui signifie qu'ils n'ont jamais été traités)
   - Avec `--force` : ignorer ce filtre — TOUS les items audio/vidéo sont traités, même ceux qui ont déjà des variations. Dans ce cas, pour chaque item ciblé, supprimer les `MediaVariation` existantes (diffusion + waveform) avant de dispatcher les nouveaux jobs, et réinitialiser les `ItemProcessingState` concernés à `PENDING`. Voir le Prompt 3 pour le nettoyage des fichiers physiques dans les jobs eux-mêmes.

3. **Pour chaque item dans le lot :**
   - Vérifier que le fichier existe avec `Storage::disk('original_medias')->exists($item->file_path)`
   - Si le fichier n'existe pas, logguer un avertissement et passer à l'item suivant
   - Si `file_type` est NULL, le définir via `mime_content_type()` sur le chemin physique complet
   - Si `file_size` est NULL, le définir via `filesize()`
   - Si `md5` est NULL, le définir via `md5_file()`
   - Sauvegarder l'item AVEC `saveQuietly()` (NE PAS utiliser `save()` — ne pas déclencher l'Observer qui dispatcherait des jobs en double)
   - Si `--force` : supprimer les `MediaVariation` existantes (`hls_standard`, `waveform_json`), réinitialiser les `ItemProcessingState` à PENDING
   - Puis appeler `app(MediaProcessor::class)->processItem($item)` pour dispatcher les jobs

4. **Limiter aux fichiers audio/vidéo uniquement :**
   - Dans le lot, ne traiter que les items où `file_type` commence par `audio/` ou `video/`
   - Les images et autres types de fichiers n'ont pas de diffusion/waveform à générer

5. **Progression et reporting :**
   - Barre de progression (progress bar) sur le nombre total d'items traités
   - Afficher un récapitulatif à la fin :
     - Nombre d'items traités avec succès (jobs dispatchés)
     - Nombre d'items ignorés (fichier manquant)
     - Nombre d'items ignorés (pas audio/vidéo)
     - Nombre d'items déjà traités (sautés, si sans --force)

**Fichiers de référence à consulter :**
- `app/Console/Commands/CalculateMissingMd5.php` (pattern de commande similaire : chunk, progress bar, vérification fichier disque)
- `app/Services/MediaProcessor.php` (le service qui dispatch les jobs — à réutiliser tel quel)
- `app/Models/Item.php` (méthodes `isVideo()`, `isAudio()`, champs `file_type`, `file_size`, `md5`, `file_path`)
- `app/Jobs/GenerateDiffusionMedia.php` (job dispatché sur queue `media_processing`)
- `app/Jobs/GenerateAudiowaveform.php` (job dispatché sur queue `media_processing`)
- `app/Models/MediaVariation.php` (modèle avec `status`, `profile_name` — pour les vérifications d'existence)
- `app/Enums/ItemProcessingType.php` (enum `DIFFUSION`, `WAVEFORM`)
- `config/filesystems.php` (disque `original_medias`)
- `app/Console/Commands/MediaScan.php` (pattern de scan batch existant)

**Contraintes :**
- NE PAS appeler `Item::processFileUpload()` — cette méthode déplace/renomme les fichiers et modifie `file_path`. On veut juste mettre à jour les métadonnées manquantes sans toucher au chemin ni déplacer le fichier.
- Utiliser `saveQuietly()` au lieu de `save()` pour ne pas déclencher l'`ItemObserver::updated()` qui dispatcherait les jobs une deuxième fois (on les dispatch manuellement via `MediaProcessor::processItem()`)
- La commande doit fonctionner dans l'environnement Docker actuel. Vérifier que les chemins de disques (`original_medias`) pointent bien vers le volume monté.
- Ne PAS créer de nouvelles migrations — aucune modification de schéma n'est nécessaire.
- Les fichiers de log doivent être en français (comme le reste du projet : « Aucun item à traiter », « fichier introuvable », etc.)

---

## Prompt 2 — Paramètres d'encodage configurables dans l'admin Filament

**Contexte :** La page Filament `MediaSettings` (`app/Filament/Pages/MediaSettings.php`) permet déjà de configurer les chemins des binaires (ffmpeg, ffprobe, audiowaveform). Les paramètres sont stockés dans `storage/app/private/mms_settings.json` et lus par les jobs `GenerateDiffusionMedia` et `GenerateAudiowaveform`. Actuellement, les valeurs d'encodage (codec, preset, CRF, bitrate, HLS time, etc.) sont **hardcodées** dans les jobs et dans le formulaire Filament.

L'objectif est de centraliser toutes les options d'encodage dans `config/mms.php` (labels, valeurs possibles, valeurs par défaut) et de faire en sorte que :
- Le formulaire Filament génère ses champs Select/TextInput dynamiquement depuis `config/mms.php`
- Les jobs lisent leurs valeurs par défaut depuis `config('mms.encoding.*')` avant de tomber sur les valeurs dans `mms_settings.json`

**Objectif :** Rendre tous les paramètres d'encodage audio/vidéo configurables depuis l'admin Filament, avec les listes d'options et valeurs par défaut définies dans `config/mms.php`.

**Tâches à réaliser :**

1. **Enrichir `config/mms.php`** avec une section `encoding` contenant pour chaque paramètre : `default`, `options` (tableau associatif label → valeur ou valeur → label), et éventuellement `min`/`max` pour les champs numériques. Structure proposée :

```php
'encoding' => [
    'video' => [
        'codec' => [
            'default' => 'libx264',
            'options' => [
                'libx264' => 'H.264 (AVC)',
                'libx265' => 'H.265 (HEVC)',
                'libvpx-vp9' => 'VP9',
            ],
        ],
        'preset' => [
            'default' => 'veryfast',
            'options' => [
                'ultrafast' => 'Ultra rapide',
                'superfast' => 'Super rapide',
                'veryfast' => 'Très rapide',
                'faster' => 'Plus rapide',
                'fast' => 'Rapide',
                'medium' => 'Moyen',
                'slow' => 'Lent',
                'slower' => 'Plus lent',
                'veryslow' => 'Très lent',
            ],
        ],
        'crf' => [
            'default' => 23,
            'min' => 0,
            'max' => 51,
        ],
        'audio_bitrate' => [
            'default' => '128k',
            'options' => ['64k', '96k', '128k', '192k', '256k'],
        ],
        'hls_time' => [
            'default' => 4,
            'options' => [2, 4, 6, 8, 10],
        ],
    ],
    'audio' => [
        'codec' => [
            'default' => 'aac',
            'options' => [
                'aac' => 'AAC',
                'libmp3lame' => 'MP3',
                'libopus' => 'Opus',
            ],
        ],
        'bitrate' => [
            'default' => '128k',
            'options' => ['64k', '96k', '128k', '192k', '256k', '320k'],
        ],
        'channels' => [
            'default' => 2,
            'options' => [1 => 'Mono', 2 => 'Stéréo'],
        ],
        'hls_time' => [
            'default' => 10,
            'options' => [4, 6, 8, 10, 15, 20],
        ],
    ],
    'waveform' => [
        'pixels_per_second' => [
            'default' => 20,
            'options' => [10, 15, 20, 25, 30, 40, 50],
        ],
        'bits' => [
            'default' => 8,
            'options' => [8 => '8 bits', 16 => '16 bits'],
        ],
    ],
],
```

2. **Mettre à jour `app/Filament/Pages/MediaSettings.php`** :
   - Ajouter deux nouvelles sections dans le formulaire : « Encodage Vidéo » et « Encodage Audio »
   - Créer également une section « Waveform » pour les paramètres de génération de waveform
   - Pour chaque champ, utiliser un `Select` ou `TextInput` en lisant les options depuis `config('mms.encoding.video.codec.options')` (idem pour audio, waveform)
   - La valeur par défaut du champ dans le formulaire doit venir de `config('mms.encoding.video.codec.default')`
   - Si la config a `min`/`max`, utiliser un `TextInput` avec `numeric()` + `minValue()`/`maxValue()`
   - **Ne PAS hardcoder** de labels ou de listes de choix dans le formulaire — tout doit venir de `config/mms.php`
   - Ajouter les helperText pour guider l'utilisateur (ex: « Plus la valeur est basse, meilleure est la qualité vidéo » pour le CRF)

3. **Mettre à jour les jobs pour utiliser les defaults de config au lieu des hardcodés** :

   Dans `GenerateDiffusionMedia.php` :
   - Remplacer `'ffmpeg'` par `config('mms.encoding.video.codec.default')` pour le codec vidéo
   - Remplacer `'veryfast'` par `config('mms.encoding.video.preset.default')` pour le preset
   - Remplacer le CRF non-défini par `-crf ' . config('mms.encoding.video.crf.default')`
   - Remplacer `'128k'` pour le bitrate audio de la vidéo par `config('mms.encoding.video.audio_bitrate.default')`
   - Remplacer `4` (hls_time vidéo) par `config('mms.encoding.video.hls_time.default')`
   - Remplacer `'aac'` pour le codec audio seul par `config('mms.encoding.audio.codec.default')`
   - Remplacer `'128k'` pour le bitrate audio seul par `config('mms.encoding.audio.bitrate.default')`
   - Remplacer `2` pour les canaux audio par `config('mms.encoding.audio.channels.default')`
   - Remplacer `10` (hls_time audio) par `config('mms.encoding.audio.hls_time.default')`
   - **Ordre de priorité** : `mms_settings.json` > `config('mms.encoding.*')` > hardcodé actuel
   - Si une valeur existe dans `$settings` (venant de `mms_settings.json`), elle prime sur `config()`

   Dans `GenerateAudiowaveform.php` :
   - Remplacer `20` (pixels_per_second) par `config('mms.encoding.waveform.pixels_per_second.default')`
   - Remplacer `8` (bits) par `config('mms.encoding.waveform.bits.default')`
   - Même règle de priorité : `$settings` > `config()` > hardcodé

4. **Backward compatibility** : Si `config('mms.encoding')` n'existe pas (ancienne config), les valeurs hardcodées actuelles sont conservées comme fallback. Exemple : `$settings['video_codec'] ?? config('mms.encoding.video.codec.default', 'libx264')`.

**Fichiers de référence à consulter :**
- `config/mms.php` (fichier à enrichir avec la section `encoding`)
- `app/Filament/Pages/MediaSettings.php` (formulaire à étendre)
- `app/Jobs/GenerateDiffusionMedia.php` (job à modifier pour lire les settings)
- `app/Jobs/GenerateAudiowaveform.php` (job à modifier pour lire les settings waveform)
- `storage/app/private/mms_settings.json` (fichier de settings existant, pour comprendre le mécanisme de persistance)

**Contraintes :**
- Ne PAS modifier le mécanisme de stockage des settings — on garde `mms_settings.json` en JSON
- Tout nouveau champ doit être optionnel dans `mms_settings.json` (pas de champ = valeur par défaut depuis config/mms.php, puis hardcodée)
- Les labels dans le formulaire Filament doivent être en français
- Les Select doivent afficher les libellés lisibles (ex: « H.264 (AVC) ») mais stocker les valeurs techniques (ex: `libx264`)
- Ne PAS modifier la structure de la base de données (pas de migration)
- Vérifier que la commande `items:process-pending-media` (Prompt 1) bénéficiera automatiquement des nouveaux settings une fois ce prompt implémenté

---

## Prompt 3 — Nettoyage des fichiers de diffusion avant ré-encodage (pour le mode --force)

**Contexte :** Le job `GenerateDiffusionMedia` (`app/Jobs/GenerateDiffusionMedia.php`) génère des fichiers HLS (playlist .m3u8 + segments .ts) dans un dossier `items/{code}/diffusion/` sur le disque `diffusion_medias`. Actuellement, le job crée le dossier avec `makeDirectory()` et ffmpeg écrase les fichiers avec l'option `-y`, mais ne nettoie **pas** les fichiers résiduels d'une précédente génération. Si un ré-encodage produit moins de segments .ts que la version précédente, les segments orphelins restent sur le disque.

De même, le job `GenerateAudiowaveform` (`app/Jobs/GenerateAudiowaveform.php`) génère un fichier JSON de waveform dans `items/{code}/waveform/` sans nettoyage préalable.

La commande `items:process-pending-media --force` (Prompt 1) va dispatcher les jobs même pour des items ayant déjà des variations, et c'est dans les jobs eux-mêmes que le nettoyage des fichiers physiques doit se faire.

**Objectif :** Ajouter une étape de nettoyage des fichiers existants au début des jobs `GenerateDiffusionMedia` et `GenerateAudiowaveform`, avant la génération des nouveaux fichiers.

**Tâches à réaliser :**

1. **Dans `GenerateDiffusionMedia.php`**, ajouter après la récupération des settings (juste avant l'étape 2.5 « Extract Duration ») :
   - Supprimer tous les fichiers existants dans le dossier de sortie diffusion : `Storage::disk($diffusionDisk)->deleteDirectory($outputDir)`
   - Puis `Storage::disk($diffusionDisk)->makeDirectory($outputDir)` pour recréer le dossier vide
   - Cela garantit qu'aucun fichier .ts, .m3u8 ou autre résidu d'une précédente génération ne persiste

2. **Dans `GenerateAudiowaveform.php`**, ajouter après la récupération des settings (juste avant l'étape 2 « Paths ») :
   - Supprimer le fichier waveform existant s'il existe : `Storage::disk($diffusionDisk)->delete($outputDir.'/'.$fileName)`
   - Puis `Storage::disk($diffusionDisk)->makeDirectory($outputDir)` comme déjà fait

3. **Considérations de sécurité :**
   - Le `$outputDir` suit un pattern strict : `items/{code}/diffusion` et `items/{code}/waveform`
   - `$code` provient de `$this->item->code` (valeur en base, non modifiable par l'utilisateur) — pas de risque d'injection de path
   - Ne PAS nettoyer des dossiers en dehors de `items/{code}/` — le pattern est sécurisé par construction

4. **Interaction avec l'ordre de priorité :**
   - Le nettoyage doit arriver APRÈS avoir chargé `$settings` (pour connaître `$diffusionDisk`) mais AVANT d'appeler ffmpeg/audiowaveform

**Fichiers de référence à consulter :**
- `app/Jobs/GenerateDiffusionMedia.php` (job à modifier — lignes ~56-61 où le dossier est créé)
- `app/Jobs/GenerateAudiowaveform.php` (job à modifier — lignes ~56-59 où le dossier waveform est créé)
- `config/filesystems.php` (disques `original_medias` et `diffusion_medias`)

**Contraintes :**
- Le nettoyage ne doit PAS être conditionnel — il doit se faire à CHAQUE exécution du job, pas seulement quand --force est utilisé.
- Ne PAS supprimer les dossiers parents (`items/{code}/`) — seulement `items/{code}/diffusion/` et `items/{code}/waveform/`
- Ne pas toucher au `Item` model, ni à l'Observer, ni aux ProcessingStates — le cleanup est purement physique

---

## Prompt 4 — Service dédié ScannedFileAdminService + RunMediaScanJob asynchrone

**Contexte :** La page Filament `ScannedFileResource` (`app/Filament/Resources/ScannedFileResource.php`) utilise directement `MediaScanner` et `Artisan::call()` pour ses actions. C'est problématique pour trois raisons :
1. `Artisan::call('media:scan')` est synchrone → timeout sur 50 000+ fichiers
2. Les actions (`try_match`, `rescan`) ont des bugs (pas de `$rootScanPath` → `file_path` jamais défini sur l'Item)
3. Le `media:scan` existant ne déclenche pas le processing (Observer ne voit pas `file_path` comme dirty)

Pour isoler ces changements du reste du MMS, il faut créer un **service dédié** (`ScannedFileAdminService`) utilisé uniquement par cette page d'administration. Ce service doit fonctionner avec la commande `items:process-pending-media` (Prompt 1) pour le processing des items.

**Objectif :** Créer un service `ScannedFileAdminService` avec des méthodes dédiées aux actions de la page d'admin, et un job de queue `RunMediaScanJob` pour le scan asynchrone des fichiers.

**Tâches à réaliser :**

1. **Créer `app/Services/Admin/ScannedFileAdminService.php`** avec les méthodes suivantes :

   - **`runScan(?string $scanPath = null)`** :
     - Charge le `scan_path` depuis `mms_settings.json` si non fourni
     - Scanne le dossier avec `Symfony\Component\Finder\Finder` (comme dans `MediaScan`)
     - Pour chaque fichier trouvé : crée ou met à jour un `ScannedFile` record
     - Extrait le code du nom de fichier (sans extension), cherche l'Item correspondant
     - Si trouvé : associe (set `item_id` + status `ASSOCIATED`)
     - Si `file_path` de l'Item est vide : le définit avec le chemin relatif
     - Si `md5` de l'Item est vide : le calcule
     - **NE PAS** sauvegarder l'Item avec `save()` — ne pas déclencher l'Observer (le processing sera géré par `processPending()`)
     - Retourne un tableau de stats : `['found' => int, 'matched' => int, 'orphaned' => int]`

   - **`tryMatch(ScannedFile $record)`** :
     - Charge le `scan_path` depuis les settings
     - Extrait le code du `file_name`
     - Cherche l'Item correspondant par code
     - Si trouvé : associe sur le ScannedFile + définit `file_path` sur l'Item si vide + calcule `md5` si vide
     - Sauvegarde avec `saveQuietly()` (pas de déclenchement de l'Observer)
     - Retourne `true`/`false`

   - **`rescan(ScannedFile $record)`** :
     - Vérifie que le fichier physique existe toujours (`file_exists($record->file_path)`)
     - Met à jour `size`, `last_scanned_at`
     - Si le statut est `ORPHAN` : tente un `tryMatch`
     - Si le statut est `ASSOCIATED` : met à jour `md5` de l'Item si nécessaire
     - Retourne `true`/`false`

   - **`processPending(bool $force = false)`** :
     - Lance la commande `items:process-pending-media` avec ou sans `--force` en arrière-plan
     - Soit via `Artisan::call` si c'est léger, soit en dispatchant un `RunMediaScanJob`

2. **Créer `app/Jobs/RunMediaScanJob.php`** (implements `ShouldQueue`, on queue `media_processing`) :
   - Le constructeur prend un `?string $scanPath`
   - La méthode `handle()` instancie `ScannedFileAdminService` et appelle `$service->runScan($this->scanPath)`
   - Une fois le scan terminé :
     - Loggue les stats
     - Dispatch automatiquement `items:process-pending-media` via `Artisan::queue('items:process-pending-media')` pour traiter les items nouvellement associés
     - Envoie une notification Filament à l'utilisateur qui a déclenché le scan

3. **Le `runScan` et `tryMatch` du service DOIVENT** :
   - Calculer le `file_path` relatif : retirer le `scan_path` du chemin absolu pour obtenir un relatif stockable
   - **Ne PAS appeler `$item->save()`** standard — toujours utiliser `saveQuietly()` pour éviter les doubles dispatchs via l'Observer
   - Le processing des items (génération diffusion + waveform) est délégué à `items:process-pending-media` — pas de dispatch direct dans le service

**Fichiers de référence à consulter :**
- `app/Services/MediaScanner.php` (logique existante à adapter dans le nouveau service, ne PAS modifier ce fichier)
- `app/Console/Commands/MediaScan.php` (pattern de scan avec Finder)
- `app/Models/ScannedFile.php` (modèle avec status, item_id, file_path, file_name)
- `app/Enums/ScannedFileStatus.php` (enum `ORPHAN`, `ASSOCIATED`)
- `app/Models/Item.php` (champs file_path, md5, code)
- `storage/app/private/mms_settings.json` (settings avec scan_path)
- `app/Filament/Resources/ScannedFileResource.php` (page qui utilisera le nouveau service)
- `config/filesystems.php` (disques)
- `app/Jobs/GenerateDiffusionMedia.php` (job existant sur queue `media_processing`)

**Contraintes :**
- Ne PAS modifier `MediaScanner.php`, `MediaProcessor.php`, ni `ItemObserver.php` — ces classes sont utilisées ailleurs dans le MMS
- Le service `ScannedFileAdminService` est le seul point d'entrée pour la page d'admin
- `RunMediaScanJob` doit être dispatchable sur la queue `media_processing`
- Les méthodes du service retournent des valeurs simples (bool, array) — pas d'effet de bord de notification
- La gestion des notifications Filament se fait dans le `ScannedFileResource` ou dans le job, pas dans le service
- Les logs et messages doivent être en français

---

## Prompt 5 — Refonte de la page Filament ScannedFileResource

**Contexte :** Ce prompt dépend des Prompts 1 et 4 (la commande `items:process-pending-media` et le service `ScannedFileAdminService` + `RunMediaScanJob` doivent exister).

La page `ScannedFileResource` (`app/Filament/Resources/ScannedFileResource.php`) a plusieurs problèmes :
- `run_scan` synchrone : timeout sur les gros volumes
- `try_match` buggé : ne définit pas `file_path` sur l'Item
- `rescan` sur un fichier associé = no-op
- Impossible de lancer `items:process-pending-media` depuis l'interface
- Pas de feedback utilisateur sur l'état du scan

La page doit être refondue pour utiliser `ScannedFileAdminService` et `RunMediaScanJob`, avec des actions corrigées et de nouvelles fonctionnalités.

**Objectif :** Refondre le `ScannedFileResource` pour corriger les bugs, ajouter des actions de batch et de processing, et utiliser le nouveau service + job asynchrone.

**Tâches à réaliser :**

1. **Mettre à jour `app/Filament/Resources/ScannedFileResource.php`** :

   - **Action « Lancer un scan » (`run_scan`)** :
     - Remplacer `Artisan::call('media:scan')` par le dispatch de `RunMediaScanJob`
     - `RunMediaScanJob::dispatch()` sans path (utilise le scan_path des settings)
     - Notification : « Scan lancé en arrière-plan. Vous recevrez une notification à la fin. » au lieu de « Scan terminé » (qui était faux vu que c'était synchrone)

   - **Action « Rescanner » (`rescan`)** :
     - Remplacer l'appel direct à `$scanner->scanFile()` par `app(ScannedFileAdminService::class)->rescan($record)`
     - Si le fichier n'existe plus : notification danger « Fichier introuvable »
     - Si le fichier existe : notification success « Fichier rescanné »

   - **Action « Associer » (`try_match`)** :
     - Remplacer l'appel direct à `$scanner->matchItem()` par `app(ScannedFileAdminService::class)->tryMatch($record)`
     - Visibilité inchangée : seulement sur les ORPHAN
     - Notification existante conservée (« Item associé avec succès » / « Aucun item correspondant trouvé »)

2. **Ajouter dans `app/Filament/Resources/ScannedFileResource/Pages/ListScannedFiles.php`** :

   - **Action « Traiter les items en attente »** dans `getHeaderActions()` (bouton en haut de page) :
     - Label : « Traiter les items associés »
     - Icon : `heroicon-o-play`
     - Color : `success`
     - Action : `Artisan::queue('items:process-pending-media')`
       - Utiliser `Illuminate\Support\Facades\Artisan::queue()` — pas de `call()` synchrone
     - Notification : « Traitement lancé en arrière-plan. Les jobs de diffusion et waveform sont en cours de dispatch. »

   - **Action « Forcer le retraitement »** dans `getHeaderActions()` :
     - Label : « Forcer le retraitement »
     - Icon : `heroicon-o-arrow-path`
     - Color : `danger`
     - Requires confirmation : `requiresConfirmation()`
     - Action : `Artisan::queue('items:process-pending-media', ['--force' => true])`
     - Notification : « Retraitement forcé lancé. Tous les items seront ré-encodés. »

3. **Améliorations mineures du tableau :**

   - Ajouter une colonne `ItemProcessingState` liée :
     ```php
     TextColumn::make('item.processingStates')
         ->label('Statut diffusion')
         ->formatStateUsing(function ($record) {
             $state = $record->item?->processingStates()
                 ->where('process_type', ItemProcessingType::DIFFUSION)
                 ->first();
             return $state?->status->value ?? '—';
         })
         ->badge()
         ->color(fn (?string $state) => match ($state) {
             'completed' => 'success',
             'processing' => 'warning',
             'failed' => 'danger',
             'pending' => 'gray',
             default => 'gray',
         })
     ```

4. **Nettoyage :**
   - Supprimer l'import de `MediaScanner` en haut du fichier (il n'est plus utilisé directement)
   - `toolbarActions` devient responsable de dispatcher les jobs, pas de les exécuter

**Fichiers de référence à consulter :**
- `app/Filament/Resources/ScannedFileResource.php` (fichier à refondre)
- `app/Filament/Resources/ScannedFileResource/Pages/ListScannedFiles.php` (page à enrichir avec header actions)
- `app/Services/Admin/ScannedFileAdminService.php` (nouveau service — Prompt 4)
- `app/Jobs/RunMediaScanJob.php` (nouveau job — Prompt 4)
- `app/Enums/ScannedFileStatus.php` (enum existante)
- `app/Enums/ItemProcessingType.php` (enum DIFFUSION, WAVEFORM)
- `app/Enums/ItemProcessingStatus.php` (enum PENDING, PROCESSING, COMPLETED, FAILED)
- `app/Models/Item.php` (relation `processingStates()`)
- `app/Models/ScannedFile.php` (model avec relation `item()`)

**Contraintes :**
- Utiliser `Artisan::queue()` et non `Artisan::call()` pour toutes les commandes longues — ne jamais bloquer la requête HTTP
- Les notifications Filament (success, danger, warning) sont le seul retour utilisateur
- Ne PAS modifier `app/Services/MediaScanner.php` ni `app/Services/MediaProcessor.php`
- Les imports inutilisés doivent être supprimés
- Les libellés, notifications et messages doivent être en français
- Le bouton « Forcer le retraitement » doit avoir une confirmation explicite (`requiresConfirmation()`) pour éviter les clics accidentels

---

## Prompt 6 — Aide contextuelle pour les paramètres d'encodage dans les settings

**Contexte :** Les champs d'encodage ont été ajoutés dans `app/Filament/Pages/MediaSettings.php` (Prompt 2 — exécuté par Junie). Actuellement, seul le champ `video_crf` a un `helperText` minimaliste : « Plus la valeur est basse, meilleure est la qualité vidéo (0-51). » Les autres champs (codec, preset, bitrate, canaux, HLS time, waveform) n'ont **aucune aide** pour guider l'utilisateur.

L'objectif est d'ajouter des `helperText` complets pour **chaque champ**, rédigés pour un utilisateur non technique — quelqu'un qui connaît ses fichiers audio/vidéo mais pas les détails de l'encodage.

Les `helperText` doivent expliquer :
- Ce que fait le paramètre en termes concrets
- L'impact (taille du fichier, qualité, temps de traitement)
- Quand choisir quelle valeur

**Objectif :** Ajouter des `helperText` informatifs sur chaque champ d'encodage dans `MediaSettings.php`, rédigés en français pour un public non technique.

**Tâches à réaliser :**

1. **Ajouter/modifier les `helperText` dans `app/Filament/Pages/MediaSettings.php`** pour chaque champ. Ne pas toucher au `config/mms.php` — les helperText restent dans le formulaire. Voici les textes à utiliser :

   **Section « Configuration MMS »** (inchangée) :
   - `scan_path` : « Dossier où se trouvent les fichiers sources (fichiers originaux à encoder). »
   - `ffmpeg_path` : « Laissez vide si ffmpeg est accessible via le PATH système. »
   - `ffprobe_path` : « Laissez vide si ffprobe est accessible via le PATH système. »
   - `audiowaveform_path` : « Laissez vide si audiowaveform est accessible via le PATH système. »
   - `diffusion_disk` : « Disque de stockage où seront écrits les fichiers de diffusion (HLS, waveform). »

   **Section « Encodage Vidéo »** :

   - `video_codec` : « Codec utilisé pour compresser la vidéo. H.264 (AVC) est le standard le plus compatible (recommandé). H.265 (HEVC) offre une meilleure compression mais est moins compatible. VP9 offre une bonne qualité à bas débit. »
   - `video_preset` : « Vitesse de compression. Plus c'est rapide (Ultra rapide), plus le fichier est gros pour une même qualité. Plus c'est lent (Très lent), plus le fichier est petit mais le traitement dure plus longtemps. Valeur recommandée : Très rapide pour un bon équilibre. »
   - `video_crf` : « Qualité visible de la vidéo. 18 = qualité quasi parfaite (fichier volumineux), 23 = qualité très bonne (équilibre recommandé), 28 = qualité réduite (fichier plus petit). Ne pas descendre sous 18 : la différence n'est plus visible mais le fichier est bien plus gros. »
   - `video_audio_bitrate` : « Débit binaire de la piste audio dans la vidéo. 128k = qualité FM (recommandé), 192k = haute qualité, 256k = qualité CD. Plus le débit est élevé, plus le fichier est gros et meilleure est la qualité audio. »
   - `video_hls_time` : « Durée de chaque segment de streaming (en secondes). Segments courts = démarrage plus rapide sur connexions lentes mais plus de fichiers. Segments longs = moins de fichiers, téléchargement plus efficace. 4s est un bon compromis. »

   **Section « Encodage Audio »** :

   - `audio_codec` : « Format audio pour le streaming. AAC est le plus compatible (recommandé). Opus offre une meilleure qualité au même débit. MP3 est le format le plus universellement reconnu. »
   - `audio_bitrate` : « Débit binaire audio : plus le chiffre est élevé, meilleure est la qualité mais plus le fichier est gros. 64k = acceptable (voix), 128k = bonne qualité (recommandé), 192k+ = haute qualité (musique). »
   - `audio_channels` : « Mono = un seul canal (recommandé pour la parole). Stéréo = deux canaux (recommandé pour la musique). Le Mono produit un fichier deux fois plus petit. »
   - `audio_hls_time` : « Durée de chaque segment audio (en secondes). 10s est la valeur recommandée pour un bon équilibre entre fluidité et efficacité. »

   **Section « Waveform »** :

   - `waveform_pixels_per_second` : « Résolution de la forme d'onde : plus il y a de pixels par seconde, plus le rendu visuel est précis et détaillé, mais le fichier JSON est plus volumineux. 20 pps est excellent, 10 pps suffit. »
   - `waveform_bits` : « Précision des données de la forme d'onde. 8 bits = plus petit, suffisant pour un affichage standard. 16 bits = plus précis, 2 fois plus gros. »

2. **Format technique** : Utiliser `->hintIcon('heroicon-o-information-circle')` avec `->hintColor('primary')` pour les textes longs (tooltip), et `->helperText()` concis pour les textes courts.

   Répartition suggérée :
   - `video_preset`, `video_audio_bitrate`, `audio_bitrate`, `audio_channels`, `waveform_pixels_per_second` : helperText direct (textes courts)
   - `video_codec`, `video_crf`, `video_hls_time`, `audio_codec`, `audio_hls_time`, `waveform_bits` : hintIcon avec tooltip (textes longs)

3. **Ne PAS modifier** :
   - La structure des sections (colonne 2)
   - Les options / defaults / labels des champs
   - `config/mms.php`
   - Le mécanisme de sauvegarde
   - La logique des jobs

**Fichiers de référence à consulter :**
- `app/Filament/Pages/MediaSettings.php` (fichier à modifier — ajouter les helperText / hints)
- `config/mms.php` (lire seulement pour comprendre les champs, ne pas modifier)

**Contraintes :**
- Les textes doivent être compréhensibles pour un utilisateur qui ne connaît PAS l'encodage vidéo/audio
- Les valeurs recommandées doivent être mises en évidence (ex: « recommandé », « bonne qualité », « excellent »)
- Ne pas utiliser de jargon technique non expliqué (CRF, bitrate, codec doivent avoir une explication simple)
- Un même réglage impactant à la fois la qualité et la taille du fichier doit le dire explicitement
- Les textes sont en français uniquement
- Utiliser `hintIcon()` avec une icône d'information pour les explications les plus longues, cela rend l'interface plus épurée

---

## Prompt 7 — Service MediaVariationPathResolver + MediaController::segment()

**Contexte :** Les variations médias (HLS diffusion + waveform JSON) sont actuellement stockées dans `storage/app/diffusion_medias/items/{code}/diffusion/` et `storage/app/diffusion_medias/items/{code}/waveform/`. Les fichiers originaux sont dans `medias/items/{Y/m/d}/{code}.{ext}` (disque `original_medias`). Les variations ne suivent pas la même arborescence que les originaux.

L'objectif est de déplacer les variations dans `medias/diffusion_medias/items/{Y/m/d}/{code}/diffusion/` et `.../waveform/`, en déduisant le chemin depuis `$item->file_path` (ex: `"items/2011/05/25/CODE.wav"` → `dirname()` = `"items/2011/05/25"`). Il faut créer un service centralisé de résolution de chemins et corriger la méthode `MediaController::segment()` qui hardcode actuellement l'ancien chemin.

**Objectif :** Créer `MediaVariationPathResolver` qui centralise la logique de construction des chemins de variations (depuis `dirname($item->file_path)`), et corriger `MediaController::segment()` pour utiliser le chemin depuis l'item plutôt qu'un hardcode.

**Tâches à réaliser :**

1. **Créer le service** (`app/Services/MediaVariationPathResolver.php` — nouveau fichier) :

   ```php
   <?php
   
   namespace App\Services;
   
   use App\Models\Item;
   
   class MediaVariationPathResolver
   {
       /**
        * Dossier de base de l'item, reproduit depuis l'original.
        * Ex: $item->file_path = "items/2011/05/25/CODE.wav"
        *     → "items/2011/05/25/CODE"
        */
       public function itemDir(Item $item): string
       {
           $base = dirname($item->file_path);
           // Protection contre les file_path sans dossier parent (en mode saveQuietly)
           if ($base === '' || $base === '.') {
               $base = 'items';
           }
           return $base . '/' . $item->code;
       }
   
       /**
        * Dossier complet pour un type de variation.
        * Ex: → "items/2011/05/25/CODE/diffusion"
        */
       public function variationDir(Item $item, string $type): string
       {
           return $this->itemDir($item) . '/' . $type;
       }
   
       /**
        * Chemin complet d'un fichier de variation.
        * Ex: → "items/2011/05/25/CODE/diffusion/CODE.m3u8"
        */
       public function variationPath(Item $item, string $type, string $filename): string
       {
           return $this->variationDir($item, $type) . '/' . $filename;
       }
   
       /**
        * Dossier parent d'un type de variation (pour les segments HLS).
        * Ex: → "items/2011/05/25/CODE/diffusion"
        */
       public function segmentDir(Item $item): string
       {
           return $this->variationDir($item, 'diffusion');
       }
   }
   ```

2. **Corriger `MediaController::segment()`** (`app/Http/Controllers/MediaController.php`, méthode `segment()` lignes 73-100) :

   Remplacer le hardcode actuel (ligne 90) :
   ```php
   // AVANT
   $path = 'items/'.$code.'/diffusion/'.$segment;
   ```
   Par une déduction depuis l'item :
   ```php
   // APRÈS
   $resolver = app(\App\Services\MediaVariationPathResolver::class);
   $path = $resolver->segmentDir($item) . '/' . $segment;
   ```

   Le reste de la méthode reste inchangé (vérification de sécurité `str_starts_with($segment, $code.'_')`, chargement de l'item, réponse).

**Fichiers de référence à consulter :**
- `app/Models/Item.php` (champ `file_path`, code)
- `app/Http/Controllers/MediaController.php` (méthode `segment()`, lignes 73-100)
- `app/Jobs/GenerateDiffusionMedia.php` (consommera le service dans le Prompt 8)
- `app/Jobs/GenerateAudiowaveform.php` (consommera le service dans le Prompt 9)

**Contraintes :**
- NE PAS modifier les autres méthodes du MediaController (`master`, `waveform`, `serve`, `variations`, `determineUrl`, `formatMediaEntry` — elles utilisent déjà `$variation->file_path` depuis la DB)
- La vérification de sécurité `str_starts_with($segment, $code.'_')` doit être conservée
- Le service ne doit avoir AUCUNE dépendance autre que `App\Models\Item` et les classes PHP natives
- `dirname($item->file_path)` est garanti non-vide pour les items créés normalement (via `processFileUpload()` qui place en `items/Y/m/d/CODE.ext`). La protection contre `''` ou `'.'` est pour les cas marginaux (items créés via `saveQuietly()` par le scan admin)

---

## Prompt 8 — GenerateDiffusionMedia adapté à la nouvelle arborescence

**Contexte :** Le job `GenerateDiffusionMedia` (`app/Jobs/GenerateDiffusionMedia.php`) génère un flux HLS (playlist .m3u8 + segments .ts) via ffmpeg. Il doit désormais utiliser le service `MediaVariationPathResolver` (créé au Prompt 7) pour construire ses chemins de sortie dans la nouvelle arborescence : `medias/diffusion_medias/items/{Y/m/d}/{code}/diffusion/`.

Le disque `diffusion_medias` utilise maintenant `MMS_DIFFUSION_MEDIAS_PATH` qui pointe vers `medias/diffusion_medias/` (à définir dans `.env`). Le paramètre `diffusion_disk` dans `mms_settings.json` (panel admin) continue d'écraser le nom du disque — cette logique est inchangée.

**Objectif :** Adapter `GenerateDiffusionMedia` pour utiliser `MediaVariationPathResolver` dans la construction de tous les chemins de sortie, et s'assurer que `deleteDirectory` + `makeDirectory` nettoient uniquement le sous-dossier `diffusion/`.

**Tâches à réaliser :**

1. **Dans `app/Jobs/GenerateDiffusionMedia.php`, modifier la méthode `handle()` :**

   a. **Instancier le service** après le bloc try (ligne ~37) :
      ```php
      $resolver = app(\App\Services\MediaVariationPathResolver::class);
      ```

   b. **Remplacer la construction du dossier de sortie** (lignes 68-69) :
      ```php
      // AVANT
      $outputDir = 'items/'.$this->item->code.'/diffusion';
      $outputPathRelative = $outputDir.'/'.$this->item->code;
      
      // APRÈS
      $outputDir = $resolver->variationDir($this->item, 'diffusion');
      $outputPathRelative = $resolver->variationPath($this->item, 'diffusion', $this->item->code);
      ```

   c. **Remplacer le `$finalPath`** (lignes 135 pour vidéo, ligne 160 pour audio) :
      ```php
      // AVANT
      $finalPath = $outputDir.'/'.$playlistName;
      // APRÈS
      $finalPath = $resolver->variationPath($this->item, 'diffusion', $playlistName);
      ```

   d. **Conserver inchangés** les éléments suivants (ils utilisent déjà `$outputDir` ou `$outputDirAbsolute` qui dérivent de la nouvelle valeur) :
      - `$outputDirAbsolute = Storage::disk($diffusionDisk)->path($outputDir)` (ligne 74)
      - `deleteDirectory($outputDir)` + `makeDirectory($outputDir)` (lignes 72-73)
      - `-hls_base_url`, `$this->item->code.'/'` (c'est une URL de route, pas un chemin disque — lignes 128 et 153)
      - `-hls_segment_filename`, `$outputDirAbsolute.'/'.$this->item->code.'_%03d.ts'` (lignes 129 et 154)
      - `Storage::disk($diffusionDisk)->allFiles($outputDir)` (ligne 173)

   e. **Mettre à jour le `MediaVariation::updateOrCreate`** (lignes 178-194) : le champ `file_path` utilise déjà `$finalPath` — pas de changement.

2. **Conserver le mécanisme de settings** (inchangé) :
   ```php
   $diffusionDisk = $settings['diffusion_disk'] ?? 'diffusion_medias';
   ```
   Le nom du disque `diffusion_medias` reste le même, seul son root change via `MMS_DIFFUSION_MEDIAS_PATH` dans `.env`.

**Fichiers de référence à consulter :**
- `app/Jobs/GenerateDiffusionMedia.php` (fichier à modifier)
- `app/Services/MediaVariationPathResolver.php` (nouveau service — Prompt 7)
- `config/filesystems.php` (disque `diffusion_medias`)
- `.env` (définir `MMS_DIFFUSION_MEDIAS_PATH=C:\laragon\www\mms-crem\medias\diffusion_medias`)

**Contraintes :**
- Ne PAS modifier la logique de nettoyage (`deleteDirectory` + `makeDirectory`) — elle fonctionne déjà correctement, seul `$outputDir` change de valeur
- Ne PAS modifier `MediaVariation::updateOrCreate` — `$finalPath` est déjà utilisé
- Le `-hls_base_url` reste `$this->item->code.'/'` car c'est l'URL de la route `/media/{code}/{segment}` — pas le chemin disque
- Ne PAS modifier la logique de fallback `$settings['diffusion_disk'] ?? 'diffusion_medias'` — le panel admin contrôle le nom du disque

---

## Prompt 9 — GenerateAudiowaveform adapté à la nouvelle arborescence

**Contexte :** Le job `GenerateAudiowaveform` (`app/Jobs/GenerateAudiowaveform.php`) génère un fichier JSON de waveform à partir d'un fichier audio/vidéo. Il doit utiliser le service `MediaVariationPathResolver` (créé au Prompt 7) pour placer son fichier de sortie dans la nouvelle arborescence : `medias/diffusion_medias/items/{Y/m/d}/{code}/waveform/{code}.json`.

**Objectif :** Adapter `GenerateAudiowaveform` pour utiliser `MediaVariationPathResolver` dans la construction de ses chemins.

**Tâches à réaliser :**

1. **Dans `app/Jobs/GenerateAudiowaveform.php`, modifier la méthode `handle()` :**

   a. **Instancier le service** après le bloc try (ligne ~37) :
      ```php
      $resolver = app(\App\Services\MediaVariationPathResolver::class);
      ```

   b. **Remplacer la construction des chemins** (lignes 60-66) :
      ```php
      // AVANT
      $outputDir = 'items/'.$this->item->code.'/waveform';
      $fileName = $this->item->code.'.json';
      
      Storage::disk($diffusionDisk)->delete($outputDir.'/'.$fileName);
      Storage::disk($diffusionDisk)->makeDirectory($outputDir);
      $outputFileAbsolute = Storage::disk($diffusionDisk)->path($outputDir.'/'.$fileName);
      
      // APRÈS
      $outputDir = $resolver->variationDir($this->item, 'waveform');
      $fileName = $this->item->code.'.json';
      
      Storage::disk($diffusionDisk)->delete($outputDir.'/'.$fileName);
      Storage::disk($diffusionDisk)->makeDirectory($outputDir);
      $outputFileAbsolute = Storage::disk($diffusionDisk)->path($outputDir.'/'.$fileName);
      ```

   c. **Mettre à jour le `MediaVariation::updateOrCreate`** (lignes 110-125) :
      ```php
      // AVANT (ligne 118)
      'file_path' => $outputDir.'/'.$fileName,
      // APRÈS
      'file_path' => $resolver->variationPath($this->item, 'waveform', $fileName),
      ```

   d. **Mettre à jour le calcul de $fileSize** (ligne 107) :
      ```php
      // AVANT
      $fileSize = Storage::disk($diffusionDisk)->size($outputDir.'/'.$fileName);
      // APRÈS (inchangé — utilise toujours $outputDir.'/'.$fileName)
      // Mais vérifier que $outputDir a bien la nouvelle valeur
      $fileSize = Storage::disk($diffusionDisk)->size($outputDir.'/'.$fileName);
      ```

2. **Conserver le mécanisme de settings** (inchangé) :
   ```php
   $diffusionDisk = $settings['diffusion_disk'] ?? 'diffusion_medias';
   ```
   Le nom du disque `diffusion_medias` reste le même.

**Fichiers de référence à consulter :**
- `app/Jobs/GenerateAudiowaveform.php` (fichier à modifier)
- `app/Services/MediaVariationPathResolver.php` (nouveau service — Prompt 7)

**Contraintes :**
- La logique de nettoyage (`delete` + `makeDirectory`) reste inchangée — seul `$outputDir` change de valeur
- Ne PAS modifier la logique de fallback `$settings['diffusion_disk'] ?? 'diffusion_medias'`
- Ne PAS modifier la construction de `$inputPath` (ligne 54) — elle lit depuis `original_medias`, pas `diffusion_medias`

---

## Prompt 10 — Fallback scan_path vers MMS_MEDIAS_PATH

**Contexte :** Les commandes et services de scan utilisent `$settings['scan_path'] ?? null` pour déterminer le dossier à scanner. Si le champ `scan_path` n'est pas renseigné dans le panel admin (vide dans `mms_settings.json`), le scan échoue avec « No scan path configured ». Or `MMS_MEDIAS_PATH` (via `config('mms.medias_path')`) contient déjà le chemin racine des médias, qui est un fallback naturel.

**Objectif :** Ajouter `config('mms.medias_path')` comme fallback dans les deux endroits qui lisent `scan_path` : la commande `MediaScan` et le service `ScannedFileAdminService`.

**Tâches à réaliser :**

1. **Dans `app/Console/Commands/MediaScan.php`** (lignes 35-38) :
   ```php
   // AVANT
   $scanPath = $settings['scan_path'] ?? null;
   
   // APRÈS
   $scanPath = $settings['scan_path'] ?? config('mms.medias_path');
   ```

2. **Dans `app/Services/Admin/ScannedFileAdminService.php`** (lignes 40-41) :
   ```php
   // AVANT
   $rootScanPath = $scanPath ?? $settings['scan_path'] ?? null;
   
   // APRÈS
   $rootScanPath = $scanPath ?? $settings['scan_path'] ?? config('mms.medias_path');
   ```

**Fichiers de référence à consulter :**
- `app/Console/Commands/MediaScan.php` (ligne ~35)
- `app/Services/Admin/ScannedFileAdminService.php` (ligne ~41)
- `config/mms.php` (clé `medias_path`)

**Contraintes :**
- L'ordre de priorité doit rester : argument explicite (si fourni) > `mms_settings.json` > `config('mms.medias_path')` > null
- NE PAS modifier la logique existante de `app/Services/MediaScanner.php` (seulement `ScannedFileAdminService.php`)
