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
 * 🧪 Unit tests — the URL check no longer branches on the PHP version (#308)
 * ============================================================================
 *
 * validateDestination() used to pick between two ways of giving get_headers()
 * its options, depending on the PHP version. The old way could never run on a
 * supported PHP (8.4 or newer), so it was removed. This stops it coming back.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      #308
 * ============================================================================
 */

declare(strict_types=1);

test('redirect resolver: no PHP version branch and no default stream context change', function (): void
{
    $source = file_get_contents(dirname(__DIR__, 2) . '/web/G2My.Link/_functions/redirect_resolver.php');

    assert_true($source !== false, 'The file could be read');
    assert_false(str_contains((string) $source, 'stream_context_set_default'), 'stream_context_set_default is back');
    assert_false(str_contains((string) $source, 'version_compare'), 'A version_compare() branch is back');
    assert_true(str_contains((string) $source, '@get_headers($pinnedUrl, true, $context)'), 'get_headers() should get the context directly');
});
