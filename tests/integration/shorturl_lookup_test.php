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
 * @return void
 */
function g2ml_test_insert_shorturl(mysqli $db, string $shortCode, ?string $destinationURL, ?string $startDate, ?string $endDate, int $isActive): void
{
    $sql = 'INSERT INTO `tblShortURLs` '
        . '(`orgHandle`, `shortCode`, `destinationURL`, `destinationType`, `startDate`, `endDate`, `isActive`) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?)';

    $statement = mysqli_prepare($db, $sql);

    if ($statement === false)
    {
        throw new RuntimeException('Prepare failed: ' . mysqli_error($db));
    }

    $orgHandle       = '[default]';
    $destinationType = 'url';

    mysqli_stmt_bind_param(
        $statement,
        'ssssssi',
        $orgHandle,
        $shortCode,
        $destinationURL,
        $destinationType,
        $startDate,
        $endDate,
        $isActive
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
 * CALL sp_lookupShortURL and return its OUT parameters.
 *
 * @param  mysqli $db
 * @param  string $domain
 * @param  string $shortCode
 * @return array{destination: ?string, status: ?string, orgHandle: ?string}
 */
function g2ml_test_lookup(mysqli $db, string $domain, string $shortCode): array
{
    $statement = mysqli_prepare($db, 'CALL sp_lookupShortURL(?, ?, @outDest, @outStatus, @outOrg)');

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

    $result = mysqli_query($db, 'SELECT @outDest AS destination, @outStatus AS status, @outOrg AS orgHandle');

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
    g2ml_test_exec($db, "DELETE FROM `tblShortURLs` WHERE `shortCode` IN ('itlive', 'itgone', 'itsoon')");

    // (a) A live row pointing at a known destination.
    g2ml_test_insert_shorturl($db, 'itlive', 'https://example.com/live-destination', null, null, 1);

    // (b) An expired row — endDate in the past.
    g2ml_test_insert_shorturl($db, 'itgone', 'https://example.com/expired-destination', null, '2000-01-01 00:00:00', 1);

    // (c) A not-yet-active row — startDate in the future (bonus characterisation).
    g2ml_test_insert_shorturl($db, 'itsoon', 'https://example.com/future-destination', '2999-01-01 00:00:00', null, 1);

    // ------------------------------------------------------------------------
    // (a) Live short code → status 'success', destination matches.
    // ------------------------------------------------------------------------
    test('sp_lookupShortURL: a live code resolves with status=success and the stored destination', function () use ($db): void
    {
        $outcome = g2ml_test_lookup($db, 'g2my.link', 'itlive');

        assert_same('success', $outcome['status'], 'A live, active, in-window code resolves to success');
        assert_same('https://example.com/live-destination', $outcome['destination'], 'The destination URL is returned verbatim');
        assert_same('[default]', $outcome['orgHandle'], 'Unknown domain falls back to the [default] org');
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
