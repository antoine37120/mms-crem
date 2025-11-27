## Analyse du nouveau modèle de données

### Relations actuelles (many-to-many) :
- **Collection ↔ Corpus** : `belongsToMany` (une collection peut appartenir à plusieurs corpus)
- **Corpus ↔ Fond** : `belongsToMany` (un corpus peut appartenir à plusieurs fonds)
- **Collection** : entité autonome avec ses propres items
- **Items** : peuvent être attachés à Fond, Corpus, ou Collection (polymorphique)

### Implication pour HierarchyExplorer :
Le modèle hiérarchique strict (Fond → Corpus → Collection) n'est plus valide. Les Collections sont maintenant des entités indépendantes qui peuvent être liées à plusieurs Corpus.

---

# Spécifications révisées pour HierarchyExplorer

Voici la nouvelle documentation complète :

#### 3.1 HierarchyExplorer

**Navigation** : `Explorateur > Vue Hiérarchique`  
**Objectif** : Navigation interactive avec deux modes d'exploration distincts et panneau d'informations

##### **Changement majeur de paradigme**

Suite à l'évolution du schéma de données vers des relations many-to-many :
- Une **Collection** peut appartenir à **plusieurs Corpus**
- Un **Corpus** peut appartenir à **plusieurs Fonds**
- Les **Collections** sont des entités autonomes avec leurs propres items

L'explorateur propose désormais **deux modes d'exploration** pour refléter cette nouvelle réalité.

---

##### **Contraintes de Design** (inchangées)
- **Interface épurée** : Éviter l'aspect caricatural d'un explorateur de fichiers
- **Usage minimal d'icônes** : Privilégier le texte et la typographie pour la navigation
- **3 colonnes égales** : Répartition équilibrée en tiers (33% chacune)
- **Distinction items meta** : Séparation visuelle des items avec propriété `is_sub`
- **Actions centralisées** : Regroupement des actions par niveau de sélection
- **Navigation unifiée** : Utilisation de `heroicon-o-chevron-up-down` pour les dépliants

---

##### **Sélecteur de Mode d'Exploration**

Un sélecteur en haut de la **Colonne 1** permet de basculer entre les deux modes :
```

┌─────────────────────────────────────────┐
│  Mode: [● Collections] [○ Fonds]        │
│  Recherche: [________________________]  │
└─────────────────────────────────────────┘
```
- **Mode Collections** (par défaut) : Exploration centrée sur les Collections
- **Mode Fonds** : Exploration de la hiérarchie Fonds → Corpus → Collections

---

## MODE 1 : Exploration par Collections (Mode par défaut)

##### **Principe**
Navigation directe dans les Collections comme entités autonomes, avec affichage de leurs items.

##### **Interface 3 Colonnes**
```

┌─ COLONNE 1 (33%) ───────────┬─ COLONNE 2 (33%) ───────────┬─ COLONNE 3 (33%) ──────────┐
│                             │                             │                            │
│ Mode: [● Collections] [○]   │                             │                            │
│ Recherche: [____________]   │                             │                            │
│                             │                             │                            │
│ ⌄ CNRSMH_E_2009 ◄           │ Item: CNRSMH_I_2009_001 ◄   │ ┌─ SÉLECTION COLONNE 1 ──┐ │
│   > CNRSMH_I_2009_001       │                             │ │ 📁 Collection           │ │
│   ⌄ CNRSMH_I_2009_002       │ ┌─ Items Secondaires ──────┐│ │ CNRSMH_E_2009           │ │
│       TRA_en.pdf            │ │ > TRA_en.pdf             ││ │ 15 items • 3.2 GB       │ │
│       TRS_fr.txt            │ │ > TRS_fr.txt             ││ │ Corpus liés: 2          │ │
│   > CNRSMH_I_2009_003       │ │ > notes.doc              ││ │ [Voir] [Éditer]         │ │
│ > CNRSMH_E_2010             │ └──────────────────────────┘│ │ [+ Item]                │ │
│ > CNRSMH_E_2011             │                             │ └────────────────────────-│ │
│                             │                             │                            │
│                             │                             │ ┌─ SÉLECTION COLONNE 2 ──┐ │
│                             │                             │ │ 📄 Item Secondaire      │ │
│                             │                             │ │ TRA_en.pdf              │ │
│                             │                             │ │ PDF • 1.2 MB            │ │
│                             │                             │ │ [Voir] [Télécharger]    │ │
│                             │                             │ └────────────────────────-│ │
└─────────────────────────────┴─────────────────────────────┴────────────────────────────┘
```

#### **Colonne 1 : Arbre Collections → Items Principaux**

###### **Structure Hiérarchique**
```

Collection (niveau 0)
├─ Item Principal A (niveau 1) - via relation mainItems()
│  └─ (enfants visibles en Colonne 2)
├─ Item Principal B (niveau 1) - via relation mainItems()
└─ Item Principal C (niveau 1)
```
###### **Affichage**
- **Niveau 0** : Liste des Collections triées par code
- **Niveau 1** : Items principaux (`mainItems`) de chaque Collection dépliée
- Navigation arborescente avec expansion/collapse
- Chaque Collection affiche son code et nombre d'items principaux
- Chaque Item affiche son code (ou file_name si pas de code)

###### **États de Navigation**
- `⌄` : Collection ou Item déplié (avec `heroicon-o-chevron-up-down`)
- `>` : Collection ou Item replié (avec `heroicon-o-chevron-up-down`)
- `•` : Élément sans enfants
- `◄` : Élément sélectionné

###### **Recherche**
- Filtrage en temps réel sur Collections (code, titre) ET Items (code, file_name, title)
- Expansion automatique des Collections contenant des résultats
- Recherche inclusive

###### **Sélection**
- **Clic sur une Collection** → sélection avec marqueur `◄`
    - Met à jour la Colonne 2 avec les `secondaryItems` de la Collection
    - Met à jour la Section 1 de la Colonne 3 avec les infos de la Collection
- **Clic sur un Item Principal** → sélection avec marqueur `◄`
    - Met à jour la Colonne 2 avec les `secondaryItems` (items enfants) de cet Item
    - Met à jour la Section 1 de la Colonne 3 avec les infos de l'Item

###### **Informations affichées par niveau**
| Niveau | Éléments affichés |
|--------|-------------------|
| Collection | Code, (titre), nombre mainItems_count |
| Item Principal | Code ou file_name, indicateur si a des enfants |

##### **Colonne 2 : Items de la Collection sélectionnée**

Fonctionnement identique au mode précédent :
- **Section "Meta Items"** : Items avec `is_sub = true`
- **Section "Items Standards"** : Items avec `is_sub = false` ou `null`
- Navigation récursive pour les items enfants

##### **Colonne 3 : Informations Contextuelles**

###### **Section 1 : Informations Collection**
- Code et titre de la Collection
- **Corpus associés** : Liste des corpus liés (avec liens)
- **Fonds associés** : Liste des fonds (via les corpus)
- Statistiques : nombre d'items, taille totale
- Actions : Voir, Éditer, Ajouter Item

###### **Section 2 : Informations Item**
- Identique au fonctionnement actuel

---

## MODE 2 : Exploration par Fonds

##### **Principe**
Navigation hiérarchique traditionnelle : Fonds → Corpus → Collections, avec prise en compte des relations many-to-many.

##### **Interface 3 Colonnes**
```

┌─ COLONNE 1 (33%) ───────────┬─ COLONNE 2 (33%) ───────────┬─ COLONNE 3 (33%) ──────────┐
│                             │                             │                            │
│ Mode: [○ Collections] [●]   │                             │                            │
│ Recherche: [____________]   │                             │                            │
│                             │                             │                            │
│ ⌄ CNRSMH                    │ Collection: Mariages ◄      │ ┌─ SÉLECTION COLONNE 1 ──┐ │
│   ⌄ CNRSMH_E                │                             │ │ 📁 Collection Mariages  │ │
│     > CNRSMH_E_2009         │ ┌─ Meta Items ─────────────┐│ │ 15 items • 3.2 GB       │ │
│     > CNRSMH_E_2010 ◄       │ │ ⌄ CNRSMH_I_2011_001.wav  ││ │ Aussi dans:             │ │
│   > CNRSMH_I                │ │     TRA_en.pdf           ││ │  - Corpus ABC           │ │
│ > CREM_Archives             │ │     TRS_fr.txt           ││ │ [Voir] [Éditer]         │ │
│                             │ │                          ││ └────────────────────────-│ │
│                             │ │ > CNRSMH_I_2011_002.wav  ││                            │
│                             │ └──────────────────────────┘│ ┌─ SÉLECTION COLONNE 2 ──┐ │
│                             │                             │ │ 🎵 Item Principal       │ │
│                             │ ┌─ Items Standards ────────┐│ │ WAV 48kHz • 45.2MB      │ │
│                             │ │ > CNRSMH_I_001_003.mp4   ││ │ Durée: 12:30            │ │
│                             │ └──────────────────────────┘│ │ [Voir] [Télécharger]    │ │
└─────────────────────────────┴─────────────────────────────┴────────────────────────────┘
```
##### **Colonne 1 : Arbre Fonds → Corpus → Collections**

###### **Structure Hiérarchique**
```

Fond (niveau 0)
├─ Corpus A (niveau 1) - via relation many-to-many
│  ├─ Collection X (niveau 2) - via relation many-to-many
│  └─ Collection Y (niveau 2)
└─ Corpus B (niveau 1)
└─ Collection Z (niveau 2)
```
###### **Particularités many-to-many**
- Un même Corpus peut apparaître sous plusieurs Fonds
- Une même Collection peut apparaître sous plusieurs Corpus
- **Indication visuelle** : Les éléments présents dans plusieurs parents affichent un indicateur (ex: `*` ou badge)

###### **États de Navigation**
- `⌄` : Élément déplié (avec `heroicon-o-chevron-up-down`)
- `>` : Élément replié (avec `heroicon-o-chevron-up-down`)
- `•` : Élément sans enfants
- `◄` : Élément sélectionné

###### **Sélection**
- **Sélection Fond** : Affiche les items directs du Fond dans la Colonne 2
- **Sélection Corpus** : Affiche les items directs du Corpus dans la Colonne 2
- **Sélection Collection** : Affiche les items de la Collection dans la Colonne 2

##### **Colonne 2 : Items Secondaires de l'élément sélectionné**

###### **Principe**
Affiche les **items secondaires** (`secondaryItems`) de l'élément sélectionné dans la Colonne 1, qu'il s'agisse d'une Collection ou d'un Item Principal.

###### **Comportement selon le type sélectionné**

| Type sélectionné en Col. 1 | Items affichés en Col. 2 |
|----------------------------|--------------------------|
| Collection | `secondaryItems()` de la Collection (`is_sub = true`) |
| Item Principal | Items enfants de l'Item (via `childItems` ou relation parent) |

###### **Organisation**
- Liste plate des items secondaires
- Pas de sections Meta/Standard (tous sont secondaires par définition)
- Affichage du code, file_name, type de fichier
- Indicateur visuel si l'item secondaire a lui-même des enfants

###### **Navigation Récursive**
- Si un item secondaire possède des enfants, il peut être déplié
- Sélection d'un item secondaire → met à jour la Section 2 de la Colonne 3

###### **États de Navigation**
- `⌄` : Item déplié avec enfants
- `>` : Item replié avec enfants
- `•` : Item sans enfants
- `◄` : Item sélectionné
##### **Colonne 3 : Informations Contextuelles**

###### **Section 1 : Informations de l'élément sélectionné en Colonne 1**

**Pour une Collection :**
- Code et titre de la Collection
- **Corpus associés** : Liste des corpus liés (avec liens)
- Statistiques :
    - Nombre d'items principaux (`mainItems_count`)
    - Nombre d'items secondaires (`secondaryItems_count`)
    - Taille totale
- Actions : Voir, Éditer, Ajouter Item Principal

**Pour un Item Principal :**
- Code et titre/file_name de l'Item
- Type de fichier, taille, durée (si applicable)
- **Collection parente** : Lien vers la Collection
- Statistiques : Nombre d'items secondaires (enfants)
- Actions : Voir, Éditer, Télécharger, Ajouter Item Secondaire

###### **Section 2 : Informations de l'item sélectionné en Colonne 2**
- Détails de l'item secondaire sélectionné
- Métadonnées techniques : format, taille, durée
- Parent hiérarchique (Item Principal ou Collection)
- Actions : Voir, Éditer, Télécharger

---

##### **Gestion des relations many-to-many**

###### **Affichage des entités partagées**
- Une Collection appartenant à plusieurs Corpus affiche un badge "Multi-corpus"
- Un Corpus appartenant à plusieurs Fonds affiche un badge "Multi-fonds"
- Dans le panneau d'info, liste complète des parents avec liens

###### **Navigation contextuelle**
- L'élément sélectionné dans l'arbre indique le "chemin d'accès" actuel
- Le panneau d'informations montre tous les parents possibles
- Possibilité de naviguer vers un autre parent via les liens

---

##### **Paramètres URL et Deep Linking**

###### **Paramètres supportés**
- `?mode=collections` ou `?mode=fonds` : Mode d'exploration
- `?focus=collection&id=123` : Sélectionner une Collection
- `?focus=corpus&id=456` : Sélectionner un Corpus (mode Fonds uniquement)
- `?focus=fond&id=789` : Sélectionner un Fond (mode Fonds uniquement)
- `?focus=item&id=123` : Naviguer vers l'item et son parent

###### **Comportement**
- Mode Collections + focus=collection : Sélectionne directement la collection
- Mode Fonds + focus=collection : Déploie l'arbre jusqu'à la collection (via le premier corpus/fond)
- Si le focus ne correspond pas au mode, basculer automatiquement

---

##### **Interactions Entre Colonnes**

###### **Mode Collections**

| Action en Colonne 1 | Effet Colonne 2 | Effet Section 1 Col. 3 | Effet Section 2 Col. 3 |
|---------------------|-----------------|------------------------|------------------------|
| Sélection Collection | `secondaryItems` de la Collection | Info Collection | (vide) |
| Sélection Item Principal | Items enfants de l'Item | Info Item Principal | (vide) |
| Sélection Item Secondaire en Col. 2 | - | - | Info Item Secondaire |

###### **Flux de navigation typique**
1. L'utilisateur voit la liste des Collections (repliées)
2. Il déplie une Collection → voit ses Items Principaux
3. Il clique sur un Item Principal → la Colonne 2 affiche ses items secondaires
4. Il clique sur un item secondaire → la Section 2 affiche ses détails

###### **Mode Fonds**
| Action | Effet Colonne 2 | Effet Colonne 3 |
|--------|-----------------|-----------------|
| Sélection Fond | Items directs du Fond | Info Fond |
| Sélection Corpus | Items directs du Corpus | Info Corpus + Fonds parents |
| Sélection Collection | Items de la Collection | Info Collection + Corpus parents |
| Sélection Item | - | Info Item |

---

##### **Recherche Unifiée**

###### **Mode Collections**
- Recherche dans les Collections uniquement (code, titre)
- Filtrage direct de la liste

###### **Mode Fonds**
- Recherche dans Fonds, Corpus et Collections
- Filtrage de l'arbre avec expansion automatique des branches contenant des résultats
- Les parents sont affichés même s'ils ne correspondent pas au terme (pour maintenir la hiérarchie)

---

##### **Terminologie et relations - Mode Collections**

###### **Définitions**
| Terme | Relation | Critère |
|-------|----------|---------|
| Items Principaux | `mainItems()` | `is_sub = false` |
| Items Secondaires | `secondaryItems()` | `is_sub = true` |
| Items Enfants | `childItems()` | Items dont le parent est un autre Item |

###### **Hiérarchie visuelle**
```

Collection
├── Item Principal 1 (is_sub = false, itemable = Collection)
│   ├── Item Secondaire A (is_sub = true, itemable = Item Principal 1)
│   └── Item Secondaire B (is_sub = true, itemable = Item Principal 1)
├── Item Principal 2 (is_sub = false, itemable = Collection)
└── Item Secondaire direct (is_sub = true, itemable = Collection) ← visible en Col. 2 quand Collection sélectionnée
```
###### **Note importante**
- La Colonne 1 affiche **Collections + leurs mainItems**
- La Colonne 2 affiche **les secondaryItems** de l'élément sélectionné en Colonne 1
- Un Item Principal peut avoir ses propres items enfants (affichés en Colonne 2 quand il est sélectionné)

---

##### **Avantages de cette Architecture**

1. **Flexibilité** : Deux modes adaptés aux différents cas d'usage
2. **Cohérence** : Reflète fidèlement le nouveau modèle de données
3. **Simplicité** : Mode Collections pour un accès direct et rapide
4. **Contexte** : Mode Fonds pour comprendre la hiérarchie organisationnelle
5. **Transparence** : Visibilité des relations many-to-many
6. **Navigation fluide** : Liens vers tous les parents possibles

---

## Résumé des changements clés

| Aspect | Ancienne version | Nouvelle version |
|--------|------------------|------------------|
| Modèle hiérarchique | Strict (Fond → Corpus → Collection) | Flexible (many-to-many) |
| Point d'entrée | Uniquement par Fonds | Collections (défaut) ou Fonds |
| Collections | Liées à un seul Corpus | Autonomes, liées à N Corpus |
| Corpus | Liés à un seul Fond | Liés à N Fonds |
| Colonne 1 | Arbre unique | Liste ou Arbre selon le mode |
| Info panneau | Parents uniques | Liste des parents multiples |

---

Ces modifications reflètent le nouveau comportement où :
1. **Colonne 1** = Arbre navigable : Collections → Items Principaux (mainItems)
2. **Colonne 2** = Items Secondaires (secondaryItems) de l'élément sélectionné en Colonne 1
3. **Colonne 3** = Informations contextuelles des sélections


Voici la section à ajouter à la fin de la documentation :

---

## Section : Fichiers à modifier pour l'implémentation

```markdown
---

##### **Fichiers à modifier pour l'implémentation**

Cette section liste tous les fichiers concernés par les modifications de HierarchyExplorer, organisés par priorité et nature des changements.

---

###### **Fichiers principaux (modifications majeures)**

| Fichier | Chemin | Description des modifications |
|---------|--------|-------------------------------|
| **HierarchyExplorer.php** | `app/Filament/Pages/HierarchyExplorer.php` | Refonte complète : ajout du sélecteur de mode, nouvelles propriétés computed pour les deux modes, gestion des états d'expansion par mode, méthodes pour `mainItems`/`secondaryItems` |
| **hierarchy-explorer.blade.php** | `resources/views/filament/pages/hierarchy-explorer.blade.php` | Refonte du template : sélecteur de mode, structure conditionnelle selon le mode, arbre Collections→MainItems pour Mode 1, arbre Fonds→Corpus→Collections pour Mode 2 |

---

###### **Fichiers de support (modifications mineures)**

| Fichier | Chemin | Description des modifications |
|---------|--------|-------------------------------|
| **HierarchyController.php** | `app/Http/Controllers/HierarchyController.php` | Ajouter des endpoints pour `mainItems` et `secondaryItems`, adapter les méthodes existantes aux relations many-to-many |
| **HasHierarchicalItems.php** | `app/Traits/HasHierarchicalItems.php` | Vérifier que les relations `mainItems()` et `secondaryItems()` sont correctement définies (déjà présentes) |

---

###### **Fichiers de routes (si utilisation d'API)**

| Fichier | Chemin | Description des modifications |
|---------|--------|-------------------------------|
| **web.php** ou **api.php** | `routes/web.php` ou `routes/api.php` | Ajouter les routes pour les nouveaux endpoints du HierarchyController si nécessaire |

---

###### **Modèles (vérification, pas de modification majeure attendue)**

| Fichier | Chemin | Vérification |
|---------|--------|--------------|
| **Collection.php** | `app/Models/Collection.php` | Vérifier `mainItems()`, `secondaryItems()` via trait, relation `corpuses()` many-to-many |
| **Corpus.php** | `app/Models/Corpus.php` | Vérifier relation `fonds()` many-to-many, `collections()` many-to-many |
| **Fond.php** | `app/Models/Fond.php` | Vérifier relation `corpuses()` many-to-many |
| **Item.php** | `app/Models/Item.php` | Vérifier `childItems()`, `isSecondary()`, relations polymorphiques |

---

###### **Documentation**

| Fichier | Chemin | Description |
|---------|--------|-------------|
| **HierarchyExplorer.md** | `HierarchyExplorer.md` | Mise à jour complète selon ces nouvelles spécifications |

---

###### **Ordre de modification recommandé**

1. **Phase 1 - Backend**
   - `app/Filament/Pages/HierarchyExplorer.php` - Logique métier et propriétés computed
   - `app/Http/Controllers/HierarchyController.php` - Endpoints API (si utilisés)

2. **Phase 2 - Frontend**
   - `resources/views/filament/pages/hierarchy-explorer.blade.php` - Interface utilisateur

3. **Phase 3 - Tests et ajustements**
   - Vérifier les modèles et relations
   - Tester les deux modes de navigation
   - Valider les paramètres URL et deep linking

---

###### **Dépendances existantes à conserver**

Les fichiers suivants utilisent HierarchyExplorer et doivent rester compatibles :

| Fichier | Chemin | Utilisation |
|---------|--------|-------------|
| **CollectionResource.php** | `app/Filament/Resources/Collections/CollectionResource.php` | `getGlobalSearchResultUrl()` vers HierarchyExplorer |
| **CorpusResource.php** | `app/Filament/Resources/Corpuses/CorpusResource.php` | `getGlobalSearchResultUrl()` vers HierarchyExplorer |
| **FondResource.php** | `app/Filament/Resources/Fonds/FondResource.php` | `getGlobalSearchResultUrl()` vers HierarchyExplorer |
| **ViewCollection.php** | `app/Filament/Resources/Collections/Pages/ViewCollection.php` | Action "Voir Hiérarchie" |
| **ViewCorpus.php** | `app/Filament/Resources/Corpuses/Pages/ViewCorpus.php` | Action "Voir Hiérarchie" |
| **ViewFond.php** | `app/Filament/Resources/Fonds/Pages/ViewFond.php` | Action "Voir Hiérarchie" |

---

###### **Arborescence récapitulative**
```

mms-crem/
├── app/
│   ├── Filament/
│   │   └── Pages/
│   │       └── HierarchyExplorer.php          ← MODIFICATION MAJEURE
│   ├── Http/
│   │   └── Controllers/
│   │       └── HierarchyController.php        ← MODIFICATION MINEURE
│   ├── Models/
│   │   ├── Collection.php                     ← VÉRIFICATION
│   │   ├── Corpus.php                         ← VÉRIFICATION
│   │   ├── Fond.php                           ← VÉRIFICATION
│   │   └── Item.php                           ← VÉRIFICATION
│   └── Traits/
│       └── HasHierarchicalItems.php           ← VÉRIFICATION
├── resources/
│   └── views/
│       └── filament/
│           └── pages/
│               └── hierarchy-explorer.blade.php ← MODIFICATION MAJEURE
├── routes/
│   └── web.php                                ← MODIFICATION MINEURE (si API)
└── HierarchyExplorer.md                       ← MISE À JOUR DOCUMENTATION
```

```


---

Cette section permet d'avoir une vue claire de tous les fichiers impactés et de planifier le travail de développement.
