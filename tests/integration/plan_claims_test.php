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
 * 🧪 Integration check — what the pricing page says about the Free plan (#209)
 * ============================================================================
 *
 * The page says an organisation can create new links while fewer than 50 are
 * active. This runs createShortURL() against a real organisation on the real
 * 'free' tier, with 49 and then 50 active links, and reads the words of the
 * refusal from the database (seed 082) through the real __().
 *
 * It says nothing about people in no organisation: the limit is not checked
 * for them, and the page does not claim it is.
 *
 * Runs after entitlements_test.php in name order, and defines no constants or
 * connection of its own: it skips when no database connection is configured.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.0.0 (#209)
 * ============================================================================
 */

declare(strict_types=1);

if (!defined('DB_HOST') || !function_exists('createShortURL'))
{
    return;
}

require_once dirname(__DIR__, 2) . '/web/_functions/i18n.php';

test('Free plan (#209): an organisation is refused its 51st active link, with words that promise no upgrade', function (): void
{
    $db     = getDB();
    $handle = 'plan209org';

    mysqli_query($db, "DELETE FROM `tblShortURLs` WHERE `orgHandle` = 'plan209org'");
    $made = mysqli_query(
        $db,
        "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `tierID`, `isVerified`, `isActive`) "
        . "VALUES ('plan209org', 'Plan 209', 'free', 0, 1) "
        . "ON DUPLICATE KEY UPDATE `tierID` = 'free', `isActive` = 1"
    );
    assert_true($made !== false, 'Could not create the test organisation: ' . mysqli_error($db));

    for ($number = 1; $number <= 49; $number++)
    {
        $code = 'p209-' . $number;
        $ok   = dbInsert(
            'INSERT INTO `tblShortURLs` (`orgHandle`, `shortCode`, `destinationURL`, `isActive`) VALUES (?, ?, ?, 1)',
            'sss',
            [$handle, $code, 'https://example.com/']
        );
        assert_true($ok !== false, 'Could not create test link ' . $number);
    }

    // One of the 49 is past its end date. It is still switched on, so it must
    // still count, as the pricing page says.
    $expired = mysqli_query(
        $db,
        "UPDATE `tblShortURLs` SET `endDate` = '2020-01-01 00:00:00' WHERE `orgHandle` = 'plan209org' AND `shortCode` = 'p209-1'"
    );
    assert_true($expired !== false, 'Could not expire a test link: ' . mysqli_error($db));

    unset($_SESSION['session_token'], $_SESSION['user_uid'], $_SESSION['user_role'], $_SESSION['user_org_handle']);
    g2ml_clearOrgTierCache($handle);

    $fiftieth    = createShortURL('https://example.com/plan209-50', ['orgHandle' => $handle]);
    $fiftyFirst  = createShortURL('https://example.com/plan209-51', ['orgHandle' => $handle]);
    $storedWords = dbSelectOne(
        "SELECT `translationValue` FROM `tblTranslations` WHERE `localeCode` = 'en-GB' AND `translationKey` = 'links.error_plan_limit'",
        '',
        []
    );

    mysqli_query($db, "DELETE FROM `tblShortURLs` WHERE `orgHandle` = 'plan209org'");
    mysqli_query($db, "DELETE FROM `tblOrganisations` WHERE `orgHandle` = 'plan209org'");
    g2ml_clearOrgTierCache($handle);

    assert_true($fiftieth['success'], 'The 50th active link is accepted');
    assert_false($fiftyFirst['success'], 'The 51st active link is refused');
    assert_true(is_array($storedWords), 'Seed 082 has the refusal message');
    assert_same((string) $storedWords['translationValue'], $fiftyFirst['error']);
    assert_true(stripos($fiftyFirst['error'], 'upgrade') === false, 'The message still asks for an upgrade');
});
