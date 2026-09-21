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
 * 📋 Go2My.Link — Data Subject Rights Functions
 * ============================================================================
 *
 * Implements GDPR/CCPA data subject rights:
 *   - Data export (Article 20 / CCPA right to know)
 *   - Data deletion (Article 17 / CCPA right to delete)
 *   - Data anonymisation
 *   - Consent history
 *
 * Dependencies: db_connect.php, db_query.php, settings.php, email.php,
 *               security.php, auth.php
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.7.0
 * @since      Phase 6
 *
 * 📖 References:
 *     - GDPR Art 17: https://gdpr-info.eu/art-17-gdpr/
 *     - GDPR Art 20: https://gdpr-info.eu/art-20-gdpr/
 *     - CCPA:        https://oag.ca.gov/privacy/ccpa
 * ============================================================================
 */

// ============================================================================
// 🛡️ Direct Access Guard
// ============================================================================
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}

// ============================================================================
// 📦 Request Data Export
// ============================================================================

/**
 * Gather all user data and create a downloadable JSON export file.
 *
 * Collects data from: tblUsers, tblShortURLs, tblConsentRecords,
 * tblUserSessions, tblActivityLog (user's own entries), tblLinksPages and
 * tblLinksPageItems (the user's own LinksPage profile pages and the links on
 * them — added for #219; before this, a subject-access export left out a
 * whole category of the person's own content, which is itself a GDPR
 * Article 20 gap).
 *
 * @param  int   $userUID  The user requesting the export
 * @return array           ['success' => bool, 'requestUID' => int|null, 'error' => string|null]
 */
function g2ml_requestDataExport(int $userUID): array
{
    $db = getDB();

    if ($db === null)
    {
        return ['success' => false, 'error' => 'Database unavailable'];
    }

    // Check for pending/processing export request
    $sql  = "SELECT requestUID FROM tblDataDeletionRequests
             WHERE userUID = ? AND requestType = 'export'
               AND status IN ('pending', 'processing')
             LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $userUID);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing !== null)
    {
        return ['success' => false, 'error' => 'An export request is already in progress'];
    }

    try
    {
        // Gather user profile data
        $userData = [];

        $sql  = "SELECT userUID, email, firstName, lastName, displayName, timezone,
                        role, isActive, emailVerified, createdAt, updatedAt
                 FROM tblUsers WHERE userUID = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $userUID);
        $stmt->execute();
        $userData['profile'] = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Gather short URLs
        $sql  = "SELECT shortCode, destinationURL, title, isActive, clickCount,
                        createdAt, updatedAt, startDate, endDate
                 FROM tblShortURLs WHERE createdByUserUID = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $userUID);
        $stmt->execute();
        $userData['short_urls'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Gather consent records
        $sql  = "SELECT consentType, consentGiven, consentMethod, jurisdiction,
                        consentVersion, createdAt, expiresAt
                 FROM tblConsentRecords WHERE userUID = ? ORDER BY createdAt DESC";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $userUID);
        $stmt->execute();
        $userData['consent_records'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Gather sessions (exclude tokens for security)
        $sql  = "SELECT sessionUID, deviceInfo, ipAddress,
                        isActive, createdAt, lastActivityAt, expiresAt
                 FROM tblUserSessions WHERE userUID = ? ORDER BY createdAt DESC";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $userUID);
        $stmt->execute();
        $userData['sessions'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Gather LinksPages (#219). A LinksPage is the person's own public
        // profile page (page title, bio, avatar, colours, font, social
        // links), so it belongs in a subject-access export exactly like
        // their short URLs above.
        //
        // customHTML and customCSS are deliberately left OUT, and a plain
        // hasCustomHTML/hasCustomCSS flag stands in for each instead.
        //
        // The reason is size, not that the content is unimportant: both are
        // content the user wrote themselves, and a right-of-access export is
        // meant to cover exactly that. customHTML is capped at
        // G2ML_CUSTOM_HTML_MAX_BYTES (100 KB, see
        // web/_functions/html_sanitiser.php) and customCSS at
        // G2ML_CUSTOM_CSS_MAX_BYTES (50 KB), and the export is one JSON blob
        // meant to stay readable at a glance, so including either raw column
        // would work against that. This is NOT because the content is
        // "already visible on the rendered page" — that would only be true
        // while the page is published AND its organisation is currently
        // allowed the custom-HTML feature, and neither is guaranteed (an
        // unpublished page's custom HTML is shown to nobody, and the
        // custom-HTML kill switch ships OFF, so today it is never rendered
        // for anyone at all).
        //
        // Whether the actual content should be added to the export despite
        // the size trade-off is an open product decision, tracked as #245.
        // Cite that issue here and in docs/DATABASE.md if this changes.
        $sql  = "SELECT pageUID, slug, pageTitle, pageDescription, avatarPath,
                        templateUID, themeColour, backgroundColour, fontFamily,
                        showSocialIcons, socialLinks, isPublished, isActive,
                        (customHTML IS NOT NULL) AS hasCustomHTML,
                        (customCSS IS NOT NULL) AS hasCustomCSS,
                        createdAt, updatedAt
                 FROM tblLinksPages WHERE userUID = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $userUID);
        $stmt->execute();
        $userData['linkspages'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Gather LinksPage items (the individual links placed on each of the
        // user's own pages). Joined through tblLinksPages rather than
        // filtered by a userUID column of its own — tblLinksPageItems has no
        // such column, only pageUID — so this stays scoped to the SAME
        // user's pages only, never anyone else's items.
        $sql  = "SELECT i.pageUID, i.itemTitle, i.itemURL, i.itemDescription,
                        i.itemIcon, i.requiresAgeGate, i.sortOrder, i.isActive,
                        i.createdAt, i.updatedAt
                 FROM tblLinksPageItems i
                 INNER JOIN tblLinksPages p ON i.pageUID = p.pageUID
                 WHERE p.userUID = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $userUID);
        $stmt->execute();
        $userData['linkspage_items'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Write JSON export
        $exportDir = G2ML_UPLOADS . DIRECTORY_SEPARATOR . 'exports';

        if (!is_dir($exportDir))
        {
            mkdir($exportDir, 0750, true);
        }

        $filename = 'export_' . $userUID . '_' . date('Ymd_His') . '.json';
        $filepath = $exportDir . DIRECTORY_SEPARATOR . $filename;

        $jsonContent = json_encode([
            'export_date'    => date('Y-m-d\TH:i:s\Z'),
            'export_version' => '1.0',
            'service'        => 'Go2My.Link',
            'user_uid'       => $userUID,
            'data'           => $userData,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        file_put_contents($filepath, $jsonContent);

        // Calculate expiry
        if (function_exists('getSetting'))
        {
            $expiryHours = (int) getSetting('compliance.data_export_expiry_hours', 48);
        }
        else
        {
            $expiryHours = 48;
        }

        $expiresAt   = date('Y-m-d H:i:s', strtotime("+{$expiryHours} hours"));

        // Create request record
        $sql = "INSERT INTO tblDataDeletionRequests
                (userUID, requestType, status, exportFilePath, exportExpiresAt)
                VALUES (?, 'export', 'completed', ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('iss', $userUID, $filepath, $expiresAt);
        $stmt->execute();
        $requestUID = $stmt->insert_id;
        $stmt->close();

        // Update processedAt
        $sql = "UPDATE tblDataDeletionRequests SET processedAt = NOW(), status = 'completed'
                WHERE requestUID = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $requestUID);
        $stmt->execute();
        $stmt->close();

        // Send email notification
        if (function_exists('g2ml_sendEmail') && $userData['profile'] !== null)
        {
            $email = $userData['profile']['email'];
            g2ml_sendEmail($email, 'Your Data Export is Ready', 'data_export_ready', [
                'displayName' => $userData['profile']['displayName'] ?? $userData['profile']['firstName'],
                'downloadURL' => 'https://admin.go2my.link/privacy/export?download=' . $requestUID,
                'expiryHours' => $expiryHours,
            ]);
        }

        if (function_exists('logActivity'))
        {
            logActivity('data_export_requested', 'success', null, [
                'userUID' => $userUID,
                'logData' => ['requestUID' => $requestUID],
            ]);
        }

        return ['success' => true, 'requestUID' => $requestUID];
    }
    catch (\Throwable $e)
    {
        error_log('[Go2My.Link] ERROR: g2ml_requestDataExport failed: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Export failed. Please try again later.'];
    }
}

// ============================================================================
// 📥 Handle Data Export Download (#162)
// ============================================================================
//
// The admin dashboard page pages/privacy/export/index.php links to (and
// g2ml_requestDataExport()'s own email above sends) a download URL of the
// form /privacy/export?download=<requestUID> — but the file-based router
// (web/Go2My.Link/_admin/public_html/index.php) ALWAYS requires header.php +
// nav.php (which echo real HTML — there is no output buffering anywhere in
// this app) BEFORE it ever requires the resolved page file. By the time a
// page file's own code runs, a header() call for Content-Type/
// Content-Disposition/Location would silently fail ("headers already sent")
// — the exact constraint pages/analytics/index.php's own docblock documents,
// and the reason analytics-export.php (#44) exists as a second, standalone
// entry point in public_html/ rather than a pages/ file.
//
// g2ml_handleDataExportDownloadRequest() below is the equivalent fix for this
// route, but dispatched from the ROUTER itself — see index.php's Step 5 —
// BEFORE header.php is required, rather than as a second public_html file.
// This keeps the download reachable at the SAME URL the page links to and
// the emailed downloadURL already promises, with no change to either.
// ============================================================================

/**
 * Look up a data-export request row for download, scoped to the CURRENT user
 * in the SAME prepared statement as the ownership check.
 *
 * ⚠️ IDOR guard (#162): requestUID is NEVER trusted alone — every caller must
 * also supply the authenticated session's own userUID, and both are matched
 * in one WHERE clause. A requestUID that exists but belongs to a DIFFERENT
 * user therefore returns null here — indistinguishable from a requestUID
 * that does not exist at all, which is exactly the non-disclosure behaviour
 * g2ml_handleDataExportDownloadRequest() needs.
 *
 * @param  int $requestUID  The requestUID from ?download=.
 * @param  int $userUID     The CURRENT authenticated user's userUID (never
 *                          client-supplied).
 * @return array|null|false The row (requestUID, status, exportFilePath,
 *                          exportExpiresAt), null if no matching row exists
 *                          for THIS user, or false on a DB error.
 */
function g2ml_findExportRequestForDownload(int $requestUID, int $userUID): array|null|false
{
    return dbSelectOne(
        "SELECT requestUID, status, exportFilePath, exportExpiresAt
         FROM tblDataDeletionRequests
         WHERE requestUID = ? AND userUID = ? AND requestType = 'export'
         LIMIT 1",
        'ii',
        [$requestUID, $userUID]
    );
}

/**
 * PURE — determine whether an export-request row (already ownership-scoped
 * by g2ml_findExportRequestForDownload()) is in a downloadable state.
 *
 * A row is downloadable only when ALL of the following hold:
 *   - it is not null/false (not found, wrong owner, or a DB error);
 *   - status is exactly 'completed' (pending/processing/rejected are refused);
 *   - exportFilePath is a non-empty string;
 *   - exportExpiresAt is a non-empty, parseable timestamp still in the future
 *     (an expired or missing expiry is refused).
 *
 * No I/O, no superglobals — safe to unit test without a database.
 *
 * @param  array|null|false $requestRow  A row from
 *                                       g2ml_findExportRequestForDownload(),
 *                                       or null/false as that function
 *                                       returns for "not found"/"DB error".
 * @return bool
 *
 * @phpstan-assert-if-true array $requestRow
 */
function g2ml_isExportRequestDownloadable(array|null|false $requestRow): bool
{
    if (!is_array($requestRow))
    {
        return false;
    }

    if (($requestRow['status'] ?? '') !== 'completed')
    {
        return false;
    }

    $exportFilePath = $requestRow['exportFilePath'] ?? null;

    if (!is_string($exportFilePath) || $exportFilePath === '')
    {
        return false;
    }

    $exportExpiresAt = $requestRow['exportExpiresAt'] ?? null;

    if (!is_string($exportExpiresAt) || $exportExpiresAt === '')
    {
        return false;
    }

    $expiryTimestamp = strtotime($exportExpiresAt);

    if ($expiryTimestamp === false)
    {
        return false;
    }

    if ($expiryTimestamp <= time())
    {
        return false;
    }

    return true;
}

/**
 * Redirect back to the privacy export page with a single, generic,
 * non-disclosing error flag.
 *
 * Used for EVERY rejection reason (not found, wrong owner, wrong status,
 * expired, or a corrupted/missing file all land here via the SAME code path)
 * so the response can never be used as an oracle for whether a given
 * requestUID exists or who owns it (#162 IDOR guard).
 *
 * Safe to call here specifically because g2ml_handleDataExportDownloadRequest()
 * is only ever invoked from the router BEFORE header.php is required (see
 * this file's own section docblock above) — a plain header('Location: ...')
 * would fail everywhere else in this app.
 *
 * @return void  Always exits — never returns.
 */
function g2ml_redirectExportDownloadError(): void
{
    header('Location: /privacy/export?export_error=1');
    exit;
}

/**
 * Stream a validated, ownership-checked export file to the browser as a JSON
 * download, log the download, then exit.
 *
 * NOT pure — sends headers, echoes output, calls exit(). Callers MUST have
 * already confirmed g2ml_isExportRequestDownloadable($requestRow) === true
 * and that $requestRow was resolved via g2ml_findExportRequestForDownload()
 * scoped to the CURRENT userUID — never call this with an unverified row.
 *
 * The stored exportFilePath is never client-supplied (see
 * g2ml_requestDataExport() above), but is still confined to the expected
 * exports directory via realpath() before being opened, as defence in depth
 * against a corrupted or tampered path ever escaping it.
 *
 * @param  array $requestRow  A downloadable row from
 *                            g2ml_findExportRequestForDownload().
 * @param  int   $userUID     The current, authenticated user (for the
 *                            activity log).
 * @return void               Always exits — never returns.
 */
function g2ml_streamExportDownload(array $requestRow, int $userUID): void
{
    $requestUID     = (int) $requestRow['requestUID'];
    $exportFilePath = (string) $requestRow['exportFilePath'];

    $exportsDirectory     = G2ML_UPLOADS . DIRECTORY_SEPARATOR . 'exports';
    $realExportsDirectory = realpath($exportsDirectory);
    $realFilePath         = realpath($exportFilePath);

    if ($realExportsDirectory === false || $realFilePath === false)
    {
        error_log('[Go2My.Link] ERROR: g2ml_streamExportDownload — export file missing on disk for requestUID ' . $requestUID);
        g2ml_redirectExportDownloadError();
        return;
    }

    $confinedPrefix = $realExportsDirectory . DIRECTORY_SEPARATOR;

    if (strncmp($realFilePath, $confinedPrefix, strlen($confinedPrefix)) !== 0)
    {
        error_log('[Go2My.Link] ERROR: g2ml_streamExportDownload — exportFilePath escapes the exports directory for requestUID ' . $requestUID);
        g2ml_redirectExportDownloadError();
        return;
    }

    $jsonContent = file_get_contents($realFilePath);

    if ($jsonContent === false)
    {
        error_log('[Go2My.Link] ERROR: g2ml_streamExportDownload — could not read export file for requestUID ' . $requestUID);
        g2ml_redirectExportDownloadError();
        return;
    }

    if (function_exists('logActivity'))
    {
        logActivity('data_export_downloaded', 'success', null, [
            'userUID' => $userUID,
            'logData' => ['requestUID' => $requestUID],
        ]);
    }

    $downloadFilename = 'go2mylink-data-export-' . $requestUID . '.json';

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
    header('Content-Length: ' . (string) strlen($jsonContent));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');

    echo $jsonContent;

    exit;
}

/**
 * Handle a GET ?download=<requestUID> request against the /privacy/export
 * route (#162).
 *
 * MUST be called from the router BEFORE header.php is required (see
 * web/Go2My.Link/_admin/public_html/index.php, Step 5) — see this file's own
 * section docblock above for why.
 *
 * Reuses the page's own requireAuth() guard, then re-verifies ownership by
 * matching BOTH requestUID and the session's userUID in the SAME prepared
 * statement (g2ml_findExportRequestForDownload()) — requestUID is NEVER
 * trusted alone. Only a 'completed', unexpired request is streamed; every
 * other outcome (not found, wrong owner, wrong status, expired, unreadable
 * file) redirects to a single generic error state so the response can never
 * disclose whether a given requestUID exists or who owns it.
 *
 * @return void  Always exits — never returns.
 */
function g2ml_handleDataExportDownloadRequest(): void
{
    requireAuth();

    $currentUser = getCurrentUser();

    if ($currentUser === null)
    {
        g2ml_redirectExportDownloadError();
        return;
    }

    $userUID = (int) $currentUser['userUID'];

    $rawRequestUID = '';

    if (isset($_GET['download']) && is_string($_GET['download']))
    {
        $rawRequestUID = $_GET['download'];
    }

    if ($rawRequestUID === '' || !ctype_digit($rawRequestUID))
    {
        g2ml_redirectExportDownloadError();
        return;
    }

    $requestUID = (int) $rawRequestUID;

    if ($requestUID <= 0)
    {
        g2ml_redirectExportDownloadError();
        return;
    }

    $requestRow = g2ml_findExportRequestForDownload($requestUID, $userUID);

    if (!g2ml_isExportRequestDownloadable($requestRow))
    {
        g2ml_redirectExportDownloadError();
        return;
    }

    g2ml_streamExportDownload($requestRow, $userUID);
}

// ============================================================================
// 🗑️ Request Data Deletion
// ============================================================================

/**
 * Create a pending data deletion request with a grace period.
 *
 * The deletion is NOT executed immediately — it enters a grace period
 * during which the user can cancel. After the grace period, an admin
 * (or automated process) executes the deletion.
 *
 * @param  int         $userUID  The user requesting deletion
 * @param  string|null $reason   Optional reason for the request
 * @return array                 ['success' => bool, 'requestUID' => int|null, 'graceDays' => int, 'error' => string|null]
 */
function g2ml_requestDataDeletion(int $userUID, ?string $reason = null): array
{
    $db = getDB();

    if ($db === null)
    {
        return ['success' => false, 'error' => 'Database unavailable'];
    }

    // Check for existing pending deletion request
    $sql  = "SELECT requestUID FROM tblDataDeletionRequests
             WHERE userUID = ? AND requestType = 'deletion'
               AND status IN ('pending', 'processing')
             LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $userUID);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing !== null)
    {
        return ['success' => false, 'error' => 'A deletion request is already pending'];
    }

    if (function_exists('getSetting')) {
        $graceDays = (int) getSetting('compliance.data_deletion_grace_days', 30);
    } else {
        $graceDays = 30;
    }

    try
    {
        $sql = "INSERT INTO tblDataDeletionRequests
                (userUID, requestType, status, requestReason)
                VALUES (?, 'deletion', 'pending', ?)";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('is', $userUID, $reason);
        $stmt->execute();
        $requestUID = $stmt->insert_id;
        $stmt->close();

        // Send confirmation email
        if (function_exists('g2ml_sendEmail') && function_exists('getCurrentUser'))
        {
            $user = getCurrentUser();

            if ($user !== null)
            {
                g2ml_sendEmail($user['email'], 'Data Deletion Request Received', 'data_deletion_requested', [
                    'displayName' => $user['displayName'] ?? $user['firstName'],
                    'graceDays'   => $graceDays,
                    'cancelURL'   => 'https://admin.go2my.link/privacy/delete?cancel=' . $requestUID,
                ]);
            }
        }

        if (function_exists('logActivity'))
        {
            logActivity('data_deletion_requested', 'success', null, [
                'userUID' => $userUID,
                'logData' => ['requestUID' => $requestUID, 'graceDays' => $graceDays],
            ]);
        }

        return ['success' => true, 'requestUID' => $requestUID, 'graceDays' => $graceDays];
    }
    catch (\Throwable $e)
    {
        error_log('[Go2My.Link] ERROR: g2ml_requestDataDeletion failed: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Request failed. Please try again later.'];
    }
}

// ============================================================================
// 🔒 Anonymise User Data
// ============================================================================

/**
 * Anonymise one user's account: a PARTIAL erasure, not a complete one.
 *
 * ⚠️ Do not quote this function to a customer, a support ticket or a
 * regulator as "we erase all your personal data". It changes exactly the
 * columns listed under "What this function changes" below and NOTHING
 * else. Personal data also lives in columns and rows this function never
 * touches (examples below). The full inventory of what erasure leaves
 * behind, and the fix, is tracked in issue #247. The earlier version of
 * this comment said "Replace all PII with anonymised placeholders across
 * all tables", which was never true; review rounds 5 to 8 of #219 kept
 * finding more that it leaves behind. So this comment now states only what
 * the code does (which can be checked line by line against the SQL below)
 * and points to #247 for the rest, rather than attempting a complete list
 * that would keep going out of date.
 *
 * This is a destructive, irreversible operation, used after the grace
 * period of a deletion request. Everything happens inside ONE transaction,
 * so a failure partway through rolls every change back rather than leaving
 * the account half-anonymised.
 *
 * What this function changes — exactly, and only this:
 *   - tblUsers (the person's row): email becomes
 *     'deleted_<userUID>@anonymised.go2my.link'; firstName, lastName and
 *     displayName become '[DELETED]';
 *     passwordHash becomes empty; avatarPath becomes NULL; isActive and
 *     emailVerified become 0; updatedAt is set to the current time. The
 *     row is kept.
 *   - tblUserSessions (the person's rows): isActive becomes 0. Nothing
 *     else on those rows changes.
 *   - tblActivityLog (rows with the person's userUID): ipAddress becomes
 *     '0.0.0.0'. Nothing else on those rows changes.
 *   - tblConsentRecords (the person's rows): ipAddress becomes '0.0.0.0'
 *     and userAgent becomes NULL. The rows are kept.
 *   - tblLinksPages (the person's pages): DELETED outright (added for
 *     #219; the inline comment at the DELETE below explains why a page is
 *     deleted rather than edited). Their tblLinksPageItems rows go with
 *     them through FK_item_page ON DELETE CASCADE, and a custom short
 *     domain that pointed at one of those pages has that pointer set to
 *     NULL through FK_short_domain_linkspage ON DELETE SET NULL. The
 *     domain then behaves as if no page had ever been chosen for it: a
 *     visit to the bare domain is redirected to the site's fallback address
 *     (the redirect.fallback_url setting), and an unknown path gets the
 *     usual not-found page, unless the organisation has set its own
 *     fallback address (orgFallbackURL), in which case the visitor is
 *     redirected there.
 *
 * Examples of personal data this function LEAVES BEHIND (not a complete
 * list — see #247): on tblUsers, username (often built from the email
 * address), lastLoginIP, userNotes and suspendedReason; on sessions, the
 * IP address, browser string and device details; on the activity log, the
 * browser string and the details split out of it, paths, referrers,
 * location, the addresses the person shortened (destinationURL), and
 * logData — which holds
 * the person's email address on sign-in and other account events and on
 * email-sending rows, and LinksPage slugs. Some activity-log rows written
 * while the person was signed out carry no userUID at all — for example the
 * row recording that a password-reset email was sent to them, whose logData
 * holds their email address — so this function cannot find them.
 *
 * @param  int  $userUID  The user to anonymise
 * @return bool           true if anonymisation completed
 */
function g2ml_anonymiseUserData(int $userUID): bool
{
    $db = getDB();

    if ($db === null)
    {
        return false;
    }

    $placeholder = '[DELETED]';
    $anonEmail   = 'deleted_' . $userUID . '@anonymised.go2my.link';

    try
    {
        $db->begin_transaction();

        // Anonymise user profile
        $sql = "UPDATE tblUsers SET
                    email = ?, firstName = ?, lastName = ?, displayName = ?,
                    passwordHash = '', avatarPath = NULL, isActive = 0,
                    emailVerified = 0, updatedAt = NOW()
                WHERE userUID = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('ssssi', $anonEmail, $placeholder, $placeholder, $placeholder, $userUID);
        $stmt->execute();
        $stmt->close();

        // Revoke all sessions
        $sql  = "UPDATE tblUserSessions SET isActive = 0 WHERE userUID = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $userUID);
        $stmt->execute();
        $stmt->close();

        // Anonymise IP addresses in activity log
        $sql  = "UPDATE tblActivityLog SET ipAddress = '0.0.0.0' WHERE userUID = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $userUID);
        $stmt->execute();
        $stmt->close();

        // Anonymise consent records (keep structure for legal compliance)
        $sql  = "UPDATE tblConsentRecords SET ipAddress = '0.0.0.0', userAgent = NULL WHERE userUID = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $userUID);
        $stmt->execute();
        $stmt->close();

        // Delete the user's LinksPages entirely (#219 — right to erasure).
        //
        // Everything else in this function updates a row in place rather
        // than deleting it, and changes only some columns — see the
        // docblock above for exactly which, and #247 for what is left
        // behind. Those rows are kept
        // because they are the historical record the anonymisation is meant
        // to leave behind. A LinksPage is different: the row itself IS the
        // person's personal data — a title that is often their name, a bio,
        // an avatar address, social links and the links they chose to
        // publish. Erasure has to remove that, and a page emptied of all of
        // it would be a meaningless shell with no reason to keep. (Merely
        // unpublishing it would stop it being shown — the renderer returns
        // nothing for an unpublished page — but would keep the personal data,
        // which is exactly what erasure must not do.) What deleting CANNOT
        // do: it does not remove copies already held by search engines or
        // in visitors' browser caches.
        //
        // TRADE-OFF this does not solve: deleting the row also frees its
        // slug immediately. From the instant this commits, anyone can
        // register the same lnks.page/<slug> and start receiving whatever
        // traffic the deleted person's old links still send it — social
        // media bios, business cards, anywhere they posted the address
        // outside this app, which nothing here can reach or update. That is
        // an impersonation risk, not merely a cosmetic one, and it is not
        // new to this fix — deleting a page by hand through the admin UI
        // already frees the slug the same way (see linkspage_manage.php's
        // own delete path). Tracked as a follow-up rather than solved here
        // (see #244) because holding a freed slug back for a cooling-off
        // period is a product decision — how long, whether it applies to
        // manual deletes too — not a one-line code change, and it does not
        // change what erasure itself must do.
        //
        // tblLinksPageItems rows for the deleted page(s) go with them
        // automatically through FK_item_page (ON DELETE CASCADE — see
        // web/_sql/schema/032_linkspage.sql). Any custom short domain that
        // had designated this page as its root fallback clears that
        // designation to NULL through FK_short_domain_linkspage (ON DELETE
        // SET NULL). The domain then behaves as if no page had ever been
        // chosen for it: a visit to the bare domain is redirected to the
        // site's fallback address (redirect.fallback_url), and an unknown
        // path gets the usual not-found page — unless the organisation has
        // set its own fallback address (orgFallbackURL), in which case the
        // visitor is redirected there. The foreign key does not block
        // this delete, and it does not delete the domain or change anything
        // else about it.
        $sql  = "DELETE FROM tblLinksPages WHERE userUID = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $userUID);
        $stmt->execute();
        $stmt->close();

        $db->commit();

        return true;
    }
    catch (\Throwable $e)
    {
        $db->rollback();
        error_log('[Go2My.Link] ERROR: g2ml_anonymiseUserData failed: ' . $e->getMessage());
        return false;
    }
}

// ============================================================================
// ⚙️ Process Data Deletion (Admin Action)
// ============================================================================

/**
 * Execute a pending data deletion request (after grace period).
 *
 * @param  int  $requestUID         The deletion request to process
 * @param  int  $processedByUserUID Admin user processing the request
 * @return bool                     true if processed successfully
 */
function g2ml_processDataDeletion(int $requestUID, int $processedByUserUID): bool
{
    $db = getDB();

    if ($db === null)
    {
        return false;
    }

    // Get request details
    $sql  = "SELECT userUID, requestType, status FROM tblDataDeletionRequests WHERE requestUID = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $requestUID);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($request === null || $request['status'] !== 'pending')
    {
        return false;
    }

    // Anonymise the user's data
    $success = g2ml_anonymiseUserData($request['userUID']);

    // Update request status
    if ($success) {
        $newStatus = 'completed';
    } else {
        $newStatus = 'rejected';
    }
    $sql  = "UPDATE tblDataDeletionRequests
             SET status = ?, processedByUserUID = ?, processedAt = NOW()
             WHERE requestUID = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('sii', $newStatus, $processedByUserUID, $requestUID);
    $stmt->execute();
    $stmt->close();

    return $success;
}

// ============================================================================
// 📜 Get Consent History
// ============================================================================

/**
 * Retrieve the full consent history for a user.
 *
 * @param  int   $userUID  The user to query
 * @return array           Array of consent records sorted by date desc
 */
function g2ml_getConsentHistory(int $userUID): array
{
    $db = getDB();

    if ($db === null)
    {
        return [];
    }

    $sql  = "SELECT consentUID, consentType, consentGiven, consentMethod,
                    jurisdiction, consentVersion, createdAt, expiresAt
             FROM tblConsentRecords WHERE userUID = ?
             ORDER BY createdAt DESC";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $userUID);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $result;
}

// ============================================================================
// 📋 Get User Data Requests
// ============================================================================

/**
 * Retrieve all data subject requests for a user (exports, deletions, etc.).
 *
 * @param  int   $userUID  The user to query
 * @return array           Array of request records sorted by date desc
 */
function g2ml_getUserDataRequests(int $userUID): array
{
    $db = getDB();

    if ($db === null)
    {
        return [];
    }

    $sql  = "SELECT requestUID, requestType, status, requestReason,
                    exportFilePath, exportExpiresAt, createdAt, processedAt
             FROM tblDataDeletionRequests WHERE userUID = ?
             ORDER BY createdAt DESC";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $userUID);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $result;
}
