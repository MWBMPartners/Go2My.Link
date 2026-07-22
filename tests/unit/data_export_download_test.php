<?php
/**
 * Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
 * All rights reserved.
 *
 * This source code is proprietary and confidential.
 * Unauthorised copying, modification, or distribution is strictly prohibited.
 */

/**
 * ============================================================================
 * 🧪 Unit tests — g2ml_isExportRequestDownloadable() (#162)
 * ============================================================================
 *
 * DB-free coverage for the PURE decision half of the GDPR data-export
 * download fix (web/_functions/data_rights.php). The IMPURE half —
 * g2ml_findExportRequestForDownload() (the ownership-scoped SQL SELECT),
 * g2ml_streamExportDownload() (headers + file I/O), and
 * g2ml_handleDataExportDownloadRequest() (the full dispatcher) — all touch
 * either the database or superglobals/headers and are covered instead by
 * tests/integration/data_export_download_test.php.
 *
 * ----------------------------------------------------------------------------
 * Why a null row IS the cross-user (IDOR) regression case
 * ----------------------------------------------------------------------------
 * g2ml_findExportRequestForDownload() matches BOTH requestUID AND the
 * session's own userUID in ONE prepared statement's WHERE clause. A
 * requestUID that belongs to a DIFFERENT user therefore can never be
 * returned as a row — the query itself returns null, identically to a
 * requestUID that does not exist at all. Passing null into
 * g2ml_isExportRequestDownloadable() and asserting false is therefore the
 * exact, DB-free shape of "another user's requestUID must be rejected"; the
 * companion integration test additionally proves the real SQL query behaves
 * this way against a live schema.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      launch-prep/2026-07-09 (#162)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/data_rights.php';

// ============================================================================
// 🔒 IDOR guard — a row belonging to (or matched for) a different user
// ============================================================================

test('isExportRequestDownloadable (#162): a requestUID belonging to a DIFFERENT user (null row) is rejected', function (): void
{
    // g2ml_findExportRequestForDownload() returns null for BOTH "no such
    // requestUID" and "requestUID exists but is owned by someone else" — the
    // ownership check is baked into the WHERE clause, so both cases are
    // indistinguishable by design. null is exactly what that function
    // returns for a cross-user requestUID.
    assert_false(
        g2ml_isExportRequestDownloadable(null),
        'A null row (not found OR owned by a different user) must never be treated as downloadable'
    );
});

test('isExportRequestDownloadable (#162): a DB error (false) is rejected', function (): void
{
    assert_false(
        g2ml_isExportRequestDownloadable(false),
        'A DB-error sentinel (false) must never be treated as downloadable'
    );
});

// ============================================================================
// 🚦 Status guard — only 'completed' is downloadable
// ============================================================================

test('isExportRequestDownloadable (#162): a pending request is rejected', function (): void
{
    $row = [
        'requestUID'      => 1,
        'status'          => 'pending',
        'exportFilePath'  => '/exports/export_1.json',
        'exportExpiresAt' => date('Y-m-d H:i:s', time() + 3600),
    ];

    assert_false(g2ml_isExportRequestDownloadable($row), 'A pending export must not be downloadable');
});

test('isExportRequestDownloadable (#162): a processing request is rejected', function (): void
{
    $row = [
        'requestUID'      => 2,
        'status'          => 'processing',
        'exportFilePath'  => '/exports/export_2.json',
        'exportExpiresAt' => date('Y-m-d H:i:s', time() + 3600),
    ];

    assert_false(g2ml_isExportRequestDownloadable($row), 'A processing export must not be downloadable');
});

test('isExportRequestDownloadable (#162): a rejected request is rejected', function (): void
{
    $row = [
        'requestUID'      => 3,
        'status'          => 'rejected',
        'exportFilePath'  => '/exports/export_3.json',
        'exportExpiresAt' => date('Y-m-d H:i:s', time() + 3600),
    ];

    assert_false(g2ml_isExportRequestDownloadable($row), 'A rejected export must not be downloadable');
});

// ============================================================================
// ⏰ Expiry guard — 'completed' but expired (or missing expiry) is rejected
// ============================================================================

test('isExportRequestDownloadable (#162): a completed but EXPIRED request is rejected', function (): void
{
    $row = [
        'requestUID'      => 4,
        'status'          => 'completed',
        'exportFilePath'  => '/exports/export_4.json',
        'exportExpiresAt' => date('Y-m-d H:i:s', time() - 3600),
    ];

    assert_false(g2ml_isExportRequestDownloadable($row), 'An expired export must not be downloadable, even when completed');
});

test('isExportRequestDownloadable (#162): a completed request with a null exportExpiresAt is rejected', function (): void
{
    $row = [
        'requestUID'      => 5,
        'status'          => 'completed',
        'exportFilePath'  => '/exports/export_5.json',
        'exportExpiresAt' => null,
    ];

    assert_false(g2ml_isExportRequestDownloadable($row), 'A missing expiry must not be treated as "never expires"');
});

test('isExportRequestDownloadable (#162): a completed request with an empty exportFilePath is rejected', function (): void
{
    $row = [
        'requestUID'      => 6,
        'status'          => 'completed',
        'exportFilePath'  => '',
        'exportExpiresAt' => date('Y-m-d H:i:s', time() + 3600),
    ];

    assert_false(g2ml_isExportRequestDownloadable($row), 'An empty exportFilePath must not be treated as downloadable');
});

// ============================================================================
// ✅ The one genuinely downloadable shape
// ============================================================================

test('isExportRequestDownloadable (#162): a completed, unexpired request IS downloadable', function (): void
{
    $row = [
        'requestUID'      => 7,
        'status'          => 'completed',
        'exportFilePath'  => '/exports/export_7.json',
        'exportExpiresAt' => date('Y-m-d H:i:s', time() + 3600),
    ];

    assert_true(g2ml_isExportRequestDownloadable($row), 'A completed request with a future expiry must be downloadable');
});
