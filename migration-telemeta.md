# Migration des données Telemeta — procédure complète

Cette procédure décrit le remplacement complet des données de la base `mms_crem`
par une réimportation depuis les tables Telemeta (`media_*`), avec ménage préalable
des données existantes créées avant une date limite.

> La commande centrale est `php artisan import:telemeta`
> (voir `app/Console/Commands/ImportTelemetaCommand.php`).
> Elle lit les tables Telemeta via la connexion dédiée `telemeta`
> (base `mms_telemeta` par défaut, voir `DB_TELEMETA_DATABASE` dans `.env`).

---

## 0. Vue d'ensemble

| Étape | Outil | Base concernée |
|---|---|---|
| 1. Préparer la base Telemeta | Manuel (dump/restore, phpMyAdmin…) | `mms_telemeta` |
| 2. Sauvegarde de sécurité | `mysqldump` | `mms_crem` |
| 3. Ménage des données anciennes | `database/sql/cleanup-before-date.sql` | `mms_crem` |
| 4. Simulation | `php artisan import:telemeta --dry-run` | lecture seule |
| 5. Import | `php artisan import:telemeta` | `mms_crem` |
| 6. Post-traitement | commandes artisan | `mms_crem` |

⚠️ **Ne jamais lancer `php artisan migrate:fresh` sur la base `mms_crem`** :
cela dropperait toutes les tables, y compris les tables applicatives ET
les tables Telemeta si elles s'y trouvent encore.

---

## 1. Prérequis

### 1.1 Base Telemeta séparée

Les tables Telemeta doivent se trouver dans la base définie par
`DB_TELEMETA_DATABASE` (par défaut `mms_telemeta`) — pas dans `mms_crem`.

Les 10 tables à y placer :

```
media_fonds            media_corpus           media_collections      media_items
media_fonds_children   media_corpus_children
media_fonds_related    media_corpus_related   media_collection_related
media_item_related
```

Si elles sont encore dans `mms_crem` et que la destination est sur le **même
serveur MariaDB**, le déplacement est instantané :

```sql
RENAME TABLE mms_crem.media_fonds TO mms_telemeta.media_fonds;
-- (à répéter pour les 10 tables, ou via dump/restore selon votre méthode)
```

Vérification :

```bash
mysql -u root -e "SHOW TABLES FROM mms_telemeta"
```

> Tant que les tables ne sont pas présentes dans la base Telemeta,
> `import:telemeta` échoue avec « table doesn't exist » : comportement
> normal et sans risque.

### 1.2 Connexion et configuration

- `config/database.php` : connexion `telemeta` (hérite de `DB_HOST`, `DB_PORT`,
  `DB_USERNAME`, `DB_PASSWORD` ; surcharge possible via `DB_TELEMETA_HOST`,
  `DB_TELEMETA_PORT`, `DB_TELEMETA_USERNAME`, `DB_TELEMETA_PASSWORD`).
- En production (`prod.env`) : l'utilisateur MySQL de l'application doit avoir
  les droits `SELECT` sur la base Telemeta.

### 1.3 Utilisateur créateur

Toutes les données importées sont rattachées à l'utilisateur `--user-id`
(défaut : `1`). Vérifier que cet utilisateur existe :

```sql
SELECT id, name FROM users WHERE id = 1;
```

---

## 2. Sauvegarde de sécurité

Avant toute modification, faire un dump complet :

```bash
mysqldump -u root -p mms_crem > sauvegarde_avant_menage.sql
```

---

## 3. Ménage des données existantes

Le ménage est fait **en pur SQL**, sans Laravel, avec le script
**`database/sql/cleanup-before-date.sql`**. Principe : supprimer tout ce qui a
été **créé avant une date limite** (imports comme saisies manuelles) et
conserver tout ce qui a été créé après.

### 3.1 Adapter la date limite

Ouvrir le script et modifier la première ligne :

```sql
SET @date_limite = '2025-01-01 00:00:00';   -- ← votre date
```

### 3.2 Aperçu avant destruction (section A)

Exécuter d'abord la section A seule (aucune modification) : copier la requête
`SELECT … UNION ALL …` du script dans un client MySQL, ou décommenter
temporairement le reste. Le tableau affiche, table par table, le nombre de
lignes qui seraient supprimées (`fonds`, `corpuses`, `collections`, `items`,
`media_variations`, `item_views`, `scanned_files`, `audits`).

### 3.3 Exécution de la suppression (section B)

Le script supprime dans l'ordre des clés étrangères, **dans une transaction** :

1. `audits` (polymorphe, pas de FK) — entités import uniquement
2. `scanned_files` (sinon orphelines après suppression des items)
3. `items` — casse en cascade `media_variations`, `item_processing_states`, `item_views`
4. `collections`, `corpuses`, `fonds` — les pivots `corpus_fond` et
   `collection_corpus` sont nettoyés automatiquement (`ON DELETE CASCADE`)

Le script se termine par `COMMIT;` : vérifier les compteurs de la section A
**avant** de le laisser s'exécuter, ou remplacer `COMMIT;` par `ROLLBACK;`
pour une répétition générale sans conséquence.

**Ce qui n'est jamais touché** : `users`, `pending_files`, `media_clients`,
`documentation_pages`, `vantage_*`, sessions/cache, et bien sûr les tables
Telemeta `media_*` (dans `mms_telemeta`).

---

## 4. Import des données Telemeta

### 4.1 Simulation (lecture seule)

```bash
php artisan import:telemeta --dry-run
```

Le résumé indique, pour chaque étape, ce qui serait créé vs déjà présent.
Exemple de référence (chiffres de la dernière simulation, ils dépendent de
votre ménage) : 13 534 items créés, 58 000 ignorés, 0 erreur.

Options utiles :

| Option | Effet |
|---|---|
| `--dry-run` | Simulation, aucune écriture |
| `--only=fonds,corpus,collections,items,related` | Exécuter une ou plusieurs étapes seules |
| `--limit=N` | Limiter les lignes lues par table source (tests) |
| `--user-id=1` | Utilisateur créateur/depôsant |

### 4.2 Import réel

```bash
php artisan import:telemeta
```

Étapes exécutées dans l'ordre :

1. **fonds** ← `media_fonds` (skip par `code`)
2. **corpus** ← `media_corpus` + pivots `corpus_fond` ← `media_fonds_children`
3. **collections** ← `media_collections` + pivots `collection_corpus` ← `media_corpus_children`
4. **items** ← `media_items` (attachés aux collections par code) + sous-items
   depuis `media_item_related`
5. **related** ← `media_fonds_related`, `media_corpus_related`,
   `media_collection_related` (items secondaires attachés au parent)

Comportements clés :

- **Aucune suppression** : la commande est 100 % idempotente, un second
  passage ne crée rien.
- Les items **sans code ni fichier** sont ignorés (décision métier).
- Les items avec code mais **sans fichier** sont importés comme fiches
  métadonnées (`file_path` null).
- Conflit `(code, extension)` : skip (jamais de doublon).
- Insertions SQL brutes : le fichier physique n'est jamais déplacé,
  ni observer ni audit déclenché.
- En cas d'interruption : relancer simplement la commande, elle reprend
  là où elle s'était arrêtée.

---

## 5. Post-traitement

```bash
# MD5 des fichiers présents sur le disque
php artisan items:calculate-md5

# Génération diffusion (HLS) + waveform : dispatch des jobs
php artisan items:process-pending-media

# Worker dédié (à laisser tourner jusqu'à ce que la file soit vide)
php artisan queue:work --queue=media_processing
```

> `app:update-media-variation-sizes` n'est **pas** nécessaire : les jobs de
> génération écrivent déjà `file_size` ; cette commande ne sert qu'à
> rattraper d'anciennes variations sans taille.

---

## 6. Vérifications finales

```sql
-- Volumes importés
SELECT 'fonds', COUNT(*) FROM fonds
UNION ALL SELECT 'corpuses', COUNT(*) FROM corpuses
UNION ALL SELECT 'collections', COUNT(*) FROM collections
UNION ALL SELECT 'items', COUNT(*) FROM items
UNION ALL SELECT 'corpus_fond', COUNT(*) FROM corpus_fond
UNION ALL SELECT 'collection_corpus', COUNT(*) FROM collection_corpus;

-- Cohérence avec Telemeta
SELECT 'media_fonds', COUNT(*) FROM mms_telemeta.media_fonds
UNION ALL SELECT 'media_corpus', COUNT(*) FROM mms_telemeta.media_corpus
UNION ALL SELECT 'media_collections', COUNT(*) FROM mms_telemeta.media_collections
UNION ALL SELECT 'media_items', COUNT(*) FROM mms_telemeta.media_items;
```

- `import:telemeta` doit être ré-exécutable : un second passage affiche
  uniquement des « déjà présents ».
- Vérifier quelques items dans l'admin (code, `code_prefix`/`code_suffix`,
  `public_access`, fichier).

---

## 7. Sécurité des tests

- `phpunit.xml` impose **`DB_CONNECTION=mariadb`** + **`DB_DATABASE=mms_crem_test`**
  et **`DB_TELEMETA_DATABASE=mms_crem_test`** : aucun test ne peut toucher
  `mms_crem` ni `mms_telemeta`.
- Le test de la commande : `tests/Feature/ImportTelemetaCommandTest.php`
  (6 tests : import complet, fiches vides ignorées, idempotence, dry-run,
  `--only` valide/invalide).
- Sur cet environnement Windows, `php artisan test` peut se bloquer (TTY) ;
  lancer Pest directement :

```bash
php vendor/pestphp/pest/bin/pest tests/Feature/ImportTelemetaCommandTest.php
```

---

## 8. Dépannage

| Symptôme | Cause / solution |
|---|---|
| « table doesn't exist » au lancement | Les tables `media_*` ne sont pas (encore) dans la base `DB_TELEMETA_DATABASE` |
| Items « collection introuvable » au résumé | L'étape collections n'a pas tourné avant items → rejouer avec `--only=collections` puis `--only=items` |
| Fichiers physiques manquants en post-traitement | Les chemins `media_items.filename` doivent correspondre aux fichiers réellement présents sur le disque médias |
| Import interrompu | Relancer `php artisan import:telemeta` : idempotent |
| Besoin de rejouer une seule étape | `php artisan import:telemeta --only=items` (etc.) |
