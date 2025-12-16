# Cahier des charges V4 : Système de Traitement des Médias (MMS)

Ce document révisé (v4) intègre une étape de documentation utilisateur.

## Objectifs
1.  **Exploration & Historique** : Scanner et historiser `ScannedFile`.
2.  **Traitement Multi-types** : Diffusion, Waveform, Documents, via `ItemProcessingState`.
3.  **Documentation** : Guide utilisateur intégré.

---

## 1. Architecture des Données (Identique V3)

### 1.1 Modèle `ScannedFile` (Historique Scan)
*   **Table** : `scanned_files`
*   **Champs** : `file_path` (string, index), `disk`, `file_name`, `size`, `status` (assoc./orphan), `item_id`, `last_scanned_at`.

### 1.2 Modèle `MediaVariation` (Résultats)
*   **Table** : `media_variations`
*   **Champs** : `item_id`, `profile_name`, `type` (video, audio, image, data, document), `disk`, `file_path`, `mime_type`, `is_streaming`, `generation_params`, `status`.

### 1.3 Modèle `ItemProcessingState` (Suivi)
*   **Table** : `item_processing_states`
*   **Champs** : `item_id`, `process_type` (unique avec item), `label`, `status`, `message`, `started_at`, `finished_at`.

---

## Plan d'Implémentation & Briefs Techniques

### Étape 1 : Fondations (Modèles & Config)
Création des structures de données.

> [!NOTE]
> **Brief LLM - Étape 1**
> **Objectif** : Créer les 3 tables et modèles.
> **Tâches** :
> 1.  Migration `create_scanned_files_table`.
> 2.  Migration `create_media_variations_table`.
> 3.  Migration `create_item_processing_states_table`.
> 4.  Modèles Eloquent.
> 5.  Settings Page (Filament) pour config (`scan_path`, `ffmpeg`, etc.).

---

### Étape 2 : Exploration (Scan)
Logique de découverte.

> [!NOTE]
> **Brief LLM - Étape 2**
> **Objectif** : Cmd `media:scan` + UI ScannedFile.
> **Tâches** :
> 1.  Cmd `media:scan` (Update `ScannedFile`, Match Code -> Item).
> 2.  UI `ScannedFileResource` (List, Filters).
> 3.  Logique : l'association ne fait que mettre à jour Item, le traitement est asynchrone (Etape 3-4).

---

### Étape 3 : Jobs & Orchestration
Jobs unifiés via `ItemProcessingState`.

> [!NOTE]
> **Brief LLM - Étape 3**
> **Objectif** : Jobs utilisant la table d'états.
> **Tâches** :
> 1.  Trait `HasProcessingState` (`updateState`).
> 2.  Job `GenerateDiffusionMedia` (Update state 'diffusion', FFMPEG, create Variation).
> 3.  Job `GenerateAudiowaveform` (Update state 'waveform', audiowaveform, create Variation).
> 4.  Orchestrateur `ProcessItemMedia`.

---

### Étape 4 : Intégration UI Final
Affichage des états.

> [!NOTE]
> **Brief LLM - Étape 4**
> **Objectif** : UI Item (States + Variations).
> **Tâches** :
> 1.  `ItemResource` : Section "Traitements" (Table/Repeater `processingStates`) + "Variations" (RelationManager).
> 2.  Observer `Item` (file_path dirty -> Orchestrator).

---

### Étape 5 : Documentation Utilisateur
Intégration du guide dans le système existant.

> [!NOTE]
> **Brief LLM - Étape 5**
> **Objectif** : Rédiger la documentation utilisateur pour le nouveau module Média.
> **Contexte** : Le système charge les fichiers MD depuis `resources/docs/*.md`.
> **Tâches** :
> 1.  Créer le fichier `resources/docs/05-gestion-media.md`.
> 2.  **Contenu à rédiger** :
>     *   **Introduction** : Explication du double mode (Scan vs Upload direct).
>     *   **Scan de fichiers** : Comment utiliser l'onglet "Fichiers Scannés" (`ScannedFileResource`), signification des statuts (Orphelin vs Associé), comment lancer un scan manuel.
>     *   **Gestion des Items** : Explication de la section "Traitements" sur la fiche Item (statuts de génération), et de la liste "Variations" (accès aux fichiers streaming/waveform).
>     *   **Configuration** : Brève explication des réglages disponibles (chemins, profils).
> 3.  Vérifier que le fichier est bien détecté par `App\Filament\Pages\Documentation` (le tri est alphabétique sur le filename).
