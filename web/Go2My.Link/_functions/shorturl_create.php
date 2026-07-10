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
 * ✨ Go2My.Link — Short URL Creation Logic (Component A)
 * ============================================================================
 *
 * Provides functions for creating short URLs, rate limiting anonymous
 * creation, and verifying CAPTCHA responses.
 *
 * Functions:
 *   - createShortURL()           — Create a new short URL
 *   - checkAnonymousRateLimit()  — Check IP-based rate limits
 *   - getDefaultShortDomain()    — Get the default short domain for an org
 *   - verifyCaptcha()            — Verify Turnstile/reCAPTCHA response
 *
 * Dependencies: db_query.php, settings.php, security.php, activity_logger.php
 *               (all loaded via page_init.php)
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.4.0
 * @since      Phase 3
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
// 🔤 Custom short-code (alias) constraints — shared in one place (FG-001)
// ============================================================================
// The shortCode column is VARCHAR(50). A user-chosen alias is restricted to
// letters, digits, hyphen, and underscore, with a minimum of 3 characters so
// it cannot collide with single/double-character reserved handler segments and
// reads as a deliberate choice. The regex is anchored and length-bounded so it
// can never exceed the column width.
//
// 📖 Reference: web/_sql/schema/020_shorturls_categories_tags.sql (shortCode VARCHAR(50))
// 📖 Reference: https://www.php.net/manual/en/function.preg-match.php
// ============================================================================
if (!defined('G2ML_CUSTOM_CODE_PATTERN'))
{
    define('G2ML_CUSTOM_CODE_PATTERN', '/^[A-Za-z0-9_-]{3,50}$/');
}

// ============================================================================
// 🚫 g2ml_isReservedShortCode — block codes that would shadow Component B routes
// ============================================================================
// Component B (g2my.link) serves a small set of handler/reserved paths directly
// (robots.txt, favicon.ico, the validating/expired/404 interstitials, the API
// and admin trees, the installer, and the www host label). A custom alias that
// matches one of these would shadow the real handler when resolved, so we
// reject it at creation time. Comparison is case-insensitive.
//
// To extend the block list, add an entry to $reserved below — it is the single
// source of truth. Entries are stored lowercase; the incoming code is
// lowercased before comparison.
//
// 📖 Reference: web/G2My.Link/public_html/robots.php, favicon.php, 404.php,
//               expired.php, validating.php (handler routes)
//
// 'bulk' is reserved as of #39: the public API's parameterised route matcher
// (g2ml_apiMatchParamRoute() in api_response.php) treats any two-segment
// "urls/<code>" path as a short-code lookup for GET/PUT/DELETE, while
// "POST urls/bulk" is a literal, separate endpoint (bulk create). Reserving
// the word prevents a code named "bulk" from ever existing, so there is no
// ambiguity about what a client requesting /api/v1/urls/bulk means.
//
// @param  string $code  The candidate custom short code.
// @return bool           True when the code is reserved (must be rejected).
// ============================================================================
function g2ml_isReservedShortCode(string $code): bool
{
    // Single source of truth — keep lowercase, extend as new routes are added.
    $reserved = [
        'robots',
        'robots.txt',
        'favicon',
        'favicon.ico',
        'index',
        'sitemap',
        'sitemap.xml',
        'validating',
        'expired',
        '404',
        'api',
        'admin',
        'install',
        'www',
        'bulk',
    ];

    $normalised = strtolower(trim($code));

    return in_array($normalised, $reserved, true);
}

// ============================================================================
// 🏷️ Tag constants — shared bounds for the link-tagging feature (FG-002)
// ============================================================================
// tblTags.tagName and tblTags.tagSlug are both VARCHAR(100); we never emit a
// value that could exceed that column width. A per-link cap keeps a single
// create from fanning out into an unbounded number of tag rows/junction rows.
//
// 📖 Reference: web/_sql/schema/020_shorturls_categories_tags.sql (tblTags)
// ============================================================================
if (!defined('G2ML_TAG_MAX_LENGTH'))
{
    define('G2ML_TAG_MAX_LENGTH', 100);
}

if (!defined('G2ML_MAX_TAGS_PER_LINK'))
{
    define('G2ML_MAX_TAGS_PER_LINK', 10);
}

// ============================================================================
// 🏷️ g2ml_slugifyTag — derive a URL-safe slug for a tag (FG-002)
// ============================================================================
// Slug rules: lowercase; every run of characters that is not an ASCII letter
// or digit collapses to a single hyphen; leading/trailing hyphens are trimmed;
// the result is bounded to the tblTags.tagSlug VARCHAR(100) column. A tag with
// no alphanumeric content (e.g. "!!!") yields an empty slug and is skipped by
// the caller. Pure function — no database access.
//
// 📖 Reference: https://www.php.net/manual/en/function.preg-replace.php
//
// @param  string $tag  The raw tag text.
// @return string       The derived slug (may be '' when nothing usable remains).
// ============================================================================
function g2ml_slugifyTag(string $tag): string
{
    $lower = mb_strtolower(trim($tag), 'UTF-8');
    $slug  = preg_replace('/[^a-z0-9]+/', '-', $lower);

    if ($slug === null)
    {
        return '';
    }

    $slug = trim($slug, '-');

    if (strlen($slug) > G2ML_TAG_MAX_LENGTH)
    {
        $slug = substr($slug, 0, G2ML_TAG_MAX_LENGTH);
        $slug = trim($slug, '-');
    }

    return $slug;
}

// ============================================================================
// 🏷️ g2ml_normaliseTags — parse and normalise a tag input (FG-002)
// ============================================================================
// Accepts either a comma-separated string or an array of raw tag strings and
// returns an ordered list of ['name' => ..., 'slug' => ...] entries. Empty
// tags and tags that slugify to nothing are skipped; entries are de-duplicated
// by slug (so "Marketing" and "marketing" collapse to one); the list is capped
// at $maxTags. Display names are trimmed and bounded to VARCHAR(100). Pure
// function — no database access.
//
// @param  string|array $tags     Comma-separated string OR array of raw tags.
// @param  int          $maxTags  Maximum number of tags to keep (0 = no cap).
// @return array<int, array{name: string, slug: string}>
// ============================================================================
function g2ml_normaliseTags(string|array $tags, int $maxTags = G2ML_MAX_TAGS_PER_LINK): array
{
    if (is_string($tags))
    {
        $rawList = explode(',', $tags);
    }
    else
    {
        $rawList = $tags;
    }

    $normalised = [];
    $seenSlugs  = [];

    foreach ($rawList as $rawTag)
    {
        if ($maxTags > 0 && count($normalised) >= $maxTags)
        {
            break;
        }

        if (!is_string($rawTag))
        {
            continue;
        }

        $name = trim($rawTag);

        if ($name === '')
        {
            continue;
        }

        $slug = g2ml_slugifyTag($name);

        if ($slug === '')
        {
            continue;
        }

        if (isset($seenSlugs[$slug]))
        {
            continue;
        }

        if (mb_strlen($name, 'UTF-8') > G2ML_TAG_MAX_LENGTH)
        {
            $name = mb_substr($name, 0, G2ML_TAG_MAX_LENGTH, 'UTF-8');
        }

        $seenSlugs[$slug] = true;
        $normalised[]     = [
            'name' => $name,
            'slug' => $slug,
        ];
    }

    return $normalised;
}

// ============================================================================
// 🏷️ g2ml_findOrCreateTag — resolve a tag's UID within an org (FG-002)
// ============================================================================
// Find-or-create keyed on UQ_tag_org (tagSlug, orgHandle). The common reuse
// path is a single prepared SELECT that consumes no auto-increment id. When the
// tag is absent we INSERT; if a concurrent create wins the race and trips the
// unique key (MySQL errno 1062), we re-SELECT so we still return the winning
// row's tagUID. Every statement is prepared — no SQL is built from user input.
//
// 📖 Reference: web/_functions/db_query.php — dbSelectOne(), dbInsert(), dbLastErrno()
//
// @param  string $tagName    Display name (already trimmed/bounded).
// @param  string $tagSlug    URL-safe slug (already derived, non-empty).
// @param  string $orgHandle  Owning organisation handle.
// @return int                The tag's UID, or 0 when it could not be resolved.
// ============================================================================
function g2ml_findOrCreateTag(string $tagName, string $tagSlug, string $orgHandle): int
{
    $existing = dbSelectOne(
        "SELECT tagUID FROM tblTags WHERE tagSlug = ? AND orgHandle = ? LIMIT 1",
        'ss',
        [$tagSlug, $orgHandle]
    );

    if ($existing !== null && $existing !== false && isset($existing['tagUID']))
    {
        return (int) $existing['tagUID'];
    }

    $inserted = dbInsert(
        "INSERT INTO tblTags (tagName, tagSlug, orgHandle, createdAt) VALUES (?, ?, ?, NOW())",
        'sss',
        [$tagName, $tagSlug, $orgHandle]
    );

    if (is_int($inserted) && $inserted > 0)
    {
        return $inserted;
    }

    // The insert yielded no id. If it failed on the unique key, another request
    // created the same tag concurrently — re-select to obtain its id.
    if ($inserted === false && dbLastErrno() === 1062)
    {
        $raced = dbSelectOne(
            "SELECT tagUID FROM tblTags WHERE tagSlug = ? AND orgHandle = ? LIMIT 1",
            'ss',
            [$tagSlug, $orgHandle]
        );

        if ($raced !== null && $raced !== false && isset($raced['tagUID']))
        {
            return (int) $raced['tagUID'];
        }
    }

    return 0;
}

// ============================================================================
// 🏷️ g2ml_attachTagsToShortURL — link tags to a created short URL (FG-002)
// ============================================================================
// Normalises the supplied tags, finds-or-creates each within the org, and links
// it to the short URL via tblShortURLTags using INSERT IGNORE (so re-adding an
// already-linked tag is a harmless no-op rather than a duplicate-key error).
//
// Robustness: tags are purely additive. This function is called AFTER the short
// URL row is committed and is fully defensive — every per-tag failure is logged
// and swallowed so it can never roll back or break the already-created short
// URL. Returns the number of tags successfully linked.
//
// 📖 Reference: web/_sql/schema/020_shorturls_categories_tags.sql (tblShortURLTags)
//
// @param  int          $urlUID     The created short URL's urlUID (insert_id).
// @param  string       $orgHandle  Owning organisation handle.
// @param  string|array $tags       Comma-separated string OR array of raw tags.
// @return int                      Count of tags successfully linked.
// ============================================================================
function g2ml_attachTagsToShortURL(int $urlUID, string $orgHandle, string|array $tags): int
{
    if ($urlUID <= 0)
    {
        return 0;
    }

    $normalised = g2ml_normaliseTags($tags);

    if (count($normalised) === 0)
    {
        return 0;
    }

    $linkedCount = 0;

    foreach ($normalised as $tag)
    {
        try
        {
            $tagUID = g2ml_findOrCreateTag($tag['name'], $tag['slug'], $orgHandle);

            if ($tagUID <= 0)
            {
                error_log('[Go2My.Link] WARNING: could not resolve tag slug "' . $tag['slug'] . '" for org: ' . $orgHandle . ' — skipping.');
                continue;
            }

            $junctionResult = dbInsert(
                "INSERT IGNORE INTO tblShortURLTags (urlUID, tagUID) VALUES (?, ?)",
                'ii',
                [$urlUID, $tagUID]
            );

            if ($junctionResult === false)
            {
                error_log('[Go2My.Link] WARNING: failed to link tag ' . $tagUID . ' to URL ' . $urlUID . ' — skipping.');
                continue;
            }

            $linkedCount = $linkedCount + 1;
        }
        catch (Throwable $tagError)
        {
            error_log('[Go2My.Link] WARNING: tag attach threw for URL ' . $urlUID . ': ' . $tagError->getMessage());
            continue;
        }
    }

    return $linkedCount;
}

// ============================================================================
// ✨ createShortURL — Create a new short URL
// ============================================================================
// Creates a new short URL record in tblShortURLs. Generates a unique random
// short code via the sp_generateShortCode stored procedure, validates the
// destination URL, and returns the complete short URL.
//
// Anonymous users have restrictions: no custom suffix, no date ranges,
// no notes, no categories.
//
// 📖 Reference: web/_sql/procedures/sp_generateShortCode.sql
// 📖 Reference: https://www.php.net/manual/en/function.filter-var.php
//
// @param  string $longURL   The destination URL to shorten
// @param  array  $options   Optional overrides:
//   - orgHandle:   (string) Org handle (default: '[default]')
//   - codeLength:  (int) Short code length (default: from settings)
//   - userUID:     (int|null) Creating user's UID (null for anonymous)
//   - title:       (string|null) Link title
//   - categoryID:  (string|null) Category ID
//   - startDate:   (string|null) Start date (ISO 8601 format)
//   - endDate:     (string|null) End date (ISO 8601 format)
//   - notes:       (string|null) Internal notes
//   - customCode:  (string|null) Authenticated-only custom alias (FG-001). When
//                  non-empty it is validated (format + reserved word) and used
//                  verbatim as the short code; a duplicate alias fails with a
//                  clear error and is NEVER silently regenerated. When empty or
//                  absent, a random code is generated as before.
//   - tags:        (string|array|null) Authenticated-only optional tags (FG-002).
//                  A comma-separated string or an array of raw tag strings. Each
//                  is normalised, found-or-created per org, and linked to the new
//                  short URL AFTER it is committed. Tag handling is additive and
//                  fully defensive: a tag failure never rolls back or breaks the
//                  created short URL. Empty/absent = no tags (public path default).
//   - createdVia:  (string|null) Provenance of the create (#39 — public API).
//                  Must be one of the tblShortURLs.createdVia ENUM values
//                  ('web', 'api', 'import', 'admin', 'cuercode'); any other
//                  value (including absent/null) falls back to 'web', which is
//                  the column's own DEFAULT and preserves behaviour for every
//                  caller that predates this option (public create, dashboard
//                  create, existing tests).
//   - createdViaAPIKeyUID: (int|null) FK to tblAPIKeys.apiKeyUID — which API
//                  key minted this code (#39). Only meaningful alongside
//                  createdVia='api'/'cuercode'; null (the column's default)
//                  when absent, exactly like every pre-#39 caller.
//   - qrCodeExternalID:   (int|null) CueRCode's own numeric QR id (#145).
//                  Bound into the existing tblShortURLs.qrCodeExternalID
//                  column; null (the column's default) when absent, which is
//                  every caller before #145.
//   - qrCodeExternalUUID: (string|null) CueRCode's QR UUID (#145). Bound into
//                  the existing tblShortURLs.qrCodeExternalUUID column, which
//                  carries a UNIQUE constraint (UQ_url_qr_uuid). A duplicate
//                  value does NOT fall into the shortCode collision retry —
//                  see Steps 4/5 below — it fails the create outright with
//                  $result['qrUuidTaken'] === true so the API layer can map it
//                  to a distinct HTTP 409. An empty string is treated the same
//                  as absent (null). FORMAT validation (36-char UUID shape) is
//                  the API handler's job — that is the boundary that sees
//                  untrusted client input.
//   - qrCodeLinkedAt:     (string|null) DATETIME string for the existing
//                  tblShortURLs.qrCodeLinkedAt column (#145); null (the
//                  column's default) when absent.
// @return array  ['success' => bool, 'shortCode' => ?string,
//                 'shortURL' => ?string, 'error' => ?string,
//                 'qrUuidTaken' => ?bool] — qrUuidTaken is only present and
//                 true when the failure was a qrCodeExternalUUID collision
//                 (#145); absent for every other outcome.
// ============================================================================
function createShortURL(string $longURL, array $options = []): array
{
    // ========================================================================
    // 📋 Step 1: Validate and sanitise the URL
    // ========================================================================
    // 📖 Reference: web/_functions/security.php — g2ml_sanitiseURL()
    $longURL = trim($longURL);

    if ($longURL === '')
    {
        return [
            'success'   => false,
            'shortCode' => null,
            'shortURL'  => null,
            'error'     => 'URL is required.',
        ];
    }

    // Validate URL format (must be http:// or https://)
    $sanitisedURL = g2ml_sanitiseURL($longURL);

    if ($sanitisedURL === false)
    {
        return [
            'success'   => false,
            'shortCode' => null,
            'shortURL'  => null,
            'error'     => 'Invalid URL format. Please enter a valid HTTP or HTTPS URL.',
        ];
    }

    // ========================================================================
    // 🛡️ Step 2: Block self-referencing URLs
    // ========================================================================
    // Prevent users from shortening URLs that point to our own short domains,
    // which would create redirect loops.
    //
    // 📖 Reference: https://www.php.net/manual/en/function.parse-url.php
    // ========================================================================
    $parsed = parse_url($sanitisedURL);
    $urlHost = strtolower($parsed['host'] ?? '');

    // Strip www. prefix for comparison
    if (strpos($urlHost, 'www.') === 0)
    {
        $urlHost = substr($urlHost, 4);
    }

    // Built-in blocked domains
    $blockedDomains = ['g2my.link', 'go2my.link', 'lnks.page'];

    // Also check registered custom short domains from the database
    $customDomains = dbSelect(
        "SELECT shortDomain FROM tblOrgShortDomains WHERE isActive = 1",
        '',
        []
    );

    if ($customDomains !== false && is_array($customDomains))
    {
        foreach ($customDomains as $row)
        {
            $blockedDomains[] = strtolower($row['shortDomain']);
        }
    }

    if (in_array($urlHost, $blockedDomains, true))
    {
        return [
            'success'   => false,
            'shortCode' => null,
            'shortURL'  => null,
            'error'     => 'Cannot shorten URLs that point to this service.',
        ];
    }

    // ========================================================================
    // 🛡️ Step 2b: Block internal / metadata / userinfo destinations (SSRF)
    // ========================================================================
    // F-005 / #100: a short URL whose destination resolves to a loopback,
    // link-local, cloud-metadata, or (by default) private host turns the
    // redirect engine into an SSRF pivot. Reject creation up front so no row
    // is ever written. The shared guard also rejects userinfo (user:pass@)
    // URLs. Private destinations stay blocked unless the operator opts in via
    // redirect.allow_private_destinations.
    //
    // 📖 Reference: web/_functions/security.php — g2ml_destinationHostIsAllowed()
    // ========================================================================
    if (g2ml_destinationHostIsAllowed($sanitisedURL) === false)
    {
        return [
            'success'   => false,
            'shortCode' => null,
            'shortURL'  => null,
            'error'     => 'That destination is not permitted. Please enter a public HTTP or HTTPS URL.',
        ];
    }

    // ========================================================================
    // 📋 Step 3: Extract options
    // ========================================================================
    $orgHandle  = $options['orgHandle'] ?? '[default]';
    $userUID    = $options['userUID'] ?? null;
    $title      = $options['title'] ?? null;
    $categoryID = $options['categoryID'] ?? null;
    $startDate  = $options['startDate'] ?? null;
    $endDate    = $options['endDate'] ?? null;
    $notes      = $options['notes'] ?? null;

    // isActive (SEC-RECHECK-01 hardening): a caller may create a link inactive.
    // Default active to preserve behaviour for every existing caller. The value
    // is coerced to 0/1 and BOUND into the INSERT (never a post-insert UPDATE
    // scoped only by shortCode, which — since UQ_shortcode_org is per-org and
    // FG-001 lets a user mint an alias duplicating another org's code — would
    // deactivate a different tenant's live link).
    $isActiveOption = $options['isActive'] ?? true;
    if ($isActiveOption)
    {
        $isActiveValue = 1;
    }
    else
    {
        $isActiveValue = 0;
    }

    // ========================================================================
    // 🔗 Step 3a: createdVia / createdViaAPIKeyUID (#39 — public API provenance)
    // ========================================================================
    // Validated against the tblShortURLs.createdVia ENUM here in PHP (rather
    // than letting an unrecognised value reach the INSERT) so a caller typo
    // never trips a MySQL data-truncation warning/error — it silently falls
    // back to 'web', the column's own default, which is exactly what every
    // caller before #39 got implicitly.
    //
    // 📖 Reference: web/_sql/schema/020_shorturls_categories_tags.sql (createdVia ENUM)
    // ========================================================================
    $createdViaAllowed = ['web', 'api', 'import', 'admin', 'cuercode'];
    $createdViaOption  = $options['createdVia'] ?? 'web';

    if (!is_string($createdViaOption) || !in_array($createdViaOption, $createdViaAllowed, true))
    {
        $createdViaOption = 'web';
    }

    $createdViaAPIKeyUIDOption = $options['createdViaAPIKeyUID'] ?? null;

    if ($createdViaAPIKeyUIDOption !== null)
    {
        $createdViaAPIKeyUIDOption = (int) $createdViaAPIKeyUIDOption;
    }

    // ========================================================================
    // 🔗 Step 3a-2: qrCodeExternalID / qrCodeExternalUUID / qrCodeLinkedAt
    //               (#145 — CueRCode dynamic-QR wiring)
    // ========================================================================
    // These three existing tblShortURLs columns are bound whenever the caller
    // supplies them (the API layer only does so for a verified qr:link-scoped
    // create); every other caller gets NULL, exactly the column defaults.
    // ========================================================================
    $qrCodeExternalIDOption = $options['qrCodeExternalID'] ?? null;

    if ($qrCodeExternalIDOption !== null)
    {
        $qrCodeExternalIDOption = (int) $qrCodeExternalIDOption;
    }

    $qrCodeExternalUUIDOption = $options['qrCodeExternalUUID'] ?? null;

    if ($qrCodeExternalUUIDOption !== null)
    {
        $qrCodeExternalUUIDOption = trim((string) $qrCodeExternalUUIDOption);

        if ($qrCodeExternalUUIDOption === '')
        {
            $qrCodeExternalUUIDOption = null;
        }
    }

    $qrCodeLinkedAtOption = $options['qrCodeLinkedAt'] ?? null;

    if ($qrCodeLinkedAtOption !== null)
    {
        $qrCodeLinkedAtOption = (string) $qrCodeLinkedAtOption;
    }

    // Custom alias (FG-001): empty/absent means "generate a random code".
    $customCode = trim((string) ($options['customCode'] ?? ''));

    if ($customCode !== '')
    {
        $useCustomCode = true;
    }
    else
    {
        $useCustomCode = false;
    }

    // Determine code length from options or settings
    $codeLength = $options['codeLength']
        ?? (int) getSetting('shortcode.anonymous_length',
            getSetting('shortcode.default_length', 7));

    // ========================================================================
    // 🔤 Step 3b: Validate a supplied custom alias (FG-001)
    // ========================================================================
    // When the caller supplies a custom alias, it must pass server-side
    // validation BEFORE we touch the database: the format regex (which also
    // bounds it to the VARCHAR(50) column), then the reserved-word check that
    // stops it shadowing a Component B handler route. A failure here returns a
    // clear validation error and writes no row.
    //
    // 📖 Reference: g2ml_isReservedShortCode() above
    // 📖 Reference: G2ML_CUSTOM_CODE_PATTERN above
    // ========================================================================
    if ($useCustomCode === true)
    {
        if (preg_match(G2ML_CUSTOM_CODE_PATTERN, $customCode) !== 1)
        {
            return [
                'success'   => false,
                'shortCode' => null,
                'shortURL'  => null,
                'error'     => 'Custom alias must be 3 to 50 characters using only letters, numbers, hyphens, and underscores.',
            ];
        }

        if (g2ml_isReservedShortCode($customCode) === true)
        {
            return [
                'success'   => false,
                'shortCode' => null,
                'shortURL'  => null,
                'error'     => 'That alias is reserved and cannot be used. Please choose another.',
            ];
        }
    }

    // ========================================================================
    // 🎲 + 💾 Steps 4 & 5: Obtain a short code and insert
    // ========================================================================
    // Two paths share one INSERT:
    //
    //   • Random (default): sp_generateShortCode checks for collisions, but
    //     between that check and the INSERT a concurrent create can claim the
    //     same code (a time-of-check to time-of-use race). The UQ_shortcode_org
    //     unique key is the real guard: on a collision the INSERT fails with
    //     MySQL errno 1062 (ER_DUP_ENTRY). We wrap generate → insert in a
    //     bounded retry loop; on a duplicate key we regenerate and retry; on any
    //     other failure, or after the cap, we fail gracefully.
    //
    //   • Custom alias (FG-001): the user picked the code, so we must NOT change
    //     it. We attempt a single insert with the validated alias; a duplicate
    //     key means the alias is already taken and we return a clear error with
    //     NO regeneration and NO random fallback.
    //
    // 📖 Reference: web/_sql/procedures/sp_generateShortCode.sql
    // 📖 Reference: web/_functions/db_query.php — dbInsert(), dbLastErrno()
    // 📖 Reference: https://dev.mysql.com/doc/mysql-errors/8.0/en/server-error-reference.html (1062 = ER_DUP_ENTRY)
    // ========================================================================
    $duplicateKeyErrno    = 1062;
    $shortCode            = null;
    $insertResult         = false;
    $generationFailed     = false;
    $customCodeTaken      = false;
    $qrUuidTaken          = false;

    // A custom alias gets exactly one attempt (no regeneration); the random path
    // keeps its bounded retry budget.
    if ($useCustomCode === true)
    {
        $maxAttempts = 1;
    }
    else
    {
        $maxAttempts = 5;
    }

    // Insert SQL is static; only the bound short code changes between attempts.
    // 📖 Reference: web/_functions/db_query.php — dbInsert()
    $insertSQL = "INSERT INTO tblShortURLs
        (orgHandle, shortCode, destinationURL, destinationType,
         createdByUserUID, title, categoryID, urlNotes,
         startDate, endDate, isActive, createdVia, createdViaAPIKeyUID,
         qrCodeExternalID, qrCodeExternalUUID, qrCodeLinkedAt, createdAt, updatedAt)
        VALUES
        (?, ?, ?, 'url',
         ?, ?, ?, ?,
         ?, ?, ?, ?, ?,
         ?, ?, ?, NOW(), NOW())";

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++)
    {
        // Obtain a candidate short code: the validated alias verbatim, or a
        // freshly generated random code.
        if ($useCustomCode === true)
        {
            $shortCode = $customCode;
        }
        else
        {
            $spResult = dbCallProcedure(
                'sp_generateShortCode',
                [$orgHandle, $codeLength],
                'si',
                ['@outputCode']
            );

            if ($spResult === false || empty($spResult['@outputCode']))
            {
                error_log('[Go2My.Link] ERROR: sp_generateShortCode failed for org: ' . $orgHandle . ' (attempt ' . $attempt . ')');
                $generationFailed = true;
                break;
            }

            $shortCode = $spResult['@outputCode'];
        }

        // Build the types string and params array for this candidate.
        // s = string, i = integer (nullable userUID handled as string for NULL)
        $types  = 'sss';
        $params = [$orgHandle, $shortCode, $sanitisedURL];

        // createdByUserUID (nullable integer)
        if ($userUID !== null)
        {
            $types   .= 'i';
            $params[] = (int) $userUID;
        }
        else
        {
            $types   .= 's';
            $params[] = null;
        }

        // title (nullable string)
        $types   .= 's';
        $params[] = $title;

        // categoryID (nullable string)
        $types   .= 's';
        $params[] = $categoryID;

        // urlNotes (nullable string)
        $types   .= 's';
        $params[] = $notes;

        // startDate (nullable string)
        $types   .= 's';
        $params[] = $startDate;

        // endDate (nullable string)
        $types   .= 's';
        $params[] = $endDate;

        // isActive (bound integer, never interpolated — SEC-RECHECK-01)
        $types   .= 'i';
        $params[] = $isActiveValue;

        // createdVia (bound string; always one of the ENUM values, defaults 'web')
        $types   .= 's';
        $params[] = $createdViaOption;

        // createdViaAPIKeyUID (nullable integer)
        if ($createdViaAPIKeyUIDOption !== null)
        {
            $types   .= 'i';
            $params[] = $createdViaAPIKeyUIDOption;
        }
        else
        {
            $types   .= 's';
            $params[] = null;
        }

        // qrCodeExternalID (nullable integer — #145)
        if ($qrCodeExternalIDOption !== null)
        {
            $types   .= 'i';
            $params[] = $qrCodeExternalIDOption;
        }
        else
        {
            $types   .= 's';
            $params[] = null;
        }

        // qrCodeExternalUUID (nullable string; UNIQUE UQ_url_qr_uuid — #145)
        $types   .= 's';
        $params[] = $qrCodeExternalUUIDOption;

        // qrCodeLinkedAt (nullable DATETIME string — #145)
        $types   .= 's';
        $params[] = $qrCodeLinkedAtOption;

        $insertResult = dbInsert($insertSQL, $types, $params);

        if ($insertResult !== false)
        {
            // Inserted successfully — leave the loop with this short code.
            break;
        }

        // The insert failed on a duplicate key (1062). Two DIFFERENT unique
        // constraints can produce this SAME errno — UQ_shortcode_org (the
        // short code) and, when qrCodeExternalUUID was supplied, the QR-UUID
        // uniqueness guard (UQ_url_qr_uuid, #145) — and they must be handled
        // completely differently: a QR-UUID collision means CueRCode's QR is
        // ALREADY linked to a (different) short URL, which regenerating a new
        // random short code would never fix, so it must fail outright rather
        // than fall into the shortCode retry/alias-taken paths below.
        //
        // Disambiguate with a direct, targeted lookup on the QR-UUID itself
        // (never by parsing the driver's error text, which is not a stable
        // contract) — only meaningful when qrCodeExternalUUID was actually
        // supplied, since MySQL never treats two NULLs as equal for a unique
        // key, so a NULL qrCodeExternalUUID can never be the cause of a 1062.
        //
        // 📖 Reference: web/_sql/migrations/009_cuercode_qr_integration.sql (UQ_url_qr_uuid)
        if (dbLastErrno() === $duplicateKeyErrno)
        {
            if ($qrCodeExternalUUIDOption !== null)
            {
                $qrUuidCollisionRow = dbSelectOne(
                    "SELECT urlUID FROM tblShortURLs WHERE qrCodeExternalUUID = ? LIMIT 1",
                    's',
                    [$qrCodeExternalUUIDOption]
                );

                if ($qrUuidCollisionRow !== null && $qrUuidCollisionRow !== false)
                {
                    error_log('[Go2My.Link] INFO: CueRCode qrCodeExternalUUID already linked (1062) — uuid: ' . $qrCodeExternalUUIDOption . ', org: ' . $orgHandle);
                    $qrUuidTaken = true;
                    break;
                }
            }

            // A duplicate-key collision on the short code is handled
            // differently for the two paths: a random code is regenerated
            // and retried; a user-chosen alias must NOT be changed, so we
            // record that it is taken and stop without any fallback.
            if ($useCustomCode === true)
            {
                error_log('[Go2My.Link] INFO: custom alias already taken (1062) — code: ' . $shortCode . ', org: ' . $orgHandle);
                $customCodeTaken = true;
                break;
            }

            error_log('[Go2My.Link] WARNING: short code collision (1062) — code: ' . $shortCode . ', org: ' . $orgHandle . ', attempt ' . $attempt . ' of ' . $maxAttempts);
            $shortCode = null;
            continue;
        }

        error_log('[Go2My.Link] ERROR: Failed to insert short URL (non-duplicate) — code: ' . $shortCode . ', org: ' . $orgHandle);
        break;
    }

    // A QR UUID that is already linked to another short URL gets its own
    // distinguishable error and NEVER falls back to a random-code retry
    // (#145) — checked first since the qrUuidTaken path always takes
    // precedence over a same-attempt customCodeTaken flag (see the collision
    // handling above: only one of the two flags can ever be set per create).
    if ($qrUuidTaken === true)
    {
        return [
            'success'     => false,
            'shortCode'   => null,
            'shortURL'    => null,
            'error'       => 'That QR code is already linked to a short URL.',
            'qrUuidTaken' => true,
        ];
    }

    // A taken custom alias gets its own clear error and never falls back to a
    // random code.
    if ($customCodeTaken === true)
    {
        return [
            'success'   => false,
            'shortCode' => null,
            'shortURL'  => null,
            'error'     => 'That alias is already taken. Please choose another.',
        ];
    }

    if ($generationFailed === true)
    {
        return [
            'success'   => false,
            'shortCode' => null,
            'shortURL'  => null,
            'error'     => 'Failed to generate a unique short code. Please try again.',
        ];
    }

    if ($insertResult === false)
    {
        error_log('[Go2My.Link] ERROR: Failed to create short URL after ' . $maxAttempts . ' attempt(s) — org: ' . $orgHandle);

        return [
            'success'   => false,
            'shortCode' => null,
            'shortURL'  => null,
            'error'     => 'Failed to create the short URL. Please try again.',
        ];
    }

    // ========================================================================
    // 🔗 Step 6: Build the full short URL
    // ========================================================================
    $shortDomain = getDefaultShortDomain($orgHandle);
    $shortURL    = 'https://' . $shortDomain . '/' . $shortCode;

    // ========================================================================
    // 🏷️ Step 6b: Attach optional tags (authenticated dashboard — FG-002)
    // ========================================================================
    // Tags are additive metadata supplied only by the authenticated dashboard.
    // They are found-or-created per organisation and linked via tblShortURLTags.
    // This runs AFTER the short URL row is committed (the insert_id is the
    // urlUID) and is fully defensive: any tag failure is logged and swallowed so
    // it can never roll back or break the created short URL. The anonymous /
    // public create path never sets 'tags', so this is a no-op there.
    //
    // 📖 Reference: g2ml_attachTagsToShortURL() above
    // ========================================================================
    $tagsOption = $options['tags'] ?? null;

    if (($tagsOption !== null)
        && (is_string($tagsOption) || is_array($tagsOption))
        && is_int($insertResult)
        && $insertResult > 0)
    {
        try
        {
            g2ml_attachTagsToShortURL((int) $insertResult, $orgHandle, $tagsOption);
        }
        catch (Throwable $tagAttachError)
        {
            error_log('[Go2My.Link] WARNING: tag attach failed for URL ' . $insertResult . ': ' . $tagAttachError->getMessage());
        }
    }

    // ========================================================================
    // 📊 Step 7: Log the creation activity
    // ========================================================================
    logActivity('create_link', 'success', 201, [
        'orgHandle'      => $orgHandle,
        'shortCode'      => $shortCode,
        'destinationURL' => $sanitisedURL,
    ]);

    // ========================================================================
    // ✅ Step 8: Return success
    // ========================================================================
    return [
        'success'   => true,
        'shortCode' => $shortCode,
        'shortURL'  => $shortURL,
        'error'     => null,
    ];
}

// ============================================================================
// ⏱️ checkAnonymousRateLimit — Check IP-based rate limits
// ============================================================================
// Uses tblActivityLog to count recent 'create_link' actions from the same IP.
// No Redis needed — pure MySQL query against indexed columns.
//
// 📖 Reference: https://dev.mysql.com/doc/refman/8.0/en/date-and-time-functions.html
//
// @param  string $ipAddress  The client IP address
// @return array              ['allowed' => bool, 'remaining' => int,
//                             'hourlyCount' => int, 'dailyCount' => int]
// ============================================================================
function checkAnonymousRateLimit(string $ipAddress): array
{
    // Get rate limit settings
    $perHour = (int) getSetting('ratelimit.anonymous_per_hour', 10);
    $perDay  = (int) getSetting('ratelimit.anonymous_per_day', 50);

    // Count recent creations from this IP (hourly window)
    // Uses IDX_log_ip, IDX_log_action, IDX_log_created indexes
    $hourlyRow = dbSelectOne(
        "SELECT COUNT(*) AS cnt
         FROM tblActivityLog
         WHERE logAction = 'create_link'
           AND ipAddress = ?
           AND createdAt >= DATE_SUB(NOW(), INTERVAL 1 HOUR)",
        's',
        [$ipAddress]
    );

    if (($hourlyRow !== null && $hourlyRow !== false)) {
        $hourlyCount = (int) $hourlyRow['cnt'];
    } else {
        $hourlyCount = 0;
    }

    // Count recent creations from this IP (daily window)
    $dailyRow = dbSelectOne(
        "SELECT COUNT(*) AS cnt
         FROM tblActivityLog
         WHERE logAction = 'create_link'
           AND ipAddress = ?
           AND createdAt >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
        's',
        [$ipAddress]
    );

    if (($dailyRow !== null && $dailyRow !== false)) {
        $dailyCount = (int) $dailyRow['cnt'];
    } else {
        $dailyCount = 0;
    }

    // Check if limits are exceeded
    $allowed   = ($hourlyCount < $perHour) && ($dailyCount < $perDay);
    $remaining = min($perHour - $hourlyCount, $perDay - $dailyCount);

    if ($remaining < 0)
    {
        $remaining = 0;
    }

    return [
        'allowed'     => $allowed,
        'remaining'   => $remaining,
        'hourlyCount' => $hourlyCount,
        'dailyCount'  => $dailyCount,
    ];
}

// ============================================================================
// 🌐 getDefaultShortDomain — Get the default short domain for an org
// ============================================================================
// Queries tblOrgShortDomains for the default active short domain of the
// given organisation. Falls back to 'g2my.link'.
//
// @param  string $orgHandle  The organisation handle
// @return string             The short domain (e.g., 'g2my.link', 'camsda.link')
// ============================================================================
function getDefaultShortDomain(string $orgHandle): string
{
    $row = dbSelectOne(
        "SELECT shortDomain
         FROM tblOrgShortDomains
         WHERE orgHandle = ?
           AND isDefault = 1
           AND isActive = 1
         LIMIT 1",
        's',
        [$orgHandle]
    );

    if ($row !== null && $row !== false && !empty($row['shortDomain']))
    {
        return $row['shortDomain'];
    }

    // Fallback to the default domain
    return 'g2my.link';
}

// ============================================================================
// 🤖 verifyCaptcha — Verify a Turnstile or reCAPTCHA response
// ============================================================================
// Performs server-side verification of the CAPTCHA response by posting to
// the provider's verify endpoint. Uses PHP streams (no cURL dependency)
// for shared hosting compatibility.
//
// 📖 Reference: https://developers.cloudflare.com/turnstile/get-started/server-side-validation/
// 📖 Reference: https://developers.google.com/recaptcha/docs/verify
// 📖 Reference: https://www.php.net/manual/en/function.file-get-contents.php
//
// @param  string $type       'turnstile' or 'recaptcha'
// @param  string $response   The CAPTCHA response token from the client
// @param  string $secretKey  The server-side secret key
// @return bool               True if verification succeeded
// ============================================================================
function verifyCaptcha(string $type, string $response, string $secretKey): bool
{
    if ($response === '' || $secretKey === '')
    {
        return false;
    }

    // Determine the verify endpoint URL
    switch ($type)
    {
        case 'turnstile':
            $verifyURL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
            break;

        case 'recaptcha':
            $verifyURL = 'https://www.google.com/recaptcha/api/siteverify';
            break;

        default:
            return false;
    }

    // Build the POST data
    // 📖 Reference: https://www.php.net/manual/en/function.http-build-query.php
    $postData = http_build_query([
        'secret'   => $secretKey,
        'response' => $response,
        'remoteip' => g2ml_getClientIP(),
    ]);

    // Create the stream context for the POST request
    // 📖 Reference: https://www.php.net/manual/en/function.stream-context-create.php
    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded' . "\r\n"
                       . 'Content-Length: ' . strlen($postData) . "\r\n",
            'content' => $postData,
            'timeout' => 10, // 10 second timeout
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);

    // Make the verification request
    $responseBody = @file_get_contents($verifyURL, false, $context);

    if ($responseBody === false)
    {
        error_log('[Go2My.Link] WARNING: CAPTCHA verification request failed for type: ' . $type);
        return false;
    }

    // Parse the JSON response
    // 📖 Reference: https://www.php.net/manual/en/function.json-decode.php
    $result = json_decode($responseBody, true);

    if (!is_array($result))
    {
        error_log('[Go2My.Link] WARNING: CAPTCHA verification returned invalid JSON');
        return false;
    }

    return isset($result['success']) && $result['success'] === true;
}
