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
 * 🧪 Regression tests — the one shared destination check (#205)
 * ============================================================================
 *
 * Before #205 the dashboard's edit-link page ran only ONE of the three
 * checks createShortURL() ran (sanitise the URL; refuse our own short
 * domains; refuse internal/metadata hosts), and the API's PUT handler ran
 * two of the three but skipped the own-domain check. So an existing link
 * could be EDITED to point somewhere its own CREATION would have refused.
 * g2ml_validateLinkDestination() (web/Go2My.Link/_functions/
 * shorturl_create.php) now holds all three checks in one place; this file
 * pins its decisions down so a future change to any one path cannot drift
 * from the others.
 *
 * This runner is DB-free (see tests/run.php), so every test sets
 * $GLOBALS['g2ml_link_destination_custom_domains_override'] before calling
 * the function under test — that global is the function's own documented
 * test seam for supplying "our own registered custom short domains"
 * without a database, and it must be set (even to an empty array) or the
 * function falls through to a live tblOrgShortDomains query that dbSelect()
 * (undefined here) cannot answer.
 *
 * The 'ok' cases look up real DNS names (example.com, en.wikipedia.org)
 * through g2ml_destinationHostIsAllowed() — this mirrors the existing integration
 * suite, which already creates links to https://example.com/... throughout
 * (tests/integration/custom_alias_create_test.php and others), so this test
 * run needs the same DNS/network reachability those already assume.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      #205
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/../web/_functions/security.php';
require_once dirname(__DIR__, 1) . '/../web/Go2My.Link/_functions/shorturl_create.php';

/**
 * Run g2ml_validateLinkDestination() with the custom-domain test seam set to
 * a known list, so every test in this file is explicit about what "our own
 * domains" means for that call and never depends on a global left over from
 * a previous test.
 *
 * @param  string             $rawURL
 * @param  array<int, string> $customDomains
 * @return array{ok: bool, url: ?string, errorCode: string, error: string}
 */
function _g2mlLinkDestinationTestCheck(string $rawURL, array $customDomains = []): array
{
    $GLOBALS['g2ml_link_destination_custom_domains_override'] = $customDomains;

    return g2ml_validateLinkDestination($rawURL);
}

// ----------------------------------------------------------------------------
// Accepted destination.
// ----------------------------------------------------------------------------

test('g2ml_validateLinkDestination: an ordinary public https URL is accepted', function (): void
{
    $result = _g2mlLinkDestinationTestCheck('https://example.com/a');

    assert_true($result['ok'], 'A plain public destination must be accepted');
    assert_same('https://example.com/a', $result['url'], 'The sanitised URL is returned unchanged');
    assert_same('', $result['errorCode'], 'errorCode is empty on success');
});

// ----------------------------------------------------------------------------
// invalid_url.
// ----------------------------------------------------------------------------

test('g2ml_validateLinkDestination: javascript: scheme is invalid_url', function (): void
{
    $result = _g2mlLinkDestinationTestCheck('javascript:alert(1)');

    assert_false($result['ok'], 'A javascript: URL must be refused');
    assert_same(null, $result['url'], 'No URL is returned on failure');
    assert_same('invalid_url', $result['errorCode'], 'errorCode identifies the sanitise-URL failure');
});

// ----------------------------------------------------------------------------
// own_domain — built-in domains, exact match and subdomain widening.
// ----------------------------------------------------------------------------

test('g2ml_validateLinkDestination: our own g2my.link is own_domain', function (): void
{
    $result = _g2mlLinkDestinationTestCheck('https://g2my.link/x');

    assert_false($result['ok'], 'A link back to g2my.link must be refused');
    assert_same('own_domain', $result['errorCode'], 'errorCode identifies the own-domain failure');
});

test('g2ml_validateLinkDestination: www.lnks.page is own_domain (www. is stripped before comparison)', function (): void
{
    $result = _g2mlLinkDestinationTestCheck('https://www.lnks.page/');

    assert_false($result['ok'], 'The www. prefix must not bypass the own-domain check');
    assert_same('own_domain', $result['errorCode'], 'errorCode identifies the own-domain failure');
});

test('g2ml_validateLinkDestination: admin.go2my.link is own_domain (#205 subdomain widening)', function (): void
{
    $result = _g2mlLinkDestinationTestCheck('https://admin.go2my.link/');

    assert_false($result['ok'], 'A subdomain of a built-in domain must be refused');
    assert_same('own_domain', $result['errorCode'], 'errorCode identifies the own-domain failure');
});

// ----------------------------------------------------------------------------
// own_domain — a trailing dot on the host must not bypass the check (#205).
// "g2my.link." resolves to the same address as "g2my.link", but as a plain
// string comparison it equals neither 'g2my.link' nor '.g2my.link', so the
// trailing dot is stripped before the own-domain comparison runs.
// ----------------------------------------------------------------------------

test('g2ml_validateLinkDestination: g2my.link. (trailing dot) is own_domain', function (): void
{
    $result = _g2mlLinkDestinationTestCheck('https://g2my.link./x');

    assert_false($result['ok'], 'A trailing dot on the host must not bypass the own-domain check');
    assert_same('own_domain', $result['errorCode'], 'errorCode identifies the own-domain failure');
});

test('g2ml_validateLinkDestination: admin.go2my.link. (trailing dot) is own_domain', function (): void
{
    $result = _g2mlLinkDestinationTestCheck('https://admin.go2my.link./');

    assert_false($result['ok'], 'A trailing dot must not bypass the subdomain-widening check either');
    assert_same('own_domain', $result['errorCode'], 'errorCode identifies the own-domain failure');
});

// ----------------------------------------------------------------------------
// own_domain — a registered custom short domain, via the test seam.
// ----------------------------------------------------------------------------

test('g2ml_validateLinkDestination: a custom short domain from the override is own_domain', function (): void
{
    $result = _g2mlLinkDestinationTestCheck('https://short.example-org.test/x', ['short.example-org.test']);

    assert_false($result['ok'], 'A registered custom short domain must be refused, exactly like a built-in one');
    assert_same('own_domain', $result['errorCode'], 'errorCode identifies the own-domain failure');
});

test('g2ml_validateLinkDestination: a SUBDOMAIN of a custom short domain is NOT own_domain', function (): void
{
    // Deliberate scope limit (see g2ml_validateLinkDestination()'s own doc
    // block): the subdomain widening applies only to the three built-in
    // domains. A custom short domain belongs to a customer, so only the
    // exact host they registered is ours to refuse — a subdomain of it must
    // reach check 3 (the SSRF guard) rather than being refused as our own.
    // en.wikipedia.org is used because it is a real, stable subdomain that
    // resolves to a public address, so a pass here proves the function did
    // NOT treat it as own_domain (own_domain would return 'url' => null
    // without ever reaching the SSRF guard).
    $result = _g2mlLinkDestinationTestCheck('https://en.wikipedia.org/x', ['wikipedia.org']);

    assert_true($result['ok'], 'A subdomain of a CUSTOM short domain is a different host and is not refused as own_domain');
});

// ----------------------------------------------------------------------------
// blocked_destination — the anti-SSRF guard (#100).
// ----------------------------------------------------------------------------

test('g2ml_validateLinkDestination: loopback 127.0.0.1 is blocked_destination', function (): void
{
    $result = _g2mlLinkDestinationTestCheck('http://127.0.0.1/');

    assert_false($result['ok'], 'A loopback destination must be refused');
    assert_same('blocked_destination', $result['errorCode'], 'errorCode identifies the SSRF-guard failure');
});

test('g2ml_validateLinkDestination: cloud-metadata 169.254.169.254 is blocked_destination', function (): void
{
    $result = _g2mlLinkDestinationTestCheck('http://169.254.169.254/latest');

    assert_false($result['ok'], 'The cloud-metadata endpoint must be refused');
    assert_same('blocked_destination', $result['errorCode'], 'errorCode identifies the SSRF-guard failure');
});

test('g2ml_validateLinkDestination: userinfo (user:pass@) is blocked_destination', function (): void
{
    $result = _g2mlLinkDestinationTestCheck('http://user:pass@example.com/');

    assert_false($result['ok'], 'A URL carrying userinfo must be refused');
    assert_same('blocked_destination', $result['errorCode'], 'errorCode identifies the SSRF-guard failure');
});
