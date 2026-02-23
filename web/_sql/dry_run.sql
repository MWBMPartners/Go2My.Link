-- ============================================================================
-- 🧪 Go2My.Link — Migration Dry-Run Verification Script
-- ============================================================================
--
-- READ-ONLY verification script. Does NOT modify any data.
-- Run this before migration to verify:
--   1. Legacy database is accessible and has expected data
--   2. New database schema is ready for migration
--   3. Seed data has been loaded correctly
--
-- Usage:
--   mysql -u username -p < dry_run.sql
--
-- Dependencies: Both databases must be accessible:
--   - mwtools_mwlink (legacy)
--   - mwtools_Go2MyLink (new — schema + seeds loaded)
--
-- @version    0.7.0
-- @since      Phase 6
-- ============================================================================

-- ============================================================================
-- 📊 Section 1: Legacy Database Verification (mwtools_mwlink)
-- ============================================================================

SELECT '=== LEGACY DATABASE VERIFICATION ===' AS section;

-- 1.1 Verify legacy database exists and is accessible
SELECT 'Legacy DB accessible' AS check_name,
       SCHEMA_NAME AS result
FROM information_schema.SCHEMATA
WHERE SCHEMA_NAME = 'mwtools_mwlink';

-- 1.2 Verify legacy short URLs (expect ~480)
SELECT 'Legacy short URLs' AS check_name,
       COUNT(*) AS total,
       CASE WHEN COUNT(*) >= 480 THEN 'PASS' ELSE 'CHECK' END AS status
FROM mwtools_mwlink.tblShortURLs;

-- 1.3 Verify legacy users (expect ~7)
SELECT 'Legacy users' AS check_name,
       COUNT(*) AS total,
       CASE WHEN COUNT(*) >= 7 THEN 'PASS' ELSE 'CHECK' END AS status
FROM mwtools_mwlink.tblCustomers;

-- 1.4 Verify legacy organisations (expect ~5)
SELECT 'Legacy organisations' AS check_name,
       COUNT(*) AS total,
       CASE WHEN COUNT(*) >= 5 THEN 'PASS' ELSE 'CHECK' END AS status
FROM mwtools_mwlink.tblCustomerOrg;

-- 1.5 Verify legacy categories (expect ~4)
SELECT 'Legacy categories' AS check_name,
       COUNT(*) AS total,
       CASE WHEN COUNT(*) >= 4 THEN 'PASS' ELSE 'CHECK' END AS status
FROM mwtools_mwlink.tblCategories;

-- 1.6 Verify legacy activity log (expect ~429K, optional migration)
SELECT 'Legacy activity log' AS check_name,
       COUNT(*) AS total,
       CONCAT(ROUND(COUNT(*) / 10000), ' batches needed') AS migration_note
FROM mwtools_mwlink.tblActivityLog;

-- 1.7 Verify legacy settings
SELECT 'Legacy settings definitions' AS check_name,
       COUNT(*) AS total
FROM mwtools_mwlink.tblSettingsDictionary;

SELECT 'Legacy settings values' AS check_name,
       COUNT(*) AS total
FROM mwtools_mwlink.tblSettings;

-- ============================================================================
-- 📊 Section 2: New Database Verification (mwtools_Go2MyLink)
-- ============================================================================

SELECT '=== NEW DATABASE VERIFICATION ===' AS section;

-- 2.1 Verify new database exists
SELECT 'New DB accessible' AS check_name,
       SCHEMA_NAME AS result
FROM information_schema.SCHEMATA
WHERE SCHEMA_NAME = 'mwtools_Go2MyLink';

-- 2.2 Verify all expected tables exist (should be 30+)
SELECT 'New DB table count' AS check_name,
       COUNT(*) AS total,
       CASE WHEN COUNT(*) >= 30 THEN 'PASS' ELSE 'FAIL — schema incomplete' END AS status
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'mwtools_Go2MyLink'
  AND TABLE_TYPE = 'BASE TABLE';

-- 2.3 List all tables for manual verification
SELECT TABLE_NAME, ENGINE, TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'mwtools_Go2MyLink'
  AND TABLE_TYPE = 'BASE TABLE'
ORDER BY TABLE_NAME;

-- 2.4 Verify stored procedures (expect 3)
SELECT 'Stored procedures' AS check_name,
       COUNT(*) AS total,
       CASE WHEN COUNT(*) >= 3 THEN 'PASS' ELSE 'FAIL — procedures missing' END AS status
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = 'mwtools_Go2MyLink'
  AND ROUTINE_TYPE = 'PROCEDURE';

-- ============================================================================
-- 📊 Section 3: Seed Data Verification
-- ============================================================================

SELECT '=== SEED DATA VERIFICATION ===' AS section;

-- 3.1 Subscription tiers (expect 4: Free, Basic, Premium, Enterprise)
SELECT 'Subscription tiers' AS check_name,
       COUNT(*) AS total,
       CASE WHEN COUNT(*) = 4 THEN 'PASS' ELSE 'FAIL' END AS status
FROM mwtools_Go2MyLink.tblSubscriptionTiers;

-- 3.2 Default organisation (expect 1: [default])
SELECT 'Default organisation' AS check_name,
       COUNT(*) AS total,
       CASE WHEN COUNT(*) >= 1 THEN 'PASS' ELSE 'FAIL' END AS status
FROM mwtools_Go2MyLink.tblOrganisations
WHERE orgHandle = '[default]';

-- 3.3 Settings (expect 50+)
SELECT 'Settings count' AS check_name,
       COUNT(*) AS total,
       CASE WHEN COUNT(*) >= 50 THEN 'PASS' ELSE 'CHECK — may need more seeds' END AS status
FROM mwtools_Go2MyLink.tblSettings;

-- 3.4 Languages (expect 10)
SELECT 'Languages' AS check_name,
       COUNT(*) AS total,
       CASE WHEN COUNT(*) = 10 THEN 'PASS' ELSE 'FAIL' END AS status
FROM mwtools_Go2MyLink.tblLanguages;

-- 3.5 en-GB translations (expect ~1075)
SELECT 'en-GB translations' AS check_name,
       COUNT(*) AS total,
       CASE WHEN COUNT(*) >= 1000 THEN 'PASS' ELSE 'CHECK — translations may be incomplete' END AS status
FROM mwtools_Go2MyLink.tblTranslations
WHERE localeCode = 'en-GB';

-- 3.6 LinksPage templates (expect 5)
SELECT 'LinksPage templates' AS check_name,
       COUNT(*) AS total,
       CASE WHEN COUNT(*) = 5 THEN 'PASS' ELSE 'FAIL' END AS status
FROM mwtools_Go2MyLink.tblLinksPageTemplates;

-- ============================================================================
-- 📊 Section 4: Pre-Migration Readiness Summary
-- ============================================================================

SELECT '=== MIGRATION READINESS SUMMARY ===' AS section;

-- 4.1 Check that new tables are empty (no prior migration data)
SELECT 'tblShortURLs empty' AS check_name,
       COUNT(*) AS rows,
       CASE WHEN COUNT(*) = 0 THEN 'READY' ELSE 'WARNING — data already exists' END AS status
FROM mwtools_Go2MyLink.tblShortURLs;

SELECT 'tblUsers empty' AS check_name,
       COUNT(*) AS rows,
       CASE WHEN COUNT(*) = 0 THEN 'READY' ELSE 'WARNING — data already exists' END AS status
FROM mwtools_Go2MyLink.tblUsers;

-- 4.2 Verify InnoDB engine on critical tables
SELECT 'InnoDB check' AS check_name,
       COUNT(*) AS non_innodb_tables,
       CASE WHEN COUNT(*) = 0 THEN 'PASS' ELSE 'FAIL — non-InnoDB tables found' END AS status
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'mwtools_Go2MyLink'
  AND TABLE_TYPE = 'BASE TABLE'
  AND ENGINE != 'InnoDB';

-- 4.3 Verify character set
SELECT 'Character set check' AS check_name,
       DEFAULT_CHARACTER_SET_NAME AS charset,
       DEFAULT_COLLATION_NAME AS collation,
       CASE WHEN DEFAULT_CHARACTER_SET_NAME = 'utf8mb4' THEN 'PASS' ELSE 'FAIL' END AS status
FROM information_schema.SCHEMATA
WHERE SCHEMA_NAME = 'mwtools_Go2MyLink';

SELECT '=== DRY RUN COMPLETE ===' AS section;
