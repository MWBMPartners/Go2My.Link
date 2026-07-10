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
 * 🔗 Go2My.Link — API v1 Handlers: /api/v1/urls CRUD + list (#39)
 * ============================================================================
 *
 * Implements:
 *   - POST   /api/v1/urls          g2ml_apiHandleUrlsCreate()  scope urls:write
 *   - GET    /api/v1/urls          g2ml_apiHandleUrlsList()    scope urls:read
 *   - GET    /api/v1/urls/{code}   g2ml_apiHandleUrlsGet()     scope urls:read
 *   - PUT    /api/v1/urls/{code}   g2ml_apiHandleUrlsUpdate()  scope urls:write
 *   - DELETE /api/v1/urls/{code}   g2ml_apiHandleUrlsDelete()  scope urls:delete
 *
 * (POST /api/v1/urls/bulk lives in urls_bulk.php — a distinct concern with
 * its own per-row partial-failure contract.)
 *
 * ----------------------------------------------------------------------------
 * BOLA / org-scoping (the #1 API risk — OWASP API1:2023 Broken Object Level
 * Authorization)
 * ----------------------------------------------------------------------------
 * EVERY query in this file is scoped by `orgHandle = ?` bound from the
 * VERIFIED key row (`$keyRow['orgHandle']`) — never from client input. A
 * short code that exists but belongs to a DIFFERENT org is indistinguishable
 * from a short code that does not exist at all: both paths throw the exact
 * same generic 404 (g2ml_apiHandleUrlsGet()/Update()/Delete()), so a caller
 * can never use response-shape/status differences to enumerate another
 * tenant's codes.
 *
 * The {code} route parameter is re-validated here (see
 * _g2ml_apiUrlsRequireRouteCode()) against the short-code charset even though
 * the front controller's g2ml_apiMatchParamRoute() already validated it —
 * defence in depth, and this handler must never trust an unvalidated value
 * even if it is ever invoked from a different call site in the future.
 *
 * @package    Go2My.Link
 * @subpackage API
 * @version    1.0.0
 * @since      v1.1.0 — Phase 7 (#39)
 *
 * 📖 References:
 *     - web/Go2My.Link/_functions/shorturl_create.php — createShortURL(), g2ml_attachTagsToShortURL(), g2ml_slugifyTag()
 *     - web/_sql/schema/020_shorturls_categories_tags.sql — tblShortURLs column names
 *     - web/_functions/security.php — g2ml_sanitiseURL(), g2ml_destinationHostIsAllowed()
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
// 🔧 Internal helpers (shared by every handler in this file)
// ============================================================================

/**
 * Extract and RE-validate the {code} route parameter for a urls/{code} route.
 *
 * @param  array $context  The handler context passed by the front controller.
 * @return string           The validated short code.
 *
 * @throws RuntimeException  If no route code is present, or it fails
 *                            re-validation — should never happen in normal
 *                            operation (the front controller only invokes a
 *                            urls/{code} handler when g2ml_apiMatchParamRoute()
 *                            already matched one), so this maps to the
 *                            front controller's generic 500 path rather than
 *                            a client-facing G2mlApiHandlerException.
 */
function _g2ml_apiUrlsRequireRouteCode(array $context): string
{
    if (!isset($context['routeParams']) || !is_array($context['routeParams']) || !isset($context['routeParams']['code']))
    {
        throw new RuntimeException('Route code parameter missing.');
    }

    $code = (string) $context['routeParams']['code'];

    if (preg_match('/^[A-Za-z0-9_-]{1,50}$/', $code) !== 1)
    {
        throw new RuntimeException('Route code parameter failed re-validation.');
    }

    return $code;
}

/**
 * Read the JSON request body out of the handler context, defaulting to an
 * empty array when absent (the front controller only populates 'body' for
 * POST/PUT, and an empty request body decodes to []).
 *
 * @param  array $context
 * @return array
 */
function _g2ml_apiUrlsRequestBody(array $context): array
{
    if (isset($context['body']) && is_array($context['body']))
    {
        return $context['body'];
    }

    return [];
}

/**
 * Format a tblShortURLs row into the /api/v1 urls response shape.
 *
 * @param  array $row  A row carrying at least the columns selected by
 *                       g2ml_apiHandleUrlsGet()/Update()/List().
 * @return array
 */
function _g2ml_apiUrlsFormatRow(array $row): array
{
    $formatted = [
        'short_code'      => $row['shortCode'],
        'destination_url' => $row['destinationURL'],
        'title'           => $row['title'],
        'is_active'       => ((int) $row['isActive'] === 1),
        'click_count'     => (int) $row['clickCount'],
        'created_at'      => $row['createdAt'],
        'updated_at'      => $row['updatedAt'],
    ];

    if (array_key_exists('startDate', $row))
    {
        $formatted['start_date'] = $row['startDate'];
    }

    if (array_key_exists('endDate', $row))
    {
        $formatted['end_date'] = $row['endDate'];
    }

    return $formatted;
}

/**
 * Validate an optional 'title' field from a request body.
 *
 * @param  array $body
 * @return string|null      The trimmed title, or null when absent/empty.
 *
 * @throws G2mlApiHandlerException  422 when present but not a string, or over
 *                                   the tblShortURLs.title VARCHAR(255) width.
 */
function _g2ml_apiUrlsValidateTitle(array $body): ?string
{
    if (!isset($body['title']))
    {
        return null;
    }

    if (!is_string($body['title']))
    {
        throw new G2mlApiHandlerException(422, 'title must be a string.', 'title');
    }

    $title = trim($body['title']);

    if (mb_strlen($title, 'UTF-8') > 255)
    {
        throw new G2mlApiHandlerException(422, 'title must be 255 characters or fewer.', 'title');
    }

    if ($title === '')
    {
        return null;
    }

    return $title;
}

/**
 * Validate an optional 'tags' field from a request body (string or array).
 *
 * @param  array $body
 * @return string|array|null
 *
 * @throws G2mlApiHandlerException  422 when present but neither a string nor an array.
 */
function _g2ml_apiUrlsValidateTags(array $body): string|array|null
{
    if (!isset($body['tags']))
    {
        return null;
    }

    if (!is_string($body['tags']) && !is_array($body['tags']))
    {
        throw new G2mlApiHandlerException(422, 'tags must be a string or an array of strings.', 'tags');
    }

    return $body['tags'];
}

// ============================================================================
// ✨ POST /api/v1/urls — create (scope urls:write)
// ============================================================================

/**
 * Handle POST /api/v1/urls.
 *
 * Reuses createShortURL() end to end (alias/TOCTOU handling, SSRF/scheme
 * guards, tag attach) — this handler only validates the request shape and
 * binds the verified key's own org/user/key identity into the create, NEVER
 * a client-supplied orgHandle/userUID.
 *
 * @param  array $keyRow   The verified API key row.
 * @param  array $context  Request context; 'body' carries the decoded JSON.
 * @return array            {short_code, short_url, destination_url}
 *
 * @throws G2mlApiHandlerException  422 on validation failure, 409 on alias collision.
 */
function g2ml_apiHandleUrlsCreate(array $keyRow, array $context): array
{
    $body = _g2ml_apiUrlsRequestBody($context);

    if (!isset($body['destination_url']) || !is_string($body['destination_url']) || trim($body['destination_url']) === '')
    {
        throw new G2mlApiHandlerException(422, 'destination_url is required and must be a non-empty string.', 'destination_url');
    }

    $destinationURL = trim($body['destination_url']);

    $customCode = null;

    if (isset($body['custom_code']))
    {
        if (!is_string($body['custom_code']))
        {
            throw new G2mlApiHandlerException(422, 'custom_code must be a string.', 'custom_code');
        }

        $customCode = trim($body['custom_code']);
    }

    $title = _g2ml_apiUrlsValidateTitle($body);
    $tags  = _g2ml_apiUrlsValidateTags($body);

    $orgHandle = (string) $keyRow['orgHandle'];
    $userUID   = (int) $keyRow['userUID'];
    $apiKeyUID = (int) $keyRow['apiKeyUID'];

    $createOptions = [
        'orgHandle'           => $orgHandle,
        'userUID'             => $userUID,
        'title'               => $title,
        'createdVia'          => 'api',
        'createdViaAPIKeyUID' => $apiKeyUID,
    ];

    if ($customCode !== null && $customCode !== '')
    {
        $createOptions['customCode'] = $customCode;
    }

    if ($tags !== null)
    {
        $createOptions['tags'] = $tags;
    }

    $created = createShortURL($destinationURL, $createOptions);

    if ($created['success'] !== true)
    {
        $errorMessage = 'Failed to create the short URL.';

        if (isset($created['error']) && is_string($created['error']))
        {
            $errorMessage = $created['error'];
        }

        if (str_contains($errorMessage, 'already taken'))
        {
            throw new G2mlApiHandlerException(409, $errorMessage, 'custom_code');
        }

        throw new G2mlApiHandlerException(422, $errorMessage, 'destination_url');
    }

    // Re-read the canonically-stored destination (createShortURL() sanitises
    // the URL before storing it — filter_var(FILTER_SANITIZE_URL) can alter
    // it — rather than assuming the client's raw input is what was persisted).
    $storedRow = dbSelectOne(
        'SELECT destinationURL FROM tblShortURLs WHERE shortCode = ? AND orgHandle = ? LIMIT 1',
        'ss',
        [$created['shortCode'], $orgHandle]
    );

    if ($storedRow !== null && $storedRow !== false)
    {
        $storedDestination = $storedRow['destinationURL'];
    }
    else
    {
        $storedDestination = $destinationURL;
    }

    return [
        'short_code'      => $created['shortCode'],
        'short_url'       => $created['shortURL'],
        'destination_url' => $storedDestination,
    ];
}

// ============================================================================
// 📋 GET /api/v1/urls — list (scope urls:read), cursor-paginated on urlUID
// ============================================================================

/**
 * Handle GET /api/v1/urls.
 *
 * Org-scoped, cursor-paginated on urlUID (?limit=, capped at 100; ?after=,
 * the previous page's last urlUID). Optional ?active=0|1 and ?tag= filters.
 *
 * @param  array $keyRow   The verified API key row.
 * @param  array $context  Request context (unused — list reads $_GET directly,
 *                          mirroring the GET-only, body-free nature of a list
 *                          endpoint; every other handler in this file reads its
 *                          input from $context['body'] or $context['routeParams']).
 * @return array            {items: array, nextCursor: string|null}
 *
 * @throws G2mlApiHandlerException  422 on a malformed after/active/tag parameter.
 */
function g2ml_apiHandleUrlsList(array $keyRow, array $context): array
{
    $orgHandle = (string) $keyRow['orgHandle'];

    $limit = 50;

    if (isset($_GET['limit']) && is_string($_GET['limit']) && $_GET['limit'] !== '')
    {
        if (preg_match('/^[0-9]+$/', $_GET['limit']) !== 1)
        {
            throw new G2mlApiHandlerException(422, 'limit must be a positive integer.', 'limit');
        }

        $requestedLimit = (int) $_GET['limit'];

        if ($requestedLimit > 0)
        {
            $limit = $requestedLimit;
        }
    }

    if ($limit > 100)
    {
        $limit = 100;
    }

    $afterCursor = 0;

    if (isset($_GET['after']) && is_string($_GET['after']) && $_GET['after'] !== '')
    {
        if (preg_match('/^[0-9]+$/', $_GET['after']) !== 1)
        {
            throw new G2mlApiHandlerException(422, 'after must be a positive integer cursor.', 'after');
        }

        $afterCursor = (int) $_GET['after'];
    }

    $whereConditions = ['s.orgHandle = ?', 's.urlUID > ?'];
    $whereTypes      = 'si';
    $whereParams     = [$orgHandle, $afterCursor];

    if (isset($_GET['active']) && is_string($_GET['active']) && $_GET['active'] !== '')
    {
        if ($_GET['active'] !== '0' && $_GET['active'] !== '1')
        {
            throw new G2mlApiHandlerException(422, 'active must be 0 or 1.', 'active');
        }

        $whereConditions[] = 's.isActive = ?';
        $whereTypes       .= 'i';
        $whereParams[]     = (int) $_GET['active'];
    }

    if (isset($_GET['tag']) && is_string($_GET['tag']) && trim($_GET['tag']) !== '')
    {
        $tagFilterSlug = g2ml_slugifyTag($_GET['tag']);

        if ($tagFilterSlug === '')
        {
            // No usable slug from the supplied tag — guaranteed-empty result.
            return [
                'items'      => [],
                'nextCursor' => null,
            ];
        }

        $whereConditions[] = 's.urlUID IN ('
            . 'SELECT st.urlUID FROM tblShortURLTags st '
            . 'INNER JOIN tblTags t ON st.tagUID = t.tagUID '
            . 'WHERE t.tagSlug = ? AND t.orgHandle = ?)';
        $whereTypes       .= 'ss';
        $whereParams[]     = $tagFilterSlug;
        $whereParams[]     = $orgHandle;
    }

    $whereSQL = implode(' AND ', $whereConditions);

    // Fetch one extra row so we know whether a next page exists, without a
    // separate COUNT(*) query.
    $fetchLimit   = $limit + 1;
    $listParams   = $whereParams;
    $listTypes    = $whereTypes . 'i';
    $listParams[] = $fetchLimit;

    $rows = dbSelect(
        'SELECT s.urlUID, s.shortCode, s.destinationURL, s.title, s.isActive, '
        . 's.clickCount, s.createdAt, s.updatedAt '
        . 'FROM tblShortURLs s '
        . 'WHERE ' . $whereSQL . ' '
        . 'ORDER BY s.urlUID ASC '
        . 'LIMIT ?',
        $listTypes,
        $listParams
    );

    if ($rows === false)
    {
        $rows = [];
    }

    $nextCursor = null;

    if (count($rows) > $limit)
    {
        $rows       = array_slice($rows, 0, $limit);
        $lastRow    = $rows[count($rows) - 1];
        $nextCursor = (string) $lastRow['urlUID'];
    }

    $items = [];

    foreach ($rows as $row)
    {
        $formatted              = _g2ml_apiUrlsFormatRow($row);
        $formatted['url_uid']   = (int) $row['urlUID'];
        $items[]                = $formatted;
    }

    return [
        'items'      => $items,
        'nextCursor' => $nextCursor,
    ];
}

// ============================================================================
// 🔍 GET /api/v1/urls/{code} — read one (scope urls:read)
// ============================================================================

/**
 * Handle GET /api/v1/urls/{code}.
 *
 * @param  array $keyRow
 * @param  array $context  Carries routeParams.code (the requested short code).
 * @return array
 *
 * @throws G2mlApiHandlerException  404 when the code does not exist IN THIS
 *                                   KEY'S OWN ORG — a code that belongs to a
 *                                   different org is indistinguishable from
 *                                   one that does not exist at all (BOLA guard).
 */
function g2ml_apiHandleUrlsGet(array $keyRow, array $context): array
{
    $code      = _g2ml_apiUrlsRequireRouteCode($context);
    $orgHandle = (string) $keyRow['orgHandle'];

    $row = dbSelectOne(
        'SELECT s.urlUID, s.shortCode, s.destinationURL, s.title, s.isActive, '
        . 's.clickCount, s.startDate, s.endDate, s.createdAt, s.updatedAt '
        . 'FROM tblShortURLs s '
        . 'WHERE s.shortCode = ? AND s.orgHandle = ? '
        . 'LIMIT 1',
        'ss',
        [$code, $orgHandle]
    );

    if ($row === null || $row === false)
    {
        throw new G2mlApiHandlerException(404, 'No such short URL in this account.');
    }

    return _g2ml_apiUrlsFormatRow($row);
}

// ============================================================================
// ✏️ PUT /api/v1/urls/{code} — update destination_url/title/tags (scope urls:write)
// ============================================================================

/**
 * Handle PUT /api/v1/urls/{code}.
 *
 * Re-applies the SAME destination validation as create — g2ml_sanitiseURL()
 * then g2ml_destinationHostIsAllowed() (SSRF/scheme guard) — before the new
 * destination is ever bound into an UPDATE. Org-scoped throughout: the
 * existence check, the UPDATE's WHERE clause, and the reload all bind
 * orgHandle from the verified key row.
 *
 * @param  array $keyRow
 * @param  array $context  Carries routeParams.code and the decoded body.
 * @return array
 *
 * @throws G2mlApiHandlerException  404 (not in this org), 422 (validation).
 */
function g2ml_apiHandleUrlsUpdate(array $keyRow, array $context): array
{
    $code      = _g2ml_apiUrlsRequireRouteCode($context);
    $orgHandle = (string) $keyRow['orgHandle'];
    $body      = _g2ml_apiUrlsRequestBody($context);

    $existing = dbSelectOne(
        'SELECT urlUID FROM tblShortURLs WHERE shortCode = ? AND orgHandle = ? LIMIT 1',
        'ss',
        [$code, $orgHandle]
    );

    if ($existing === null || $existing === false)
    {
        throw new G2mlApiHandlerException(404, 'No such short URL in this account.');
    }

    $setClauses = [];
    $setTypes   = '';
    $setParams  = [];

    if (isset($body['destination_url']))
    {
        if (!is_string($body['destination_url']) || trim($body['destination_url']) === '')
        {
            throw new G2mlApiHandlerException(422, 'destination_url must be a non-empty string.', 'destination_url');
        }

        $sanitisedDestination = g2ml_sanitiseURL(trim($body['destination_url']));

        if ($sanitisedDestination === false)
        {
            throw new G2mlApiHandlerException(422, 'Invalid URL format. Please supply a valid HTTP or HTTPS URL.', 'destination_url');
        }

        if (g2ml_destinationHostIsAllowed($sanitisedDestination) === false)
        {
            throw new G2mlApiHandlerException(422, 'That destination is not permitted.', 'destination_url');
        }

        $setClauses[] = 'destinationURL = ?';
        $setTypes    .= 's';
        $setParams[]  = $sanitisedDestination;
    }

    if (isset($body['title']))
    {
        $titleValue = _g2ml_apiUrlsValidateTitle($body);

        if ($titleValue === null)
        {
            $setClauses[] = 'title = NULL';
        }
        else
        {
            $setClauses[] = 'title = ?';
            $setTypes    .= 's';
            $setParams[]  = $titleValue;
        }
    }

    $tagsToApply = _g2ml_apiUrlsValidateTags($body);

    if (count($setClauses) === 0 && $tagsToApply === null)
    {
        throw new G2mlApiHandlerException(422, 'At least one of destination_url, title, or tags must be supplied.');
    }

    if (count($setClauses) > 0)
    {
        $updateSQL = 'UPDATE tblShortURLs SET ' . implode(', ', $setClauses)
            . ' WHERE shortCode = ? AND orgHandle = ?';

        $setTypes    .= 'ss';
        $setParams[]  = $code;
        $setParams[]  = $orgHandle;

        $updateResult = dbUpdate($updateSQL, $setTypes, $setParams);

        if ($updateResult === false)
        {
            throw new RuntimeException('Failed to update the short URL.');
        }
    }

    if ($tagsToApply !== null)
    {
        // Additive and fully defensive, mirroring createShortURL()'s own tag
        // handling — a tag failure must never fail the whole update.
        try
        {
            g2ml_attachTagsToShortURL((int) $existing['urlUID'], $orgHandle, $tagsToApply);
        }
        catch (Throwable $tagAttachError)
        {
            error_log('[Go2My.Link] WARNING: API tag attach failed for URL ' . $existing['urlUID'] . ': ' . $tagAttachError->getMessage());
        }
    }

    $updatedRow = dbSelectOne(
        'SELECT s.urlUID, s.shortCode, s.destinationURL, s.title, s.isActive, '
        . 's.clickCount, s.startDate, s.endDate, s.createdAt, s.updatedAt '
        . 'FROM tblShortURLs s '
        . 'WHERE s.shortCode = ? AND s.orgHandle = ? '
        . 'LIMIT 1',
        'ss',
        [$code, $orgHandle]
    );

    if ($updatedRow === null || $updatedRow === false)
    {
        throw new RuntimeException('Updated short URL row could not be reloaded.');
    }

    return _g2ml_apiUrlsFormatRow($updatedRow);
}

// ============================================================================
// 🗑️ DELETE /api/v1/urls/{code} — soft delete (scope urls:delete)
// ============================================================================

/**
 * Handle DELETE /api/v1/urls/{code}.
 *
 * Soft-deletes (isActive = 0), org-scoped. Idempotent: deleting an
 * already-inactive link in THIS org succeeds again rather than 404ing, but a
 * code that does not exist in this org (including one that belongs to a
 * different org) still 404s — the two cases are told apart by a scoped
 * existence check after a zero-row UPDATE, never by trusting affected_rows
 * alone (which cannot distinguish "not found" from "already inactive").
 *
 * @param  array $keyRow
 * @param  array $context  Carries routeParams.code.
 * @return array             {short_code, is_active: false}
 *
 * @throws G2mlApiHandlerException  404 when the code does not exist in this org.
 */
function g2ml_apiHandleUrlsDelete(array $keyRow, array $context): array
{
    $code      = _g2ml_apiUrlsRequireRouteCode($context);
    $orgHandle = (string) $keyRow['orgHandle'];

    $affectedRows = dbUpdate(
        'UPDATE tblShortURLs SET isActive = 0 WHERE shortCode = ? AND orgHandle = ?',
        'ss',
        [$code, $orgHandle]
    );

    if ($affectedRows === false)
    {
        throw new RuntimeException('Failed to deactivate the short URL.');
    }

    if ($affectedRows === 0)
    {
        $existing = dbSelectOne(
            'SELECT urlUID FROM tblShortURLs WHERE shortCode = ? AND orgHandle = ? LIMIT 1',
            'ss',
            [$code, $orgHandle]
        );

        if ($existing === null || $existing === false)
        {
            throw new G2mlApiHandlerException(404, 'No such short URL in this account.');
        }

        // Existed, in this org, already inactive — fall through as a
        // successful (idempotent) no-op rather than a confusing 404.
    }

    return [
        'short_code' => $code,
        'is_active'  => false,
    ];
}
