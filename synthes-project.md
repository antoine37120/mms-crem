
## Synthèse du Projet MMS (Version Finale)

### Architecture des Modèles

#### 1. **Fonds**
- Niveau le plus élevé de la hiérarchie
- Représente un fonds d'archives complet
- **Cotation libre** : ex. `CNRSMH_Arnaud`
- Géré par la documentaliste

#### 2. **Corpus**
- Subdivision thématique ou chronologique d'un fonds
- **Cotation en cascade** : hérite et complète la cotation du fonds
- Ex. `CNRSMH_Arnaud_001`

#### 3. **Collections**
- Groupes d'enregistrements au sein d'un corpus
- **Cotation en cascade** : hérite et complète la cotation du corpus
- Ex. `CNRSMH_I_2011_001` ou `CNRSMH_E_2011_001_001`

#### 4. **ItemTypes**
- Définit les types d'items disponibles (pour les items secondaires uniquement)
- Gestion centralisée des suffixes et règles de nommage
- **Administration par les Documentalistes**
- Exemples : Traduction, Transcription, Livret, Pochette, etc.
- Extensible selon les besoins du fonds

#### 5. **Items**
- Éléments pouvant être associés aux Fonds, Corpus, Collections ou autres Items
- **Cotation en cascade** : hérite et complète la cotation de l'entité parente
- **Items principaux** : enregistrements audio/vidéo/photo (sans type)
- **Items secondaires** : fichiers associés (avec type obligatoire)
- Un seul fichier par Item

### Liste des Champs par Modèle

#### 1. **Fonds**
```

- id (bigint, PK, auto-increment)
- code (string, unique) // Ex: "CNRSMH_Arnaud"
- title (string, nullable)
- created_by (bigint, FK users)
- created_at (timestamp)
- updated_at (timestamp)
```
#### 2. **Corpus**
```

- id (bigint, PK, auto-increment)
- fonds_id (bigint, FK fonds)
- code (string, unique) // Ex: "CNRSMH_Arnaud_001"
- title (string, nullable)
- created_by (bigint, FK users)
- created_at (timestamp)
- updated_at (timestamp)
```
#### 3. **Collections**
```

- id (bigint, PK, auto-increment)
- corpus_id (bigint, FK corpus)
- code (string, unique) // Ex: "CNRSMH_I_2011_001"
- title (string, nullable)
- created_by (bigint, FK users)
- created_at (timestamp)
- updated_at (timestamp)
```
#### 4. **ItemTypes**
```

- id (bigint, PK, auto-increment)
- name (string) // "Traduction", "Transcription", "Livret", etc.
- suffix (string) // "_TRA", "_TRS", "_livret", etc.
- description (text, nullable)
- requires_language (boolean, default false) // Pour TRA/TRS
- allowed_extensions (json) // ["pdf", "txt", "docx"]
- is_active (boolean, default true)
- created_by (bigint, FK users)
- created_at (timestamp)
- updated_at (timestamp)
```
#### 5. **Items**
```

- id (bigint, PK, auto-increment)
- itemable_type (string) // Polymorphique: Fonds, Corpus, Collection, Item
- itemable_id (bigint) // ID de l'entité parente
- item_type_id (bigint, FK item_types, nullable) // NULL pour items principaux, requis pour items secondaires
- code (string, unique) // Ex: "CNRSMH_I_2011_001_001_001" ou "CNRSMH_I_2011_001_001_001_TRA_en"
- title (string, nullable)
- language_code (string, nullable) // "fr", "en", etc. - utilisé avec item_type_id
- file_path (string)
- file_name (string)
- file_size (bigint)
- file_type (string) // MIME type
- file_extension (string) // wav, mp4, pdf, etc.
- duration (integer, nullable) // Durée en secondes pour audio/vidéo
- upload_date (date) // Date de dépôt
- uploaded_by (bigint, FK users) // Déposant
- created_by (bigint, FK users)
- created_at (timestamp)
- updated_at (timestamp)
```
#### 6. **User** (Mis à jour)
```

- id (bigint, PK, auto-increment)
- name (string)
- email (string, unique)
- email_verified_at (timestamp, nullable)
- password (string)
- admin_access (boolean)
- remember_token (string, nullable)
- two_factor_secret (text, nullable)
- two_factor_recovery_codes (text, nullable)
- created_at (timestamp)
- updated_at (timestamp)
```
### Hiérarchie Complète
```

Fonds → Corpus → Collection → Item → Item (secondaire)
```
**Les Items peuvent être associés à tous les niveaux** :
- **Items principaux** de **Fonds/Corpus/Collection** : enregistrements principaux
- **Items secondaires** de **Fonds/Corpus/Collection/Item** : fichiers associés avec type

### Logique des Types

- **item_type_id = NULL** : Item principal (enregistrement audio/vidéo/photo)
- **item_type_id != NULL** : Item secondaire (traduction, transcription, livret, etc.)
- **Règle** : Si `itemable_type = 'Item'`, alors `item_type_id` est **obligatoire**
- **language_code** : Utilisé uniquement quand `item_type.requires_language = true`

### Système de Cotation en Cascade
```

Fonds : [COTATION_LIBRE]
↓
Corpus : [COTATION_FONDS] + [EXTENSION_CORPUS]
↓
Collection : [COTATION_CORPUS] + [EXTENSION_COLLECTION]
↓
Item Principal : [COTATION_PARENT] + [EXTENSION_ITEM]
↓
Item Secondaire : [COTATION_PARENT] + [SUFFIXE_TYPE] + [_LANGUE]
```
**Exemples d'évolution** :
- **Item principal de Collection** : `CNRSMH_I_2011_001` → `CNRSMH_I_2011_001_001_001`
- **Item secondaire d'Item** : `CNRSMH_I_2011_001_001_001` → `CNRSMH_I_2011_001_001_001_TRA_en`
- **Item secondaire de Collection** : `CNRSMH_I_2011_001` → `CNRSMH_I_2011_001_livret`

### Relations entre Modèles
```

Fonds (1) → (*) Corpus
Corpus (1) → (*) Collections
ItemTypes (1) → (*) Items (pour les items secondaires uniquement)

// Relations polymorphiques pour tous les Items
Fonds (1) → (*) Items
Corpus (1) → (*) Items  
Collections (1) → (*) Items
Items (1) → (*) Items (items secondaires uniquement)
```
### Avantages de cette Architecture Simplifiée

1. **Unification** : Un seul modèle Item pour tous les types de fichiers
2. **Flexibilité** : Items principaux et secondaires dans la même table
3. **Simplicité** : Moins de relations, logique plus claire
4. **Évolutivité** : Facile d'ajouter de nouveaux types
5. **Cohérence** : Même structure pour tous les niveaux
6. **Performance** : Moins de jointures nécessaires
7. **Logique métier claire** : Type obligatoire seulement pour items enfants

### Fonctionnalités Clés

#### Gestion des Types
- **Types flexibles** gérés par les documentalistes
- **Validation automatique** : type requis pour items enfants
- **Suffixes et langues** gérés automatiquement
- **Extensions autorisées** par type

#### Navigation et Recherche
- **Vue unifiée** : tous les items dans une seule interface
- **Filtrage par type** : principaux vs secondaires
- **Recherche par langue** pour les traductions/transcriptions
- **Hiérarchie claire** : parent → enfant

