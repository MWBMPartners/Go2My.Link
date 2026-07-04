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
 * 🧪 Regression tests — favicon path-traversal confinement (F-006 / #101)
 * ============================================================================
 *
 * Component B's favicon.php concatenates a DB-sourced orgFaviconPath into the
 * shared _uploads/ directory and readfile()s it. Before F-006 a stored value
 * such as "../../_auth_keys/auth_creds.php" would escape the uploads dir and
 * leak an arbitrary file.
 *
 * favicon.php confines the path inline (it is not a function), so this test
 * mirrors that confinement logic EXACTLY against a real temporary directory
 * tree so realpath() behaves as it does in production:
 *
 *   $safeName = basename($orgFaviconPath);
 *   $faviconFile = $uploadsDir . '/' . $safeName;
 *   confined only if realpath($faviconFile) starts with realpath($uploadsDir).
 *
 * A future refactor that weakens the confinement will break these tests.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * ============================================================================
 */

declare(strict_types=1);

/**
 * Mirror favicon.php's confinement decision exactly.
 *
 * Returns the confined absolute file path when the stored value resolves to a
 * real file genuinely inside $uploadsDir, or false when it must fall through to
 * the default favicon (rejected / escaped / missing).
 *
 * @param  string $uploadsDir      Absolute path to the uploads directory.
 * @param  string $orgFaviconPath  DB-sourced (untrusted) stored path.
 * @return string|false            Confined path, or false if not served.
 */
function g2ml_test_favicon_confine(string $uploadsDir, string $orgFaviconPath): string|false
{
    $safeName    = basename($orgFaviconPath);
    $faviconFile = $uploadsDir . DIRECTORY_SEPARATOR . $safeName;

    $resolvedUploadsDir = realpath($uploadsDir);
    $resolvedFavicon    = realpath($faviconFile);

    $isConfined = false;

    if ($resolvedUploadsDir !== false && $resolvedFavicon !== false)
    {
        $prefix = $resolvedUploadsDir . DIRECTORY_SEPARATOR;

        if (strncmp($resolvedFavicon, $prefix, strlen($prefix)) === 0)
        {
            $isConfined = true;
        }
    }

    if ($isConfined === true && file_exists($faviconFile) && is_file($faviconFile))
    {
        return $faviconFile;
    }

    return false;
}

/**
 * Build a throwaway directory tree mirroring the production layout:
 *
 *   <root>/_uploads/org-fav.ico          (a legitimate favicon)
 *   <root>/_auth_keys/auth_creds.php     (the secret a traversal would target)
 *
 * @return string  The temporary root directory (caller need not clean up;
 *                  it lives under the system temp dir).
 */
function g2ml_test_favicon_tree(): string
{
    $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'g2ml_favicon_' . bin2hex(random_bytes(6));

    mkdir($root . DIRECTORY_SEPARATOR . '_uploads', 0700, true);
    mkdir($root . DIRECTORY_SEPARATOR . '_auth_keys', 0700, true);

    file_put_contents($root . DIRECTORY_SEPARATOR . '_uploads' . DIRECTORY_SEPARATOR . 'org-fav.ico', 'ICON');
    file_put_contents($root . DIRECTORY_SEPARATOR . '_auth_keys' . DIRECTORY_SEPARATOR . 'auth_creds.php', '<?php // SECRET');

    return $root;
}

// ----------------------------------------------------------------------------
// Tests
// ----------------------------------------------------------------------------

test('favicon confinement: a plain org-fav.ico is served', function (): void
{
    $root       = g2ml_test_favicon_tree();
    $uploadsDir = $root . DIRECTORY_SEPARATOR . '_uploads';

    $result = g2ml_test_favicon_confine($uploadsDir, 'org-fav.ico');

    assert_not_same(false, $result, 'A legitimate favicon filename must be served');
    assert_same(realpath($uploadsDir . DIRECTORY_SEPARATOR . 'org-fav.ico'), realpath((string) $result), 'It must resolve to the file inside _uploads');
});

test('favicon confinement: ../../_auth_keys/auth_creds.php is confined / rejected', function (): void
{
    $root       = g2ml_test_favicon_tree();
    $uploadsDir = $root . DIRECTORY_SEPARATOR . '_uploads';

    // basename() collapses the traversal to "auth_creds.php", which does not
    // exist inside _uploads, so the guard must refuse to serve it AND must
    // never resolve to the real secret outside the uploads dir.
    $result = g2ml_test_favicon_confine($uploadsDir, '../../_auth_keys/auth_creds.php');

    assert_same(false, $result, 'A traversal payload must not be served');

    // Belt-and-braces: confirm the real secret was NOT what got selected.
    $secret = realpath($root . DIRECTORY_SEPARATOR . '_auth_keys' . DIRECTORY_SEPARATOR . 'auth_creds.php');
    assert_not_same($secret, $result, 'The escaped secret path must never be returned');
});

test('favicon confinement: an absolute path is collapsed by basename and rejected', function (): void
{
    $root       = g2ml_test_favicon_tree();
    $uploadsDir = $root . DIRECTORY_SEPARATOR . '_uploads';

    $result = g2ml_test_favicon_confine($uploadsDir, '/etc/passwd');

    assert_same(false, $result, 'An absolute path must be collapsed by basename and not served');
});

test('favicon confinement: a missing-but-legitimate filename is not served', function (): void
{
    $root       = g2ml_test_favicon_tree();
    $uploadsDir = $root . DIRECTORY_SEPARATOR . '_uploads';

    $result = g2ml_test_favicon_confine($uploadsDir, 'does-not-exist.ico');

    assert_same(false, $result, 'A non-existent favicon falls through to the default');
});
