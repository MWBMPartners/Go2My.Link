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
 * 🧪 Unit tests — every offered API scope is actually enforced (#208)
 * ============================================================================
 *
 * Static, DB-free guard against the #208 bug: the "valid scopes" whitelist
 * (g2ml_apiValidScopesList()) and the places that actually enforce a scope
 * were free to drift apart, so a scope could be offered on the API-keys page
 * and accepted into a key's stored permissions while no route or handler
 * ever checked for it. domains:read and domains:write did exactly that
 * until #208 removed them.
 *
 * This reads two kinds of source file as plain text, rather than calling any
 * route or handler, and looks for the two ways a scope is enforced today:
 *   - a route table entry, "'scope' => '<scope>'", in the front controller
 *     (web/Go2My.Link/public_html/api/v1/index.php); or
 *   - a g2ml_apiKeyHasScope($keyRow, '<scope>') call inside a handler file
 *     (web/Go2My.Link/public_html/api/v1/handlers/*.php) — the only scope
 *     enforced this way today is qr:link, checked inside the POST urls
 *     handler (handlers/urls.php) to gate the optional QR-link fields on
 *     that route, on top of the urls:write scope the route table already
 *     requires.
 *
 * A text search cannot prove a scope check runs on every path that matters —
 * only that the pattern appears somewhere in the file. That is enough to
 * catch the #208 failure (an offered scope with NO matching text anywhere),
 * which is what this test exists to prevent.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.1.0 — #208
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/security.php';
require_once dirname(__DIR__, 2) . '/web/_functions/api_response.php';
require_once dirname(__DIR__, 2) . '/web/_functions/api_auth.php';

// ============================================================================
// 📄 Read the source files this test checks against, once, as plain text.
// ============================================================================

$g2mlScopesTestIndexPath     = dirname(__DIR__, 2) . '/web/Go2My.Link/public_html/api/v1/index.php';
$g2mlScopesTestIndexContents = file_get_contents($g2mlScopesTestIndexPath);

if ($g2mlScopesTestIndexContents === false)
{
    // A missing/unreadable front controller must fail loudly, not silently
    // pass every scope as "unused" against an empty haystack.
    throw new RuntimeException('Could not read ' . $g2mlScopesTestIndexPath);
}

$g2mlScopesTestHandlerPaths = glob(dirname(__DIR__, 2) . '/web/Go2My.Link/public_html/api/v1/handlers/*.php');

if ($g2mlScopesTestHandlerPaths === false)
{
    $g2mlScopesTestHandlerPaths = [];
}

$g2mlScopesTestHandlerContents = '';

foreach ($g2mlScopesTestHandlerPaths as $g2mlScopesTestHandlerPath)
{
    $g2mlScopesTestOneHandlerContents = file_get_contents($g2mlScopesTestHandlerPath);

    if ($g2mlScopesTestOneHandlerContents !== false)
    {
        $g2mlScopesTestHandlerContents = $g2mlScopesTestHandlerContents . "\n" . $g2mlScopesTestOneHandlerContents;
    }
}

// ============================================================================
// 🔍 Extract the scope strings each source actually checks for.
// ============================================================================
// Both patterns match a single-quoted scope literal, never a variable — a
// dynamically-built scope name would not be found here. Every
// g2ml_apiKeyHasScope() call in the handler files passes its scope as a
// literal string this way.

preg_match_all("/'scope'\\s*=>\\s*'([^']+)'/", $g2mlScopesTestIndexContents, $g2mlScopesTestRouteTableMatches);
$g2mlScopesTestRouteTableScopes = array_values(array_unique($g2mlScopesTestRouteTableMatches[1]));

preg_match_all("/g2ml_apiKeyHasScope\\([^,]+,\\s*'([^']+)'\\s*\\)/", $g2mlScopesTestHandlerContents, $g2mlScopesTestHandlerScopeMatches);
$g2mlScopesTestHandlerScopes = array_values(array_unique($g2mlScopesTestHandlerScopeMatches[1]));

$g2mlScopesTestEnforcedScopes = array_values(array_unique(array_merge($g2mlScopesTestRouteTableScopes, $g2mlScopesTestHandlerScopes)));

// ============================================================================
// ✅ Every scope on the whitelist must be enforced somewhere
// ============================================================================

test('apiScopesUsed: every scope in g2ml_apiValidScopesList() is enforced by a route or a handler', function () use ($g2mlScopesTestEnforcedScopes): void
{
    $unusedScopes = [];

    foreach (g2ml_apiValidScopesList() as $validScope)
    {
        if (!in_array($validScope, $g2mlScopesTestEnforcedScopes, true))
        {
            $unusedScopes[] = $validScope;
        }
    }

    assert_same(
        [],
        $unusedScopes,
        'Offered but unenforced scopes (the #208 failure mode): ' . implode(', ', $unusedScopes)
    );
});

// ============================================================================
// 🔁 The reverse: every route table scope must be on the whitelist
// ============================================================================

test('apiScopesUsed: every scope the route table checks for is in g2ml_apiValidScopesList()', function () use ($g2mlScopesTestRouteTableScopes): void
{
    $unlistedScopes = [];
    $validScopes    = g2ml_apiValidScopesList();

    foreach ($g2mlScopesTestRouteTableScopes as $routeScope)
    {
        if (!in_array($routeScope, $validScopes, true))
        {
            $unlistedScopes[] = $routeScope;
        }
    }

    assert_same(
        [],
        $unlistedScopes,
        'Route table requires a scope that is not on the whitelist: ' . implode(', ', $unlistedScopes)
    );
});
