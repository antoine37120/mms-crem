---
sessionId: session-260511-001425-1nyk
isActive: true
---

# Requirements Japan

### Overview & Goals
L'objectif est d'ajouter un nouveau point de terminaison API dans le `MediaController` permettant de lister, sous forme de JSON, toutes les variations de média associées à un item spécifique, filtrées par type (audio, video, image, data, document).

### Functional Requirements
- **Nouvelle route de listage** : `GET /media/{code}/{type}`.
    - `{type}` correspond aux valeurs de l'énumération `MediaVariationType` (audio, video, image, data, document).
- **Réponse JSON** : Fournir une liste d'objets représentant les variations de média trouvées (incluant l'original si le type correspond).
- **Propriétés incluses** :
    - `code`, `title`, `language_code`
    - `file_name`, `file_size`, `file_type`, `file_extension`
    - `duration`, `upload_date`
    - `uploaded_by`, `created_by`
    - `md5`
    - `url` : URL absolue pour consulter le média via la route média.
- **Compatibilité** : Ne doit pas interférer avec les routes existantes (`waveform.json` et segments HLS `.ts`).
- **CORS** : Inclure le header `Access-Control-Allow-Origin: *`.

### Scope
- **In Scope** : Ajout des routes et méthodes pour le listage et la consultation des variations.
- **Out of Scope** : Modification de la logique de génération des médias.


# Technical Design

### Current Implementation
Le `MediaController` gère actuellement le service des flux HLS, des fichiers originaux et des waveforms. Les variations sont stockées dans `media_variations` avec un `type` et un `profile_name`.

### Proposed Changes

#### 1. Routage (`routes/web.php`)
- `GET /media/{code}/{type}` -> Listage JSON.
- `GET /media/{code}/variation/{profile}` -> Service de fichier brut pour les variations non-streaming.

#### 2. Contrôleur (`MediaController.php`)
- **Méthode `variations`** :
    - Agrège l'original (Item) et les variations correspondantes.
    - Pour chaque élément, remplit les métadonnées de l'Item (code, title, duration, etc.).
    - Pour `file_size` et `file_type`, utilise les valeurs spécifiques à la variation si applicable.
    - L'URL est déterminée intelligemment :
        - `media.master` pour HLS (`hls_standard`) ou l'Original.
        - `media.waveform` pour `waveform_json`.
        - `media.variation` pour les autres profils.
- **Méthode `serve`** :
    - Permet de télécharger ou visualiser n'importe quelle variation par son profil.

#### 3. Correspondance des types
- L'original est inclus si :
    - Type = `audio` et `item->isAudio()`
    - Type = `video` et `item->isVideo()`
    - Type = `image` and `item->isImage()`
    - Type = `document` or `data` (via mapping extension/mime).

### Risks & Mitigations
- **Conflit de route** : Utilisation de `whereIn` pour limiter `{type}` et placement après les routes existantes.
- **Données sensibles** : Seules les propriétés listées par l'utilisateur sont retournées, évitant l'exposition de `generation_params`.


# Delivery Steps

###   Step 1: Ajout des routes dans le fichier de routage web (routes/web.php)
Les nouvelles routes sont enregistrées dans `routes/web.php` pour permettre le listage et la consultation des médias.

- Ajouter la route `GET /media/{code}/{type}` (nom: `media.variations`) avec une contrainte `whereIn` sur le type pour éviter les conflits avec les routes existantes.
- Ajouter la route `GET /media/{code}/variation/{profile}` (nom: `media.variation`) pour servir des fichiers de variations spécifiques.
- Positionner ces routes après les routes HLS et waveform pour garantir la priorité des routes existantes.

###   Step 2: Implémentation du service de consultation des fichiers de variation dans MediaController
Une nouvelle méthode `serve` dans le `MediaController` permet de récupérer et servir n'importe quel fichier de variation par son nom de profil.

- Implémenter la méthode `serve(string $code, string $profile)` dans `app/Http/Controllers/MediaController.php`.
- Rechercher l'item par son code, puis la `MediaVariation` correspondante par son `profile_name`.
- Retourner une réponse de fichier via `Storage::disk()->response()` en incluant le header `Access-Control-Allow-Origin: *`.
- Gérer les cas où l'item, la variation ou le fichier physique est manquant (404).

###   Step 3: Implémentation du listage JSON des médias par type dans MediaController
La méthode `variations` du `MediaController` retourne un JSON complet listant tous les médias correspondants au type demandé avec leurs métadonnées.

- Implémenter la méthode `variations(string $code, string $type)` dans `app/Http/Controllers/MediaController.php`.
- Récupérer l'Item par son code.
- Inclure l'original dans la liste si son type de fichier correspond à la catégorie demandée (audio, video, etc.) en utilisant les méthodes `isAudio()`, `isVideo()`, etc. de l'Item.
- Récupérer toutes les `MediaVariation` de l'item filtrées par le type spécifié.
- Formater chaque entrée JSON avec les propriétés exactes demandées : `code`, `title`, `language_code`, `file_name`, `file_size`, `file_type`, `file_extension`, `duration`, `upload_date`, `uploaded_by`, `created_by`, `md5`.
- Calculer dynamiquement l'URL de consultation (`url`) en fonction du profil (utilisant `media.master`, `media.waveform` ou `media.variation`).
- Retourner la réponse JSON avec le header CORS `Access-Control-Allow-Origin: *`.