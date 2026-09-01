-- ============================================================================
-- Ménage des données créées AVANT une date limite (mariadb, pur SQL)
-- À exécuter sur la base mms_crem (jamais sur mms_crem_test).
--
-- Principe : on supprime TOUT ce qui a été créé avant @date_limite
-- (imports comme saisies manuelles). Ce qui a été créé après est conservé.
--
-- Cascades automatiques (FK) lors de la suppression de items :
--   media_variations, item_processing_states, item_views
-- Cascades lors de la suppression de fonds/corpuses/collections :
--   corpus_fond, collection_corpus
-- Tables sans FK à traiter explicitement : audits (polymorphe), scanned_files.
--
-- Sécurité : exécuter d'abord la section A (aperçu), puis la section B dans
-- une transaction, vérifier, et ne faire COMMIT qu'après contrôle.
-- ============================================================================

-- >>> ADAPTER CETTE DATE <<<
SET @date_limite = '2025-10-22 00:00:00';

-- ---------------------------------------------------------------------------
-- A. APERÇU (aucune modification) : ce qui sera supprimé
-- ---------------------------------------------------------------------------
SELECT 'fonds'                          AS table_, COUNT(*) AS avant_date FROM fonds     WHERE created_at < @date_limite
UNION ALL SELECT 'corpuses',                    COUNT(*) FROM corpuses    WHERE created_at < @date_limite
UNION ALL SELECT 'collections',                 COUNT(*) FROM collections WHERE created_at < @date_limite
UNION ALL SELECT 'items',                       COUNT(*) FROM items       WHERE created_at < @date_limite
UNION ALL SELECT 'media_variations (via items)',COUNT(*) FROM media_variations v JOIN items i ON i.id = v.item_id WHERE i.created_at < @date_limite
UNION ALL SELECT 'item_views (via items)',      COUNT(*) FROM item_views      v JOIN items i ON i.id = v.item_id WHERE i.created_at < @date_limite
UNION ALL SELECT 'scanned_files',               COUNT(*) FROM scanned_files   WHERE created_at < @date_limite
UNION ALL SELECT 'audits (entités import)',     COUNT(*) FROM audits          WHERE created_at < @date_limite
  AND auditable_type IN ('App\\Models\\Item','App\\Models\\Collection','App\\Models\\Corpus','App\\Models\\Fond');

-- ---------------------------------------------------------------------------
-- B. SUPPRESSION (ordre respectant les clés étrangères)
-- ---------------------------------------------------------------------------
START TRANSACTION;

-- 1. Audits des entités import (polymorphe, pas de FK)
DELETE FROM audits
WHERE created_at < @date_limite
  AND auditable_type IN ('App\\Models\\Item','App\\Models\\Collection','App\\Models\\Corpus','App\\Models\\Fond');

-- 2. scanned_files : item_id passe à NULL à la suppression des items,
--    on supprime donc d'abord les lignes anciennes (cache de scan obsolète)
DELETE FROM scanned_files WHERE created_at < @date_limite;

-- 3. items : casse en cascade media_variations, item_processing_states, item_views
DELETE FROM items WHERE created_at < @date_limite;

-- 4. Entités de hiérarchie : les pivots corpus_fond / collection_corpus
--    sont nettoyés automatiquement par les FK ON DELETE CASCADE
DELETE FROM collections WHERE created_at < @date_limite;
DELETE FROM corpuses    WHERE created_at < @date_limite;
DELETE FROM fonds       WHERE created_at < @date_limite;

-- >>> Vérifier ici que les compteurs correspondent à l'aperçu (section A),
-- >>> puis choisir :
-- ROLLBACK;   -- annuler tout
COMMIT;       -- valider tout
