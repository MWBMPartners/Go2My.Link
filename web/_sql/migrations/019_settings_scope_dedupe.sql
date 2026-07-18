-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Migration 019: dedupe System-scope settings (#150)
-- =============================================================================
-- ROOT CAUSE. tblSettings enforced uniqueness with
--     UNIQUE KEY UQ_setting_scope (settingID, settingScope, settingScopeRef)
-- and settingScopeRef IS NULL for every System- and Default-scope row. MySQL
-- treats NULLs as DISTINCT inside a UNIQUE index, so that key never fired for
-- NULL-scope rows: nothing at the database level stopped two (or more)
-- (settingID, 'System', NULL) rows from coexisting. Duplicates crept in from
--   (a) seed re-imports whose "ON DUPLICATE KEY UPDATE" could never match a
--       System row (NULL scopeRef never collides), so each re-run INSERTed a
--       fresh row, and
--   (b) the SELECT-then-INSERT race in setSetting() (web/_functions/settings.php).
-- getSetting() then reads whichever duplicate the query happens to return
-- first, risking a stale/ambiguous value. (Surfaced by a flaking
-- tests/integration/linkspage_agegate_test.php against a persisted DB.)
--
-- FIX. A STORED generated column settingScopeRefKey = COALESCE(settingScopeRef,
-- '') rewrites NULL to '' so System/Default rows collide, and UQ_setting_scope
-- is rebuilt on (settingID, settingScope, settingScopeRefKey). The base
-- settingScopeRef column is left UNTOUCHED, so every "settingScopeRef IS NULL"
-- read path keeps its exact semantics — only the uniqueness comparison changes.
--
-- ⚠️  FRESH INSTALLS DO NOT NEED THIS FILE: the same generated column and the
--     rebuilt UNIQUE key are already part of the base schema
--     (web/_sql/schema/010_core_settings.sql). Run this migration ONLY to bring
--     an ALREADY-IMPORTED earlier schema (3-column UQ_setting_scope on the raw
--     settingScopeRef) up to date.
--
-- ⚠️  ORDER MATTERS — COLLAPSE THEN CONSTRAIN. Step 1 collapses any existing
--     duplicate groups to a single authoritative row BEFORE Step 3 adds the
--     unique key. Adding the key first would fail with errno 1062 on any DB
--     that already accumulated duplicates. Step 2 (add the generated column)
--     sits between them; adding a column never fails on duplicate data.
--
-- FORWARD-ONLY, ONE-SHOT (mirrors migration 010). MySQL does NOT support
-- "ADD COLUMN IF NOT EXISTS", and this migration does not fabricate a guard the
-- other data migrations do not use: re-running it on a schema that already has
-- settingScopeRefKey errors on Step 2, which is the intended guard against
-- double application. Run it exactly once, as part of the deploy/migration path.
--
-- @package    Go2My.Link
-- @subpackage Migrations
-- @version    1.0.0
-- @since      launch-prep/2026-07-09 (#150)
-- =============================================================================

USE `mwtools_Go2MyLink`;

-- -----------------------------------------------------------------------------
-- Step 1 — Collapse existing duplicates BEFORE the unique key is added.
-- -----------------------------------------------------------------------------
-- For each (settingID, settingScope, COALESCE(settingScopeRef, '')) group —
-- the exact grouping the rebuilt UQ_setting_scope will enforce — keep ONE
-- authoritative row and delete the rest. The authoritative row is the most-
-- recently-updated one: ORDER BY COALESCE(updatedAt, createdAt) DESC (updatedAt
-- is nullable and falls back to the NOT NULL createdAt), tie-broken by the
-- highest settingUID (the AUTO_INCREMENT primary key, which is unique — so the
-- ranking is fully deterministic and exactly one row per group wins rn = 1).
--
-- Expressed as a delete joined against a keep-set of the winning settingUIDs:
-- the keep-set is wrapped in a derived table, which MySQL materialises, so this
-- self-referencing multi-table DELETE is allowed (no errno 1093). Groups with a
-- single row keep that row (rn = 1) and are never touched.
DELETE `s`
FROM `tblSettings` `s`
LEFT JOIN (
    SELECT `settingUID`
    FROM (
        SELECT
            `settingUID`,
            ROW_NUMBER() OVER (
                PARTITION BY `settingID`, `settingScope`, COALESCE(`settingScopeRef`, '')
                ORDER BY COALESCE(`updatedAt`, `createdAt`) DESC, `settingUID` DESC
            ) AS `rn`
        FROM `tblSettings`
    ) `ranked`
    WHERE `ranked`.`rn` = 1
) `keep` ON `keep`.`settingUID` = `s`.`settingUID`
WHERE `keep`.`settingUID` IS NULL;

-- -----------------------------------------------------------------------------
-- Step 2 — Add the NULL-collapsing STORED generated column.
-- -----------------------------------------------------------------------------
-- COALESCE(settingScopeRef, '') materialised so NULL-scope rows dedupe. It
-- inherits the table's utf8mb4_unicode_ci collation, matching settingScopeRef,
-- so the '' comparison in the unique key is exact.
ALTER TABLE `tblSettings`
    ADD COLUMN `settingScopeRefKey` VARCHAR(100)
        GENERATED ALWAYS AS (COALESCE(`settingScopeRef`, '')) STORED
        COMMENT 'NULL-collapsed mirror of settingScopeRef (NULL becomes empty string) so System/Default rows dedupe in UQ_setting_scope (#150)'
        AFTER `settingScopeRef`;

-- -----------------------------------------------------------------------------
-- Step 3 — Swap the unique key onto the generated column.
-- -----------------------------------------------------------------------------
-- Drop the old NULL-permitting key and rebuild it on settingScopeRefKey. Both
-- actions run in a single ALTER so the table is never left without a
-- uniqueness guard. UQ_setting_scope is not referenced by any foreign key, so
-- the drop cannot fail with errno 1553. This ADD fails with errno 1062 if any
-- duplicate group survived Step 1 — which, given Step 1, it must not.
ALTER TABLE `tblSettings`
    DROP INDEX `UQ_setting_scope`,
    ADD UNIQUE KEY `UQ_setting_scope` (`settingID`, `settingScope`, `settingScopeRefKey`);

-- =============================================================================
-- Verification (informational — safe to run, changes nothing).
-- =============================================================================
-- After this migration every (settingID, settingScope, settingScopeRefKey)
-- group holds exactly one row; this SELECT must return zero rows.
SELECT `settingID`, `settingScope`, `settingScopeRefKey`, COUNT(*) AS `dupes`
FROM   `tblSettings`
GROUP BY `settingID`, `settingScope`, `settingScopeRefKey`
HAVING COUNT(*) > 1;
