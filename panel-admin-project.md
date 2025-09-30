# Feuille de Route : Développement Interface Filament MMS

## 🎯 Vue d'Ensemble du Projet

**Objectif** : Développer une interface d'administration complète avec Filament v4 pour le MMS (Système de Management de Médias) du CREM, sans intégration Omeka S dans un premier temps.

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

## Documentation Détaillée : Resources et Pages Filament MMS

### Navigation filament
```
📁 Gestion des Archives
├── 🏛️ Fonds
├── 📚 Corpus  
├── 📦 Collections
└── 🔧 Types d'Items

🌳 Explorateur                    ← NOUVELLE SECTION
└── 🗂️ Vue Hiérarchique         ← VUE INTERACTIVE ICI

📁 Médias & Items
├── 🎵 Tous les Items
├── 📤 Upload Items
└── 🔍 Recherche Avancée

📁 Administration
├── 👥 Utilisateurs
└── 📊 Logs & Statistiques
```

### 🏛️ SECTION 1 : RESOURCES - Gestion des Archives

---

#### 1.1 FondResource

**Navigation** : `Gestion des Archives > Fonds`  
**Objectif** : Gérer les fonds d'archives (niveau racine de la hiérarchie)

##### **Formulaire (Create/Edit)**
```
┌─ SECTION PRINCIPALE ─────────────────────────────────────────┐
│ Code* [____________________] (Ex: CNRSMH_Arnaud)            │
│ Titre  [____________________] (Optionnel)                   │
│ Créé par : [Auto-rempli avec utilisateur connecté]         │
└─────────────────────────────────────────────────────────────┘

┌─ ONGLETS RELATIONS ──────────────────────────────────────────┐
│ 📚 Corpus (Lecture seule)                                   │
│   ├─ Liste des corpus enfants avec liens d'édition         │
│   └─ Bouton "Créer Nouveau Corpus"                         │
│                                                             │
│ 🎵 Items Directs (Lecture seule)                           │
│   ├─ Liste des items associés directement au fonds         │
│   └─ Bouton "Associer Item Existant"                       │
└─────────────────────────────────────────────────────────────┘

Actions : [Sauvegarder] [Voir Hiérarchie] [Dupliquer]
```


##### **Table (Liste)**
```
┌─ FILTRES ────────────────────────────────────────────────────┐
│ Recherche: [________] | Créé par: [____] | Date: [____]     │
└─────────────────────────────────────────────────────────────┘

┌─ TABLEAU ────────────────────────────────────────────────────┐
│ Code ↕       │ Titre         │ Corpus │ Items │ Créé le      │
├─────────────┼──────────────┼────────┼───────┼──────────────┤
│ CNRSMH_A    │ Fonds Arnaud │   3    │  15   │ 2024-01-15   │
│ CNRSMH_B    │ Fonds Béart  │   1    │   8   │ 2024-02-01   │
└─────────────┴──────────────┴────────┴───────┴──────────────┘

Actions par ligne : [👁 Voir] [✏️ Éditer] [🌳 Hiérarchie] [🗑 Supprimer]
```


##### **Page de Vue (View)**
```
┌─ EN-TÊTE ────────────────────────────────────────────────────┐
│ 🏛️ CNRSMH_Arnaud - Fonds Arnaud                            │
│ Créé le 15/01/2024 par Marie Dupont                        │
│ [✏️ Éditer] [🌳 Voir Hiérarchie] [📊 Statistiques]        │
└─────────────────────────────────────────────────────────────┘

┌─ STATISTIQUES ───────────────────────────────────────────────┐
│ 📚 3 Corpus     🎵 15 Items     💾 2.3 GB     📅 Actif      │
└─────────────────────────────────────────────────────────────┘

┌─ CORPUS ENFANTS ─────────────────────────────────────────────┐
│ • CNRSMH_Arnaud_001 - Chants rituels                       │
│ • CNRSMH_Arnaud_002 - Musique instrumentale                │
│ • CNRSMH_Arnaud_003 - Contes                               │
└─────────────────────────────────────────────────────────────┘

┌─ ITEMS DIRECTS ──────────────────────────────────────────────┐
│ 📄 CNRSMH_Arnaud_presentation.pdf (Documentation générale)  │
│ 📄 CNRSMH_Arnaud_bibliography.pdf (Bibliographie)          │
└─────────────────────────────────────────────────────────────┘

┌─ ACTIVITÉ RÉCENTE ───────────────────────────────────────────┐
│ 📅 25/09/2024 - Ajout corpus "Contes" par Marie Dupont     │
│ 📅 20/09/2024 - Upload bibliographie par Jean Martin      │
└─────────────────────────────────────────────────────────────┘
```


---

#### 1.2 CorpusResource

**Navigation** : `Gestion des Archives > Corpus`  
**Objectif** : Gérer les subdivisions thématiques des fonds

##### **Formulaire (Create/Edit)**
```
┌─ SECTION PRINCIPALE ─────────────────────────────────────────┐
│ Fonds Parent* [Dropdown: Liste des fonds disponibles]      │
│ Code* [____________________] (Auto-suggestion basée parent) │
│ Titre  [____________________]                               │
│ Créé par : [Auto-rempli]                                   │
└─────────────────────────────────────────────────────────────┘

┌─ BREADCRUMB HIÉRARCHIQUE ────────────────────────────────────┐
│ 🏛️ CNRSMH_Arnaud > 📚 [Nouveau Corpus]                    │
└─────────────────────────────────────────────────────────────┘

┌─ ONGLETS RELATIONS ──────────────────────────────────────────┐
│ 📦 Collections (Lecture seule)                             │
│   └─ Liste des collections enfants                         │
│                                                             │
│ 🎵 Items Directs (Lecture seule)                           │
│   └─ Items associés au niveau corpus                       │
└─────────────────────────────────────────────────────────────┘
```


##### **Table (Liste)**
```
┌─ FILTRES ────────────────────────────────────────────────────┐
│ Fonds: [Dropdown] | Recherche: [_____] | Statut: [Actif]   │
└─────────────────────────────────────────────────────────────┘

┌─ TABLEAU ────────────────────────────────────────────────────┐
│ Fonds Parent │ Code             │ Titre          │ Collect. │
├─────────────┼──────────────────┼───────────────┼──────────┤
│ CNRSMH_A    │ CNRSMH_A_001     │ Chants rituels │    5     │
│ CNRSMH_A    │ CNRSMH_A_002     │ Musique instr. │    3     │
│ CNRSMH_B    │ CNRSMH_B_001     │ Danses        │    2     │
└─────────────┴──────────────────┴───────────────┴──────────┘
```


##### **Page de Vue (View)**
```
┌─ BREADCRUMB ─────────────────────────────────────────────────┐
│ 🏛️ CNRSMH_Arnaud > 📚 CNRSMH_Arnaud_001 - Chants rituels  │
└─────────────────────────────────────────────────────────────┘

┌─ INFORMATIONS ───────────────────────────────────────────────┐
│ Code: CNRSMH_Arnaud_001                                     │
│ Titre: Chants rituels                                       │
│ Fonds parent: CNRSMH_Arnaud - Fonds Arnaud                 │
│ 📦 5 Collections     🎵 12 Items     💾 1.8 GB             │
└─────────────────────────────────────────────────────────────┘

┌─ ARBORESCENCE ───────────────────────────────────────────────┐
│ ├─ 📦 CNRSMH_I_2011_001 - Cérémonies mariage              │
│ ├─ 📦 CNRSMH_I_2011_002 - Rituels funéraires              │
│ └─ 📦 CNRSMH_I_2011_003 - Chants saisonniers              │
└─────────────────────────────────────────────────────────────┘
```


---

#### 1.3 CollectionResource

**Navigation** : `Gestion des Archives > Collections`  
**Objectif** : Gérer les groupes d'enregistrements

##### **Formulaire (Create/Edit)**
```
┌─ SECTION PRINCIPALE ─────────────────────────────────────────┐
│ Corpus Parent* [Dropdown avec recherche]                   │
│ Code* [____________________] (Validation format CNRSMH)    │
│ Titre  [____________________]                               │
│ Créé par : [Auto-rempli]                                   │
└─────────────────────────────────────────────────────────────┘

┌─ BREADCRUMB COMPLET ─────────────────────────────────────────┐
│ 🏛️ CNRSMH_Arnaud > 📚 CNRSMH_A_001 > 📦 [Nouvelle Coll.]  │
└─────────────────────────────────────────────────────────────┘

┌─ ASSISTANT COTATION ─────────────────────────────────────────┐
│ Suggestion basée sur le corpus parent:                      │
│ [CNRSMH_I_2024_003] [Accepter] [Modifier manuellement]     │
└─────────────────────────────────────────────────────────────┘
```


##### **Table (Liste)**
```
┌─ FILTRES AVANCÉS ────────────────────────────────────────────┐
│ Fonds: [____] | Corpus: [____] | Type: [I/E] | Année: [___] │
│ Statut: [Actif] | Items: [Avec/Sans] | Recherche: [_____]   │
└─────────────────────────────────────────────────────────────┘

┌─ TABLEAU DÉTAILLÉ ───────────────────────────────────────────┐
│ Code Collection    │ Titre         │ Corpus    │ Items │ Taille │
├───────────────────┼──────────────┼──────────┼───────┼────────┤
│ 🟢 CNRSMH_I_2011_001 │ Cérémonies   │ Rituels  │  25   │ 2.1GB  │
│ 🟡 CNRSMH_E_2011_001 │ Album Béart  │ Musique  │  12   │ 800MB  │
│ 🔴 CNRSMH_I_2012_001 │ En cours     │ Contes   │   3   │ 150MB  │
└───────────────────┴──────────────┴──────────┴───────┴────────┘

Légende: 🟢 Complet | 🟡 Partiel | 🔴 En cours
```


---

#### 1.4 ItemTypeResource

**Navigation** : `Gestion des Archives > Types d'Items`  
**Objectif** : Configuration des types de fichiers secondaires (Administration Documentalistes)

##### **Formulaire (Create/Edit)**
```
┌─ INFORMATIONS DE BASE ───────────────────────────────────────┐
│ Nom du type* [____________________] (Ex: Traduction)        │
│ Suffixe* [____________________] (Ex: _TRA)                  │
│ Description [________________________________]              │
│             [________________________________]              │
└─────────────────────────────────────────────────────────────┘

┌─ CONFIGURATION ──────────────────────────────────────────────┐
│ ☑ Nécessite un code langue (pour TRA/TRS)                  │
│ ☑ Type actif                                                │
│                                                             │
│ Extensions autorisées:                                      │
│ [+ Ajouter] [pdf] [txt] [docx] [odt]                       │
│                                                             │
│ Exemple de nommage final:                                   │
│ CNRSMH_I_2011_001_001_001_TRA_fr.pdf                      │
└─────────────────────────────────────────────────────────────┘
```


##### **Table (Liste)**
```
┌─ ACTIONS RAPIDES ────────────────────────────────────────────┐
│ [+ Nouveau Type] [📥 Import] [📤 Export]                   │
└─────────────────────────────────────────────────────────────┘

┌─ TABLEAU ────────────────────────────────────────────────────┐
│ Nom         │ Suffixe │ Langue │ Extensions  │ Utilisé │ Statut │
├────────────┼─────────┼────────┼─────────────┼─────────┼────────┤
│ Traduction │ _TRA    │   ✓    │ pdf,txt,doc │   45    │ 🟢     │
│ Livret     │ _livret │   ✗    │ pdf         │   12    │ 🟢     │
│ Pochette   │ _P      │   ✗    │ jpg,png,pdf │   23    │ 🟢     │
│ Archive    │ _arch   │   ✗    │ zip,rar     │    0    │ 🔴     │
└────────────┴─────────┴────────┴─────────────┴─────────┴────────┘

Statut: 🟢 Actif | 🔴 Inactif
```


---

### 🎵 SECTION 2 : RESOURCES - Médias & Items

---

#### 2.1 ItemResource

**Navigation** : `Médias & Items > Tous les Items`  
**Objectif** : Gestion unifiée de tous les items (principaux et secondaires)

##### **Formulaire (Create/Edit)**
```
┌─ ASSOCIATION PARENT ─────────────────────────────────────────┐
│ Associé à* [Dropdown: Fonds/Corpus/Collection/Item]        │
│ Élément parent* [Dropdown dynamique selon type]            │
│                                                             │
│ Breadcrumb automatique:                                     │
│ 🏛️ CNRSMH_A > 📚 CNRSMH_A_001 > 📦 CNRSMH_I_2011_001     │
└─────────────────────────────────────────────────────────────┘

┌─ TYPE ET COTATION ───────────────────────────────────────────┐
│ Type d'item [Dropdown: Vide=Principal | Traduction|...]    │
│ Code* [____________________] (Auto-généré ou manuel)       │
│ Titre [____________________]                                │
│ Langue [__] (Si type nécessite langue)                     │
└─────────────────────────────────────────────────────────────┘

┌─ FICHIER ────────────────────────────────────────────────────┐
│ Upload fichier* [Zone glisser-déposer]                     │
│ Extensions autorisées: .wav, .mp4, .pdf (selon type)      │
│                                                             │
│ Métadonnées automatiques:                                   │
│ └─ Taille: [Auto]  Type MIME: [Auto]  Durée: [Auto]       │
└─────────────────────────────────────────────────────────────┘

┌─ INFORMATIONS UPLOAD ────────────────────────────────────────┐
│ Date upload: [Auto: 25/09/2024]                           │
│ Uploadé par: [Auto: utilisateur connecté]                 │
│ Créé par: [Auto: utilisateur connecté]                    │
└─────────────────────────────────────────────────────────────┘
```


##### **Table (Liste) - Vue Avancée**
```
┌─ FILTRES INTELLIGENTS ───────────────────────────────────────┐
│ Type parent: [Fonds] [Corpus] [Collection] [Item]          │
│ Type item: [Principal] [Secondaire] | Format: [Audio] [PDF] │
│ Utilisateur: [Mes items] [Tous] | Période: [Cette semaine] │
│ Recherche globale: [_________________________]              │
└─────────────────────────────────────────────────────────────┘

┌─ TABLEAU PRINCIPAL ──────────────────────────────────────────┐
│ Code Item             │ Parent    │ Type │ Format │ Taille │ Upload   │
├──────────────────────┼──────────┼──────┼────────┼────────┼──────────┤
│ 🎵 CNRSMH_I_001_01   │ 📦 Coll01│  -   │ .wav   │ 45MB   │ 25/09    │
│   ├─📎 _TRA_en.pdf   │    "     │ TRA  │ .pdf   │ 2MB    │ 25/09    │
│   └─📎 _TRS_fr.txt   │    "     │ TRS  │ .txt   │ 15KB   │ 26/09    │
│ 🎵 CNRSMH_I_001_02   │ 📦 Coll01│  -   │ .mp4   │ 120MB  │ 24/09    │
│ 📄 CNRSMH_A_doc.pdf  │ 🏛️ FondA │ DOC  │ .pdf   │ 5MB    │ 20/09    │
└──────────────────────┴──────────┴──────┴────────┴────────┴──────────┘

Actions par ligne : [👁 Voir] [✏️ Éditer] [⬇ Télécharger] [🌳 Hiérarchie] [🗑 Supprimer]
```


##### **Page de Vue (View) - Détaillée**
```
┌─ EN-TÊTE CONTEXTUEL ─────────────────────────────────────────┐
│ 🎵 CNRSMH_I_2011_001_001_001.wav                           │
│ Item principal - Chant rituel de mariage                    │
│ 🏛️ CNRSMH_A > 📚 Rituels > 📦 Cérémonies                  │
│ [✏️ Éditer] [⬇ Télécharger] [🔗 Lien Direct] [🗑 Suppr.]  │
└─────────────────────────────────────────────────────────────┘

┌─ PLAYER AUDIO INTÉGRÉ ───────────────────────────────────────┐
│ ▶️ ──────●────────── 3:45 / 12:30                         │
│ 🔊 ──●── Vitesse: 1x | Télécharger | Partager              │
└─────────────────────────────────────────────────────────────┘

┌─ MÉTADONNÉES TECHNIQUES ─────────────────────────────────────┐
│ Format: WAV 48kHz/24bit | Taille: 45.2 MB | Durée: 12:30   │
│ Uploadé: 25/09/2024 par Marie Dupont                       │
│ Chemin: /storage/2024/09/25/CNRSMH_I_2011_001_001_001.wav  │
└─────────────────────────────────────────────────────────────┘

┌─ ITEMS ASSOCIÉS (ENFANTS) ───────────────────────────────────┐
│ 📎 CNRSMH_I_2011_001_001_001_TRA_en.pdf - Traduction EN    │
│ 📎 CNRSMH_I_2011_001_001_001_TRS_fr.txt - Transcription FR │
│ [➕ Ajouter Item Associé]                                  │
└─────────────────────────────────────────────────────────────┘

┌─ HISTORIQUE ─────────────────────────────────────────────────┐
│ 📅 26/09/2024 - Ajout transcription FR par Jean Martin     │
│ 📅 25/09/2024 - Upload fichier original par Marie Dupont   │
└─────────────────────────────────────────────────────────────┘
```


---

#### 2.2 UserResource (Mise à Jour)

**Navigation** : `Administration > Utilisateurs`  
**Objectif** : Gestion des comptes et permissions

##### **Formulaire (Create/Edit)**
```
┌─ INFORMATIONS PERSONNELLES ──────────────────────────────────┐
│ Nom complet* [____________________]                         │
│ Email* [____________________]                               │
│ Mot de passe* [____________________] [👁 Révéler]          │
└─────────────────────────────────────────────────────────────┘

┌─ PERMISSIONS MMS ────────────────────────────────────────────┐
│ Rôle: ○ Chercheur  ○ Documentaliste  ○ Administrateur     │
│                                                             │
│ Permissions détaillées:                                     │
│ ☐ Accès admin Filament                                     │
│ ☐ Upload en lot                                            │
│ ☐ Suppression items autres utilisateurs                    │
│ ☐ Gestion types d'items                                    │
│ ☐ Accès logs système                                       │
└─────────────────────────────────────────────────────────────┘

┌─ STATISTIQUES D'ACTIVITÉ ────────────────────────────────────┐
│ Items uploadés: [Lecture seule - auto calculé]            │
│ Dernière connexion: [Auto]                                 │
│ Statut: ○ Actif  ○ Suspendu                               │
└─────────────────────────────────────────────────────────────┘
```


---

### 🌳 SECTION 3 : PAGES CUSTOM

---
Basé sur votre demande de redéfinition du cahier des charges pour la page de l'admin, voici la section mise à jour de l'explorateur hiérarchique avec une approche en deux panneaux :

## 🌳 SECTION 3 : PAGES CUSTOM

---

Voici la section mise à jour pour l'Explorateur Hiérarchique selon vos contraintes de design :

## 🌳 SECTION 3 : PAGES CUSTOM

---

#### 3.1 HierarchyExplorer

**Navigation** : `Explorateur > Vue Hiérarchique`  
**Objectif** : Navigation interactive dans l'arborescence complète avec exploration hiérarchique à gauche et liste détaillée à droite

##### **Contraintes de Design**
- **Interface épurée** : Éviter l'aspect caricatural d'un explorateur de fichiers
- **Usage minimal d'icônes** : Privilégier le texte et la typographie pour la navigation
- **Proportions équilibrées** : Panneau gauche (1/3) et panneau droit (2/3)
- **Filtres en en-tête** : Remplacer la barre d'actions par les contrôles essentiels
- **Interface condensée** : Pas de panneau d'informations contextuelles en bas
- **Hiérarchie claire** : Organisation visuelle par indentation et espacement

##### **Interface 2 Panneaux (1/3 - 2/3)**
```
┌─ CONTRÔLES & FILTRES ────────────────────────────────────────────────────────┐
│ Recherche: [_________________________] [Afficher items directs] [Compact]   │
│ Contrôle densité: ●─────○                                                   │
└─────────────────────────────────────────────────────────────────────────────┘

┌─ PANNEAU GAUCHE (1/3) ──────────────────┬─ PANNEAU DROITE (2/3) ─────────────┐
│ ┌─────────────────────────────────────┐ │ ┌─ Informations Sélection ────────┐ │
│ │ ▼ CNRSMH_Arnaud                     │ │ │ Collection: Cérémonies mariage  │ │
│ │   ├─ ▼ Rituels                      │ │ │ 15 items • 23 sub • 3.2 GB     │ │
│ │   │  ├─ ► Mariages ◄                │ │ │ Modifié: 25/09/24 - M. Dupont   │ │
│ │   │  ├─ ► Funéraires                │ │ └─────────────────────────────────┘ │
│ │   │  └─ ► Saisonniers               │ │                                     │
│ │   └─ ► Musique                      │ │ ┌─ Items (non sub) ──────────────┐ │
│ │                                     │ │ │ ▼ CNRSMH_I_2011_001_001.wav    │ │
│ │ ▼ CNRSMH_Béart                      │ │ │   └─ TRA_en.pdf                 │ │
│ │   └─ ► Archives                     │ │ │   └─ TRS_fr.txt                 │ │
│ │                                     │ │ │                                 │ │
│ │ Items directs fonds:                │ │ │ ► CNRSMH_I_2011_001_002.wav    │ │
│ │   presentation.pdf                  │ │ │   (2 items sub)                │ │
│ │   bibliography.pdf                  │ │ │                                 │ │
│ │                                     │ │ │ ► CNRSMH_I_2011_001_003.mp4    │ │
│ │ Items directs corpus:               │ │ │   (aucun item sub)              │ │
│ │   corpus_notes.pdf                  │ │ └─────────────────────────────────┘ │
│ │                                     │ │                                     │
│ │ Items directs collection:           │ │ ┌─ Items Sub Directs ─────────────┐ │
│ │   pochette.jpg                      │ │ │ ▼ CNRSMH_I_2011_001_livret.pdf │ │
│ │   livret.pdf                        │ │ │   └─ TRA_en.pdf                 │ │
│ │                                     │ │ │                                 │ │
│ │ Nouveau Fonds                       │ │ │ ► CNRSMH_I_2011_001_notes.txt  │ │
│ │ Nouveau Corpus                      │ │ │   (aucun item sub)              │ │
│ │ Nouvelle Collection                 │ │ └─────────────────────────────────┘ │
│ │ Nouvel Item                         │ │                                     │
│ └─────────────────────────────────────┘ │ Actions: [Voir] [Éditer] [Export]  │
└─────────────────────────────────────────┴─────────────────────────────────────┘
```


##### **Fonctionnalités Clés du Panneau Gauche**

###### **Arbre Hiérarchique Épuré**
- **Navigation textuelle** : Utilisation de caractères `▼` `►` `◄` pour l'état des nœuds
- **Indentation progressive** : Structure claire par espacement sans surutilisation d'icônes
- **État persistant** : Conservation des éléments dépliés pendant la navigation
- **Sélection active** : Indication visuelle `◄` pour l'élément actuellement sélectionné
- **Hauteur adaptative** : Contrôle de densité via curseur pour optimiser l'affichage

###### **Affichage Contextuel des Items**
- **Items directs visibles** : Listage textuel simple selon le niveau sélectionné
- **Toggle contrôlé** : Possibilité de masquer/afficher les items directs via le filtre
- **Actions de création** : Liens textuels simples en bas de colonne
- **Mise à jour dynamique** : Affichage adapté selon la sélection active

##### **Fonctionnalités Clés du Panneau Droite**

###### **Informations de Contexte (En-tête)**
- **Résumé synthétique** : Type d'élément, nom, et statistiques essentielles
- **Métadonnées clés** : Nombre d'items, taille totale, dernière modification
- **Pas de redondance** : Remplace le panneau d'informations contextuelles du bas

###### **Organisation en 3 Niveaux**
1. **Section "Items (non sub)"**
    - Items principaux avec mécanisme de dépliant (`▼` déplié, `►` plié)
    - Indication textuelle du nombre d'items sub disponibles
    - Affichage des items sub indentés sous leur parent

2. **Section "Items Sub Directs"**
    - Items secondaires associés directement au niveau sélectionné
    - Même mécanisme de dépliant récursif
    - Organisation distincte pour clarifier la hiérarchie

3. **Dépliant Récursif**
    - Support jusqu'à 3+ niveaux avec indentations croissantes
    - Indicateurs textuels pour les éléments expandables
    - Navigation fluide sans surcharge visuelle

###### **Interface Adaptive**
- **Dépliant intelligent** : Indication textuelle du nombre d'éléments disponibles
- **Actions contextuelles** : Boutons d'action adaptés au type d'item sélectionné
- **Navigation rapide** : Liens directs vers les ressources Filament

##### **Contrôles & Filtres (En-tête)**

###### **Recherche Unifiée**
- **Champ de recherche principal** : Recherche textuelle sur codes et titres
- **Filtrage temps réel** : Mise à jour immédiate de l'affichage

###### **Options d'Affichage**
- **Toggle "Afficher items directs"** : Contrôle de la visibilité dans l'arbre gauche
- **Toggle "Compact"** : Mode d'affichage dense ou aéré
- **Curseur de densité** : Contrôle fin de l'espacement et de la taille des éléments

##### **Avantages de cette Approche Épurée**

1. **Interface Professionnelle** : Évite l'aspect "explorateur de fichiers" par un design textuel épuré
2. **Proportions Équilibrées** : Répartition 1/3 - 2/3 pour un affichage optimal du contenu
3. **Navigation Efficace** : Conservation de l'état d'exploration avec contrôles minimalistes
4. **Contexte Toujours Visible** : Informations synthétiques en en-tête du panneau droit
5. **Flexibilité d'Affichage** : Contrôles de densité pour s'adapter aux préférences utilisateur
6. **Actions Contextuelles** : Boutons d'action situés logiquement sans encombrement

##### **Actions Contextuelles Dynamiques**
- **Sélection dans l'arbre** : Met à jour automatiquement le panneau droit et les informations de contexte
- **Dépliant textuel** : Indicateurs visuels minimalistes pour les éléments contenant des sous-éléments
- **Actions de création** : Liens textuels simples adaptés au contexte de sélection
- **Filtrage intelligent** : Mise à jour temps réel selon la recherche et les options d'affichage

Cette approche respecte vos contraintes de design en privilégiant une interface épurée, textuelle et équilibrée, tout en conservant la fonctionnalité complète de navigation hiérarchique et de gestion des items à plusieurs niveaux.
---

#### 3.2 UploadItems

**Navigation** : `Médias & Items > Upload Items`  
**Objectif** : Interface d'upload simple et en lot

##### **Mode Upload Individuel**
```
┌─ SÉLECTION DESTINATION ──────────────────────────────────────┐
│ 🎯 Associer à:                                              │
│ Mode: ○ Collection  ○ Corpus  ○ Fonds  ○ Item Parent      │
│                                                             │
│ Navigation hiérarchique:                                    │
│ 🏛️ [Dropdown Fonds] > 📚 [Dropdown Corpus] > 📦 [Coll.]   │
│                                                             │
│ Sélection actuelle:                                         │
│ 📦 CNRSMH_I_2011_001 - Cérémonies de mariage              │
└─────────────────────────────────────────────────────────────┘

┌─ UPLOAD FICHIER ─────────────────────────────────────────────┐
│ ┌─────────────────────────────────────────────────────────┐ │
│ │     Glissez votre fichier ici ou cliquez pour choisir  │ │
│ │                                                         │ │
│ │         📁 Formats acceptés: WAV, MP4, PDF, TXT        │ │
│ │           Taille max: 500MB par fichier                │ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                             │
│ Type d'item: [Dropdown: Principal/Traduction/Livret...]    │
│ Langue: [__] (si nécessaire)                              │
│ Code suggéré: CNRSMH_I_2011_001_026_001 [Modifier]        │
└─────────────────────────────────────────────────────────────┘

┌─ APERÇU PRE-UPLOAD ──────────────────────────────────────────┐
│ Nom original: ceremonie_mariage_village.wav                │
│ Taille: 45.2 MB | Durée: 12:30 | Format: WAV 48kHz        │
│ Nom final: CNRSMH_I_2011_001_026_001.wav                  │
│                                                             │
│ [🚀 Uploader] [❌ Annuler]                                 │
└─────────────────────────────────────────────────────────────┘
```


##### **Mode Upload en Lot**
```
┌─ FICHIER CSV MAPPING ────────────────────────────────────────┐
│ 1. Téléchargez le modèle CSV: [📥 Télécharger modèle]      │
│ 2. Remplissez: nom_fichier | collection_code | type        │
│ 3. Uploadez CSV: [Choisir fichier CSV]                     │
└─────────────────────────────────────────────────────────────┘

┌─ UPLOAD FICHIERS ────────────────────────────────────────────┐
│ ┌─────────────────────────────────────────────────────────┐ │
│ │        Glissez TOUS vos fichiers ici (ZIP possible)    │ │
│ │                   Fichiers multiples                    │ │
│ └─────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘

┌─ APERÇU TRAITEMENT ──────────────────────────────────────────┐
│ ✅ ceremonie1.wav → CNRSMH_I_2011_001 (Principal)          │
│ ✅ livret.pdf → CNRSMH_I_2011_001 (Livret)                 │
│ ⚠️  fichier3.mp3 → Format non supporté                     │
│ ❌ fichier4.wav → Collection inexistante                   │
│                                                             │
│ Résumé: 12 OK | 3 Avertissements | 1 Erreur               │
│ [🔧 Corriger Erreurs] [🚀 Lancer Traitement]              │
└─────────────────────────────────────────────────────────────┘
```


---

#### 3.3 AdvancedSearch

**Navigation** : `Médias & Items > Recherche Avancée`  
**Objectif** : Moteur de recherche multi-critères puissant

##### **Interface de Recherche**
```
┌─ CRITÈRES DE RECHERCHE ──────────────────────────────────────┐
│ Texte libre: [_____________________] (code, titre, nom)     │
│                                                             │
│ Hiérarchie:                                                 │
│ Fonds: [____] | Corpus: [____] | Collection: [____]        │
│                                                             │
│ Type et format:                                             │
│ Type item: [Principal/Secondaire] | Format: [Audio/PDF]    │
│                                                             │
│ Utilisateurs et dates:                                      │
│ Créé par: [____] | Uploadé par: [____]                     │
│ Période upload: [____] à [____]                            │
│                                                             │
│ Taille et durée:                                            │
│ Taille: [____] à [____] MB | Durée: [____] à [____] min   │
└─────────────────────────────────────────────────────────────┘

┌─ RÉSULTATS DE RECHERCHE ─────────────────────────────────────┐
│ 🔍 45 résultats trouvés | Durée recherche: 0.2s            │
│                                                             │
│ Tri: [Pertinence] [Date] [Taille] [Nom] | Vue: [Liste][📊] │
│                                                             │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │🎵 CNRSMH_I_2011_001_001_001.wav        📍Collection001  │ │
│ │  Chant rituel de mariage - 45MB - 12:30 - 25/09/2024   │ │
│ │  [👁 Voir] [⬇ Télécharger] [🌳 Hiérarchie] [📋 Copier] │ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                             │
│ [🔖 Sauvegarder Recherche] [📤 Exporter Résultats]        │
└─────────────────────────────────────────────────────────────┘
```


---

#### 3.4 Dashboard (Page d'Accueil)

**Navigation** : `Accueil`  
**Objectif** : Vue d'ensemble et accès rapides

##### **Widgets et Statistiques**
```
┌─ WIDGETS STATISTIQUES ───────────────────────────────────────┐
│ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌────────────┐│
│ │📁 12 Fonds  │ │🎵 2,345     │ │💾 485 GB    │ │👥 28 Users ││
│ │   (+2 mois) │ │   Items     │ │   Stockage  │ │   Actifs   ││
│ └─────────────┘ └─────────────┘ └─────────────┘ └────────────┘│
└─────────────────────────────────────────────────────────────┘

┌─ ACTIVITÉ RÉCENTE ───────────────────────────────────────────┐
│ 📅 Aujourd'hui                                              │
│ • 15:30 - Marie D. a uploadé 3 items audio                 │
│ • 14:15 - Jean M. a créé la collection CNRSMH_I_2024_015   │
│ • 11:45 - Pierre L. a ajouté une traduction EN              │
│                                                             │
│ [Voir toute l'activité]                                     │
└─────────────────────────────────────────────────────────────┘

┌─ ACCÈS RAPIDES ──────────────────────────────────────────────┐
│ [🚀 Upload Rapide] [🌳 Explorateur] [🔍 Rechercher]        │
│ [📊 Rapport Mensuel] [⚙️ Config Types] [📁 Mes Items]      │
└─────────────────────────────────────────────────────────────┘

┌─ COLLECTIONS RÉCENTES ───────────────────────────────────────┐
│ • 📦 CNRSMH_I_2024_015 - Chants contemporains (En cours)    │
│ • 📦 CNRSMH_E_2024_003 - Album Dupont (Complet)            │
│ • 📦 CNRSMH_I_2024_012 - Musique instrumentale (Archivé)   │
└─────────────────────────────────────────────────────────────┘
```


---

### 🔐 SECTION 4 : LOGIQUES MÉTIER ET PERMISSIONS

#### Permissions par Rôle dans les Interfaces

##### **Chercheur**
- **Formulaires** : Champs `created_by` automatiques, pas d'édition autres utilisateurs
- **Tables** : Filtre automatique "Mes items", actions limitées
- **Pages Custom** : Upload individuel uniquement, pas d'accès admin

##### **Documentaliste**
- **Formulaires** : Accès complet, gestion types d'items
- **Tables** : Vue globale, actions étendues sauf suppression lot
- **Pages Custom** : Toutes fonctionnalités sauf administration système

##### **Administrateur**
- **Accès complet** : Toutes interfaces, toutes actions
- **Pages admin** : Logs, statistiques, configuration système
- **Actions destructives** : Suppression lot, purge, maintenance

## Réalisation par phase

## 📋 PHASE 1 : Fondations & Structure de Base

### 1.1 Configuration Filament & Architecture
**Durée estimée : 1-2 jours**

```php
// app/Providers/Filament/AdminPanelProvider.php
public function panel(Panel $panel): Panel
{
    return $panel
        ->id('admin')
        ->path('/admin')
        ->navigationGroups([
            NavigationGroup::make('Gestion des Archives')
                ->icon('heroicon-o-archive-box'),
            NavigationGroup::make('Explorateur')
                ->icon('heroicon-o-folder-tree'),
            NavigationGroup::make('Médias & Items')
                ->icon('heroicon-o-photo'),
            NavigationGroup::make('Administration')
                ->icon('heroicon-o-cog-6-tooth'),
        ]);
}
```


### 1.2 Resources de Base
**Durée estimée : 3-4 jours**

#### **FondResource.php**
```php
class FondResource extends Resource
{
    protected static ?string $navigationGroup = 'Gestion des Archives';
    protected static ?int $navigationSort = 1;
    
    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('code')
                ->required()
                ->unique(ignoreRecord: true),
            TextInput::make('title'),
            Tabs::make('Relations')
                ->tabs([
                    Tab::make('Corpus')->schema([
                        Repeater::make('corpuses')->relationship()
                    ]),
                    Tab::make('Items Directs')->schema([
                        Repeater::make('items')->relationship()
                    ])
                ])
        ]);
    }
}
```
php artisan make:filament-resource Fond --generate  --view  --soft-deletes
php artisan make:filament-resource Corpus --generate  --view  --soft-deletes
php artisan make:filament-resource Collection --generate  --view  --soft-deletes
php artisan make:filament-resource ItemType --generate  --view  --soft-deletes
php artisan make:filament-resource Item --generate  --view  --soft-deletes

**Livrables :**
- ✅ FondResource.php
- ✅ CorpusResource.php
- ✅ CollectionResource.php
- ✅ ItemTypeResource.php
- ✅ UserResource.php

---

## 📋 PHASE 2 : Resource Items Complexe

### 2.1 ItemResource avec Relations Polymorphiques
**Durée estimée : 5-6 jours**

```php
class ItemResource extends Resource
{
    protected static ?string $navigationGroup = 'Médias & Items';
    
    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('itemable_type')
                ->label('Associé à')
                ->options([
                    Fond::class => 'Fonds',
                    Corpus::class => 'Corpus',
                    Collection::class => 'Collection',
                    Item::class => 'Item Parent',
                ])
                ->reactive(),
            
            Select::make('itemable_id')
                ->label('Élément Parent')
                ->options(fn ($get) => match($get('itemable_type')) {
                    Fond::class => Fond::pluck('title', 'id'),
                    Corpus::class => Corpus::pluck('title', 'id'),
                    // ...
                }),
                
            Select::make('item_type_id')
                ->relationship('itemType', 'name')
                ->nullable()
                ->reactive(),
                
            TextInput::make('language_code')
                ->visible(fn ($get) => $get('item_type_id') && 
                    ItemType::find($get('item_type_id'))?->requires_language),
                
            FileUpload::make('file_path')
                ->required()
                ->acceptedFileTypes(['audio/*', 'video/*', 'image/*', 'application/pdf'])
        ]);
    }
    
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable(),
                BadgeColumn::make('itemable_type')
                    ->formatStateUsing(fn ($state) => match($state) {
                        Fond::class => '🏛️ Fonds',
                        Corpus::class => '📚 Corpus',
                        Collection::class => '📦 Collection',
                        Item::class => '🎵 Item',
                    }),
                TextColumn::make('itemable.code')->label('Parent'),
                TextColumn::make('file_extension'),
                TextColumn::make('formatted_file_size'),
            ])
            ->filters([
                SelectFilter::make('itemable_type')->options([...]),
                Filter::make('is_main')->query(fn ($q) => $q->whereNull('item_type_id')),
                Filter::make('is_secondary')->query(fn ($q) => $q->whereNotNull('item_type_id')),
            ]);
    }
}
```


**Livrables :**
- ✅ ItemResource.php complet avec relations polymorphiques
- ✅ Filtres avancés par type de parent
- ✅ Upload de fichiers avec validation
- ✅ Génération automatique des métadonnées

---

## 📋 PHASE 3 : Explorateur Hiérarchique

### 3.1 Page Custom HierarchyExplorer
**Durée estimée : 7-8 jours**

```php
// app/Filament/Pages/HierarchyExplorer.php
class HierarchyExplorer extends Page implements HasLivewireTable
{
    protected static ?string $navigationIcon = 'heroicon-o-folder-tree';
    protected static string $view = 'filament.pages.hierarchy-explorer';
    protected static ?string $navigationGroup = 'Explorateur';
    
    public $selectedFond = null;
    public $selectedCorpus = null;
    public $selectedCollection = null;
    
    public function selectFond($fondId)
    {
        $this->selectedFond = Fond::find($fondId);
        $this->selectedCorpus = null;
        $this->selectedCollection = null;
    }
    
    public function selectCorpus($corpusId)
    {
        $this->selectedCorpus = Corpus::find($corpusId);
        $this->selectedCollection = null;
    }
    
    // Actions rapides
    public function createCorpus()
    {
        $this->mountAction('createCorpus');
    }
    
    protected function getActions(): array
    {
        return [
            Action::make('createCorpus')
                ->form([
                    TextInput::make('code')->required(),
                    TextInput::make('title'),
                ])
                ->action(fn (array $data) => 
                    $this->selectedFond->corpuses()->create($data)
                )
        ];
    }
}
```


#### **Vue Blade 4 Colonnes**
```blade
{{-- resources/views/filament/pages/hierarchy-explorer.blade.php --}}
<x-filament-panels::page>
    <div class="grid grid-cols-4 gap-4 h-[700px]">
        
        {{-- Colonne Fonds --}}
        <x-filament::card class="overflow-hidden">
            <div class="p-4 border-b">
                <h3 class="font-semibold flex items-center">
                    🏛️ Fonds
                    <x-filament::button size="xs" class="ml-auto" wire:click="createFond">
                        <x-heroicon-o-plus class="w-4 h-4" />
                    </x-filament::button>
                </h3>
            </div>
            <div class="overflow-y-auto max-h-full p-2">
                @foreach(App\Models\Fond::all() as $fond)
                    <div wire:click="selectFond({{ $fond->id }})" 
                         class="p-3 cursor-pointer hover:bg-gray-100 rounded-md 
                                {{ $selectedFond?->id === $fond->id ? 'bg-primary-100' : '' }}">
                        <div class="font-medium">{{ $fond->code }}</div>
                        <div class="text-sm text-gray-600">{{ $fond->title }}</div>
                        <div class="text-xs text-gray-400">
                            {{ $fond->corpuses->count() }} corpus
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::card>

        {{-- Colonne Corpus --}}
        <x-filament::card class="overflow-hidden">
            {{-- Contenu similaire pour Corpus --}}
        </x-filament::card>

        {{-- Colonnes Collections et Items --}}
        {{-- ... --}}
        
    </div>
</x-filament-panels::page>
```


**Livrables :**
- ✅ HierarchyExplorer.php (Page Livewire)
- ✅ Vue Blade 4 colonnes responsive
- ✅ Navigation interactive avec sélection
- ✅ Actions contextuelles par niveau
- ✅ Affichage des items polymorphiques

---

## 📋 PHASE 4 : Système d'Upload Avancé

### 4.1 Pages Upload avec Contexte
**Durée estimée : 4-5 jours**

```php
// app/Filament/Pages/UploadItems.php
class UploadItems extends Page
{
    protected static ?string $navigationGroup = 'Médias & Items';
    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    
    public $uploadMode = 'single'; // 'single' ou 'batch'
    public $selectedParentType = null;
    public $selectedParentId = null;
    
    protected function getFormSchema(): array
    {
        return [
            Radio::make('uploadMode')
                ->options([
                    'single' => 'Upload individuel',
                    'batch' => 'Upload en lot',
                ])
                ->reactive(),
                
            Select::make('selectedParentType')
                ->label('Associer à')
                ->options([
                    Fond::class => 'Fonds',
                    Corpus::class => 'Corpus',
                    Collection::class => 'Collection',
                    Item::class => 'Item',
                ])
                ->reactive(),
                
            Select::make('selectedParentId')
                ->options(fn ($get) => $this->getParentOptions($get('selectedParentType'))),
                
            FileUpload::make('files')
                ->multiple($get('uploadMode') === 'batch')
                ->acceptedFileTypes(['audio/*', 'video/*', 'image/*', 'application/pdf']),
                
            // Upload CSV pour le mode batch
            FileUpload::make('csv_mapping')
                ->visible(fn ($get) => $get('uploadMode') === 'batch')
                ->acceptedFileTypes(['text/csv'])
        ];
    }
}
```


**Livrables :**
- ✅ Page Upload avec modes single/batch
- ✅ Sélection contexte parent via hiérarchie
- ✅ Validation fichiers + génération métadonnées
- ✅ Interface CSV pour upload lot

---

## 📋 PHASE 5 : Recherche & Filtrage Avancé

### 5.1 Page Recherche Globale
**Durée estimée : 3-4 jours**

```php
// app/Filament/Pages/AdvancedSearch.php
class AdvancedSearch extends Page implements HasLivewireTable
{
    use InteractsWithTable;
    
    protected static ?string $navigationGroup = 'Médias & Items';
    
    public function table(Table $table): Table
    {
        return $table
            ->query(Item::query())
            ->columns([
                TextColumn::make('code')->searchable(),
                TextColumn::make('itemable_type'),
                TextColumn::make('itemable.code'),
                TextColumn::make('file_type'),
                TextColumn::make('upload_date'),
                TextColumn::make('uploader.name'),
            ])
            ->filters([
                DateRangeFilter::make('upload_date'),
                SelectFilter::make('file_type'),
                SelectFilter::make('uploaded_by'),
                TextFilter::make('code'),
            ])
            ->actions([
                Action::make('viewInHierarchy')
                    ->icon('heroicon-o-folder-tree')
                    ->url(fn ($record) => route('filament.admin.pages.hierarchy-explorer', [
                        'focus' => $record->itemable_type,
                        'id' => $record->itemable_id,
                    ])),
            ]);
    }
}
```


**Livrables :**
- ✅ Table recherche avec filtres multiples
- ✅ Liens retour vers Explorateur
- ✅ Export des résultats
- ✅ Sauvegarde des recherches

---

## 📋 PHASE 6 : Gestion des Permissions

### 6.1 Système de Rôles
**Durée estimée : 2-3 jours**

```php
// app/Enums/UserRole.php
enum UserRole: string
{
    case CHERCHEUR = 'chercheur';
    case DOCUMENTALISTE = 'documentaliste';
    case ADMINISTRATEUR = 'administrateur';
}

// Dans User.php
public function hasRole(UserRole $role): bool
{
    return $this->role === $role;
}

public function canManageItems(): bool
{
    return in_array($this->role, [UserRole::DOCUMENTALISTE, UserRole::ADMINISTRATEUR]);
}
```


#### **Policies pour Resources**
```php
// app/Policies/ItemPolicy.php
class ItemPolicy
{
    public function create(User $user): bool
    {
        return $user->canManageItems();
    }
    
    public function delete(User $user, Item $item): bool
    {
        return $user->hasRole(UserRole::ADMINISTRATEUR) || 
               ($user->hasRole(UserRole::CHERCHEUR) && $item->uploaded_by === $user->id);
    }
}
```


**Livrables :**
- ✅ Enum UserRole + méthodes User
- ✅ Policies pour tous les modèles
- ✅ Middleware permissions dans Resources
- ✅ Interface gestion utilisateurs

---

## 📋 PHASE 7 : Optimisations & Finitions

### 7.1 Performance & UX
**Durée estimée : 3-4 jours**

```php
// Optimisations requêtes
class HierarchyExplorer extends Page
{
    protected function getFonds()
    {
        return Fond::withCount(['corpuses', 'items'])
            ->with(['items' => fn ($q) => $q->limit(5)])
            ->get();
    }
    
    // Cache des structures fréquentes
    public function getCorpusesProperty()
    {
        return Cache::remember(
            "fond.{$this->selectedFond->id}.corpuses",
            60,
            fn () => $this->selectedFond->corpuses()->withCount('items')->get()
        );
    }
}
```


**Livrables :**
- ✅ Lazy loading dans Explorateur
- ✅ Cache structures hiérarchiques
- ✅ Pagination optimisée
- ✅ Interface responsive mobile
- ✅ Feedback visuel (loading states)

---

## 📋 PHASE 8 : Logging & Administration

### 8.1 Système de Logs
**Durée estimée : 2 jours**

```php
// app/Models/ActivityLog.php
class ActivityLog extends Model
{
    public static function logAction(string $action, Model $model, User $user = null)
    {
        static::create([
            'user_id' => $user?->id ?? auth()->id(),
            'action' => $action,
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'ip_address' => request()->ip(),
        ]);
    }
}
```


**Livrables :**
- ✅ Modèle ActivityLog
- ✅ Resource pour consultation logs
- ✅ Observers automatiques sur modèles
- ✅ Page statistiques & dashboard

---

## 🎯 Planning Global

| Phase | Durée | Contenu Principal |
|-------|--------|-------------------|
| **1** | 1-2 semaines | Resources de base + Structure |
| **2** | 1 semaine | ItemResource complexe |
| **3** | 1.5-2 semaines | Explorateur hiérarchique |
| **4** | 1 semaine | Système upload |
| **5** | 1 semaine | Recherche avancée |
| **6** | 0.5 semaine | Permissions |
| **7** | 1 semaine | Optimisations |
| **8** | 0.5 semaine | Logs & admin |

**TOTAL ESTIMÉ : 6-8 semaines**

---

## ⚡ Points d'Attention Critiques

### 🔥 Priorités Absolues
1. **Relations polymorphiques** dans ItemResource
2. **Navigation fluide** dans HierarchyExplorer
3. **Upload avec validation** métier
4. **Permissions** granulaires par rôle

### 🎯 Objectifs Qualité
- **Performance** : < 2s chargement Explorateur
- **UX** : Navigation intuitive sans formation
- **Robustesse** : Validation métier stricte
- **Évolutivité** : Architecture extensible

Cette feuille de route garantit un développement **structuré et progressif** avec des **livrables concrets** à chaque étape !
