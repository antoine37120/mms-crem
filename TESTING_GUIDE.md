# Guide de Test - MMS (Media Management System)

Ce document décrit la procédure pour valider les fonctionnalités du module MMS (Étapes 1 à 4).

## Prérequis

1.  **FFmpeg & Audiowaveform** : Assurez-vous que ces binaires sont installés sur votre système.
    ```bash
    # Exemple (Ubuntu)
    sudo apt install ffmpeg
    # Audiowaveform doit être compilé ou installé via snap/ppa
    ```
2.  **Base de données** : Assurez-vous que les migrations sont passées.
    ```bash
    php artisan migrate
    ```
3.  **Dossiers** :
    *   Créez un dossier pour simuler les fichiers originaux : `mkdir -p storage/app/medias`
    *   Créez un dossier de destination (si non automatique) : `mkdir -p storage/app/diffusion_medias`

---

## 1. Configuration (Fondations)

1.  Accédez au Panel Admin (`/admin`).
2.  Allez dans **Configuration > MMS Settings**.
3.  Remplissez les champs :
    *   **Scan Path** : Chemin absolu vers votre dossier de médias originaux (ex: `/var/www/html/storage/app/medias`).
    *   **Diffusion Disk** : `diffusion_medias` (valeur par défaut).
    *   **Chemins binaires** : Laissez vide si ffmpeg est dans le PATH, sinon indiquez le chemin absolu.
4.  Cliquez sur **Sauvegarder**.
5.  Vérifiez que le fichier `storage/app/mms_settings.json` a été créé ou mis à jour.

---

## 2. Scan & Découverte

1.  **Préparation** : Placez un fichier vidéo ou audio dans votre dossier de scan.
    *   *Important* : Nommez le fichier exactement comme le CODE d'un Item existant (ex: si vous avez un item avec le code `VIDEO_001`, nommez votre fichier `VIDEO_001.mp4`).
    *   Si vous n'avez pas d'Item, créez-en un via le Panel Admin avec un Code spécifique.
2.  **Lancement du Scan** :
    *   **Option A (UI)** : Allez dans **Médias & Items > Fichiers Scannés** et cliquez sur le bouton "Lancer un scan" (en haut à droite).
    *   **Option B (CLI)** :
        ```bash
        php artisan media:scan
        ```
3.  **Vérification** :
    *   Le fichier doit apparaître dans la liste **Fichiers Scannés**.
    *   La colonne **Statut** doit être `Associated` (Vert) si le code correspond.
    *   La colonne **Item lié** doit être cliquable.

---

## 3. Traitement & Jobs

1.  **Lancer la Queue** : Ouvrez un terminal pour exécuter les jobs en arrière-plan.
    ```bash
    php artisan queue:work --queue=media_processing
    ```
2.  **Déclencher le traitement** :
    *   Le traitement se déclenche automatiquement à la création/modification d'un Item si le fichier est associé.
    *   Si le scan a déjà associé le fichier, modifiez légèrement l'Item (ex: changer le titre) pour déclencher l'Observer (vérifiez que `file_path` est bien renseigné sur l'Item, le scan ne le remplit pas toujours automatiquement selon votre logique d'import, assurez-vous que l'Item pointe vers le fichier scanné).
    *   *Note* : Dans l'implémentation actuelle, le scan associe le `ScannedFile` à l'Item, mais ne met pas forcément à jour le `file_path` de l'Item. Pour tester le processeur, assurez-vous que l'Item a un `file_path` valide pointant vers le fichier dans `original_medias`.
3.  **Surveillance** :
    *   Regardez le terminal `queue:work`. Vous devriez voir :
        *   `Processing: App\Jobs\GenerateDiffusionMedia`
        *   `Processing: App\Jobs\GenerateAudiowaveform`
4.  **Résultat** :
    *   Vérifiez le dossier `storage/app/diffusion_medias/items/{CODE}/`.
    *   Vous devriez voir un dossier `diffusion` (avec `.m3u8` et `.ts`) et un dossier `waveform` (avec `.json`).

---

## 4. UI & Streaming

1.  **Fiche Item** :
    *   Allez sur la page "Voir" de l'Item concerné.
    *   Une nouvelle section **Traitements & Variations** doit apparaître.
    *   Vérifiez que les statuts sont à `COMPLETED` (Vert).
    *   Vérifiez la liste des variations (HLS, Waveform).
2.  **Test Streaming** :
    *   Ouvrez un nouvel onglet navigateur.
    *   Accédez à l'URL générique : `http://votre-domaine.test/media/{CODE}`
    *   Le navigateur doit télécharger le fichier `.m3u8` (ou le jouer si vous avez une extension HLS).
    *   Pour tester la lecture réelle, utilisez un outil comme [HLSPlayer.net](https://hlsplayer.net/) et collez votre URL (assurez-vous que votre serveur est accessible ou testez en local avec VLC : `Fichier > Ouvrir un flux réseau`).
3.  **Test Waveform** :
    *   Accédez à : `http://votre-domaine.test/media/{CODE}/waveform.json`
    *   Vous devez voir le JSON des données de la forme d'onde.

---

## Commandes Utiles

*   **Scanner les médias** : `php artisan media:scan`
*   **Traiter la file d'attente** : `php artisan queue:work --queue=media_processing`
*   **Vider la configuration (si problème)** : `php artisan config:clear`
