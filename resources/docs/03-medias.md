# Médias & Items

La gestion des fichiers (Items) est au cœur du MMS. Un **Item** correspond à un fichier unique stocké dans le système.

## Types d'Items

On distingue deux grandes catégories d'items, bien qu'ils soient gérés de manière unifiée :

1.  **Items Principaux** : Ce sont généralement les fichiers audiovisuels (WAV, MP3, MP4...) qui constituent la matière première de l'archive. Ils sont directement rattachés à une Collection (ou parfois à un Corpus/Fonds).
2.  **Items Secondaires** : Ce sont des fichiers associés (PDF, TXT, IMG...) qui complètent un item principal ou une collection. Exemples : traduction, transcription, livret, photo de pochette.

## Ajouter des Items

### Upload unitaire
1.  Allez dans [Médias & Items > Tous les Items](route:filament.mms-admin.resources.items.index).
2.  Cliquez sur **Créer**.
3.  Choisissez l'élément parent (le conteneur de votre fichier, par exemple une Collection).
4.  Si c'est un fichier secondaire, sélectionnez son **Type** (ex: Traduction).
5.  Uploadez le fichier via la zone de glisser-déposer.
6.  Le système extraira automatiquement certaines métadonnées (taille, type).

### Système d'Upload en masse (Upload Manager)
Pour verser une grande quantité de fichiers :
1.  Utilisez le module **Upload Manager**.
2.  Glissez vos fichiers dans la zone d'upload.
3.  Le système placera les fichiers en attente et tentera de suggérer leur rangement (basé sur le nom du fichier).
4.  Vous pourrez ensuite valider le rangement de chaque fichier ou utiliser un fichier CSV pour automatiser le processus.

## Hiérarchie des Items

Un Item peut être parent d'autres Items.
*   *Exemple :* Un fichier audio `Chant_01.wav` (Item Principal) peut avoir comme enfants `Chant_01_transcription.pdf` et `Chant_01_traduction.pdf` (Items Secondaires).

Cette structure permet de garder liés les documents qui se rapportent à un même enregistrement.
