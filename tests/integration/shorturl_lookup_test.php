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
 * 🧪 Integration characterization tests — sp_lookupShortURL (redirect hot path)
 * ============================================================================
 *
 * Exercises the core redirect resolution stored procedure against a fresh test
 * database that the operator imports beforehand (see tests/README.md). The
 * runner passes a connected mysqli handle into
 * g2ml_register_integration_tests().
 *
 * These tests seed the minimum data they need — the [default] organisation and
 * a handful of tblShortURLs rows — and then CALL sp_lookupShortURL, reading the
 * OUT parameters via @session variables. They characterise the CURRENT
 * resolver contract:
 *
 *   (a) a live row resolves to status 'success' with the stored destination;
 *   (b) a row whose endDate is in the past resolves to status 'expired';
 *   (c) an unknown short code resolves to status 'not_found'.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * ============================================================================
 */

declare(strict_types=1);

/**
 * Run a query and abort the suite loudly if the DB rejects it — seeding errors
 * are setup faults, not characterised behaviour, so they should surface
 * clearly rather than masquerade as assertion failures.
 *
 * @param  mysqli $db
 * @param  string $sql
 * @return void
 */
function g2ml_test_exec(mysqli $db, string $sql): void
{
    $result = mysqli_query($db, $sql);

    if ($result === false)
    {
        throw new RuntimeException('Setup query failed: ' . mysqli_error($db) . ' — SQL: ' . $sql);
    }
}

/**
 * Insert a short-URL row using a prepared statement (house rule: prepared
 * statements for every query touching variable data).
 *
 * @param  mysqli      $db
 * @param  string      $shortCode
 * @param  string|null $destinationURL
 * @param  string|null $startDate    DATETIME string or null.
 * @param  string|null $endDate      DATETIME string or null.
 * @param  int         $isActive
 * @param  string|null $utmSource    (#92) Configured UTM columns — null by default.
 * @param  string|null $utmMedium    (#92)
 * @param  string|null $utmCampaign  (#92)
 * @param  string|null $utmTerm      (#92)
 * @param  string|null $utmContent   (#92)
 * @return void
 */
function g2ml_test_insert_shorturl(mysqli $db, string $shortCode, ?string $destinationURL, ?string $startDate, ?string $endDate, int $isActive, ?string $utmSource = null, ?string $utmMedium = null, ?string $utmCampaign = null, ?string $utmTerm = null, ?string $utmContent = null): void
{
    $sql = 'INSERT INTO `tblShortURLs` '
        . '(`orgHandle`, `shortCode`, `destinationURL`, `destinationType`, `startDate`, `endDate`, `isActive`, '
        . '`utmSource`, `utmMedium`, `utmCampaign`, `utmTerm`, `utmContent`) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

    $statement = mysqli_prepare($db, $sql);

    if ($statement === false)
    {
        throw new RuntimeException('Prepare failed: ' . mysqli_error($db));
    }

    $orgHandle       = '[default]';
    $destinationType = 'url';

    mysqli_stmt_bind_param(
        $statement,
        'ssssssisssss',
        $orgHandle,
        $shortCode,
        $destinationURL,
        $destinationType,
        $startDate,
        $endDate,
        $isActive,
        $utmSource,
        $utmMedium,
        $utmCampaign,
        $utmTerm,
        $utmContent
    );

    $executed = mysqli_stmt_execute($statement);

    if ($executed === false)
    {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Insert failed: ' . $error);
    }

    mysqli_stmt_close($statement);
}

/**
 * CALL sp_lookupShortURL and return its OUT parameters, including the five
 * UTM projection columns added by #92.
 *
 * @param  mysqli $db
 * @param  string $domain
 * @param  string $shortCode
 * @return array{destination: ?string, status: ?string, orgHandle: ?string,
 *               utmSource: ?string, utmMedium: ?string, utmCampaign: ?string,
 *               utmTerm: ?string, utmContent: ?string}
 */
function g2ml_test_lookup(mysqli $db, string $domain, string $shortCode): array
{
    $statement = mysqli_prepare(
        $db,
        'CALL sp_lookupShortURL(?, ?, @outDest, @outStatus, @outOrg, '
        . '@outUtmSource, @outUtmMedium, @outUtmCampaign, @outUtmTerm, @outUtmContent)'
    );

    if ($statement === false)
    {
        throw new RuntimeException('Prepare CALL failed: ' . mysqli_error($db));
    }

    mysqli_stmt_bind_param($statement, 'ss', $domain, $shortCode);

    $executed = mysqli_stmt_execute($statement);

    if ($executed === false)
    {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('CALL failed: ' . $error);
    }

    mysqli_stmt_close($statement);

    $result = mysqli_query(
        $db,
        'SELECT @outDest AS destination, @outStatus AS status, @outOrg AS orgHandle, '
        . '@outUtmSource AS utmSource, @outUtmMedium AS utmMedium, @outUtmCampaign AS utmCampaign, '
        . '@outUtmTerm AS utmTerm, @outUtmContent AS utmContent'
    );

    if ($result === false)
    {
        throw new RuntimeException('Reading OUT parameters failed: ' . mysqli_error($db));
    }

    $row = mysqli_fetch_assoc($result);
    mysqli_free_result($result);

    if ($row === null)
    {
        throw new RuntimeException('No OUT-parameter row returned');
    }

    return array(
        'destination' => $row['destination'],
        'status'      => $row['status'],
        'orgHandle'   => $row['orgHandle'],
        'utmSource'   => $row['utmSource'],
        'utmMedium'   => $row['utmMedium'],
        'utmCampaign' => $row['utmCampaign'],
        'utmTerm'     => $row['utmTerm'],
        'utmContent'  => $row['utmContent'],
    );
}

/**
 * Register the integration tests. Called by run_integration.php with a live
 * mysqli handle.
 *
 * @param  mysqli $db
 * @return void
 */
function g2ml_register_integration_tests(mysqli $db): void
{
    // ------------------------------------------------------------------------
    // Shared setup: ensure the 'free' tier and the [default] org exist, then
    // seed the test rows. We run this once, here, so the registered closures
    // can rely on it. tblOrganisations.tierID has a foreign key onto
    // tblSubscriptionTiers, so the tier must be present first. We seed the
    // minimum required rather than depending on the full seed set.
    // ------------------------------------------------------------------------
    g2ml_test_exec(
        $db,
        "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`) "
        . "VALUES ('free', 'Free') "
        . "ON DUPLICATE KEY UPDATE `tierName` = VALUES(`tierName`)"
    );

    g2ml_test_exec(
        $db,
        "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
        . "VALUES ('[default]', 'Default Test Org', 'https://go2my.link/fallback', 'free', 1) "
        . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`)"
    );

    // Clean any leftover rows from a previous run so the suite is repeatable.
    g2ml_test_exec($db, "DELETE FROM `tblShortURLs` WHERE `shortCode` IN ('itlive', 'itgone', 'itsoon', 'itutm', 'itutmgone')");

    // (a) A live row pointing at a known destination.
    g2ml_test_insert_shorturl($db, 'itlive', 'https://example.com/live-destination', null, null, 1);

    // (b) An expired row — endDate in the past.
    g2ml_test_insert_shorturl($db, 'itgone', 'https://example.com/expired-destination', null, '2000-01-01 00:00:00', 1);

    // (c) A not-yet-active row — startDate in the future (bonus characterisation).
    g2ml_test_insert_shorturl($db, 'itsoon', 'https://example.com/future-destination', '2999-01-01 00:00:00', null, 1);

    // (d) #92 — a live row with all five UTM columns configured.
    g2ml_test_insert_shorturl(
        $db,
        'itutm',
        'https://example.com/utm-destination',
        null,
        null,
        1,
        'newsletter',
        'email',
        'spring-sale',
        'shortlinks',
        'variant-a'
    );

    // (e) #92 — an EXPIRED row that ALSO has UTM columns configured, to prove
    // the projection is success-only (see resolve_loop in sp_lookupShortURL.sql).
    g2ml_test_insert_shorturl(
        $db,
        'itutmgone',
        'https://example.com/utm-expired-destination',
        null,
        '2000-01-01 00:00:00',
        1,
        'newsletter',
        'email',
        'spring-sale',
        'shortlinks',
        'variant-a'
    );

    // ------------------------------------------------------------------------
    // (a) Live short code → status 'success', destination matches.
    // ------------------------------------------------------------------------
    test('sp_lookupShortURL: a live code resolves with status=success and the stored destination', function () use ($db): void
    {
        $outcome = g2ml_test_lookup($db, 'g2my.link', 'itlive');

        assert_same('success', $outcome['status'], 'A live, active, in-window code resolves to success');
        assert_same('https://example.com/live-destination', $outcome['destination'], 'The destination URL is returned verbatim');
        assert_same('[default]', $outcome['orgHandle'], 'Unknown domain falls back to the [default] org');
        assert_same(null, $outcome['utmSource'], '(#92) A link with no configured UTM values returns utmSource as null');
        assert_same(null, $outcome['utmMedium'], '(#92) A link with no configured UTM values returns utmMedium as null');
        assert_same(null, $outcome['utmCampaign'], '(#92) A link with no configured UTM values returns utmCampaign as null');
        assert_same(null, $outcome['utmTerm'], '(#92) A link with no configured UTM values returns utmTerm as null');
        assert_same(null, $outcome['utmContent'], '(#92) A link with no configured UTM values returns utmContent as null');
    });

    // ------------------------------------------------------------------------
    // (d) #92 — a live code with configured UTM values returns all five on
    //     success — the SAME single lookup query, no extra round trip.
    // ------------------------------------------------------------------------
    test('sp_lookupShortURL: (#92) a live code with configured UTM values returns them all on success', function () use ($db): void
    {
        $outcome = g2ml_test_lookup($db, 'g2my.link', 'itutm');

        assert_same('success', $outcome['status'], 'The UTM-configured row still resolves normally');
        assert_same('https://example.com/utm-destination', $outcome['destination'], 'The destination URL is unaffected by UTM projection');
        assert_same('newsletter', $outcome['utmSource'], 'utmSource is projected verbatim');
        assert_same('email', $outcome['utmMedium'], 'utmMedium is projected verbatim');
        assert_same('spring-sale', $outcome['utmCampaign'], 'utmCampaign is projected verbatim');
        assert_same('shortlinks', $outcome['utmTerm'], 'utmTerm is projected verbatim');
        assert_same('variant-a', $outcome['utmContent'], 'utmContent is projected verbatim');
    });

    // ------------------------------------------------------------------------
    // (e) #92 — an expired code with configured UTM values still returns
    //     status=expired AND all five UTM outputs as null — the projection is
    //     success-only, so a broken/expired link is never forwarded onto.
    // ------------------------------------------------------------------------
    test('sp_lookupShortURL: (#92) an expired code with configured UTM values returns status=expired with all UTM fields null', function () use ($db): void
    {
        $outcome = g2ml_test_lookup($db, 'g2my.link', 'itutmgone');

        assert_same('expired', $outcome['status'], 'The row still resolves to expired, unaffected by having UTM columns set');
        assert_same(null, $outcome['utmSource'], '(#92) UTM projection is success-only — expired must return null, never the stored value');
        assert_same(null, $outcome['utmMedium'], '(#92) UTM projection is success-only — expired must return null, never the stored value');
        assert_same(null, $outcome['utmCampaign'], '(#92) UTM projection is success-only — expired must return null, never the stored value');
        assert_same(null, $outcome['utmTerm'], '(#92) UTM projection is success-only — expired must return null, never the stored value');
        assert_same(null, $outcome['utmContent'], '(#92) UTM projection is success-only — expired must return null, never the stored value');
    });

    // ------------------------------------------------------------------------
    // (b) Expired short code → status 'expired'.
    // ------------------------------------------------------------------------
    test('sp_lookupShortURL: an expired code resolves with status=expired', function () use ($db): void
    {
        $outcome = g2ml_test_lookup($db, 'g2my.link', 'itgone');

        assert_same('expired', $outcome['status'], 'A row past its endDate resolves to expired');
    });

    // ------------------------------------------------------------------------
    // (c) Missing short code → status 'not_found'.
    // ------------------------------------------------------------------------
    test('sp_lookupShortURL: an unknown code resolves with status=not_found', function () use ($db): void
    {
        $outcome = g2ml_test_lookup($db, 'g2my.link', 'does-not-exist-xyz');

        assert_same('not_found', $outcome['status'], 'A code that does not exist resolves to not_found');
    });

    // ------------------------------------------------------------------------
    // Bonus: a not-yet-active code → status 'not_yet_active' (current contract).
    // ------------------------------------------------------------------------
    test('sp_lookupShortURL: a future-dated code resolves with status=not_yet_active', function () use ($db): void
    {
        $outcome = g2ml_test_lookup($db, 'g2my.link', 'itsoon');

        assert_same('not_yet_active', $outcome['status'], 'A row whose startDate is in the future resolves to not_yet_active');
    });
}
