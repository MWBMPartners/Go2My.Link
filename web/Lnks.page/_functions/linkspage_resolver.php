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
 * 📋 Go2My.Link — LinksPage Resolver (Component C)
 * ============================================================================
 *
 * Resolves a public lnks.page/<slug> request into a structured, render-ready
 * page model: the published page row, its SYSTEM template, and its active
 * items — or null when the slug does not resolve to a publicly visible page.
 *
 * 🔒 SECURITY:
 *   - customHTML / customCSS are deliberately NEVER selected here. They are
 *     the C.6 / #49 stored-XSS surface (WYSIWYG + HTML upload) and are out of
 *     scope for this renderer — deferred until that work ships its own
 *     sanitiser and security review.
 *   - Only SYSTEM templates (isSystem = 1, isActive = 1) are ever consumed. A
 *     page whose templateUID is NULL, or points at a non-system/inactive
 *     template row, silently falls back to the configured default system
 *     template rather than ever touching a user-authored template.
 *   - Every query is a prepared statement (MySQLi via dbSelect()/dbSelectOne()) —
 *     the slug is bound as a parameter, never interpolated.
 *
 * Functions:
 *   - g2ml_linkspageIsValidSlug()            — charset/length guard for the URL slug
 *   - g2ml_linkspageDefaultTemplateSlug()     — the operator-configured fallback template slug
 *   - g2ml_linkspageLoadSystemTemplate()      — load ONE system template row by templateUID
 *   - g2ml_linkspageLoadDefaultSystemTemplate() — load the default (or first active) system template
 *   - g2ml_resolveLinksPage()                 — the main resolver (DB-touching)
 *
 * Dependencies: web/_functions/db_query.php (dbSelect()/dbSelectOne()) and
 *               web/_functions/settings.php (getSetting()) — both already
 *               loaded via page_init.php before this file is required.
 *
 * @package    Go2My.Link
 * @subpackage ComponentC
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.1.0
 * @since      Phase 8 (#45)
 * ============================================================================
 */

declare(strict_types=1);

// ============================================================================
// 🛡️ Direct Access Guard
// ============================================================================
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}

// ============================================================================
// 🔤 Slug validation
// ============================================================================

/**
 * Validate a LinksPage slug's shape BEFORE it ever reaches a database query.
 *
 * Deliberately restrictive: URL-safe characters only, 1–100 characters,
 * matching the UNIQUE `slug` column's VARCHAR(100) width. A slug that fails
 * this check is treated as not-found without ever touching the database.
 *
 * @param  string $slug
 * @return bool
 */
function g2ml_linkspageIsValidSlug(string $slug): bool
{
    if ($slug === '')
    {
        return false;
    }

    if (preg_match('/^[A-Za-z0-9_-]{1,100}$/', $slug) !== 1)
    {
        return false;
    }

    return true;
}

// ============================================================================
// 🎨 System template resolution
// ============================================================================

/**
 * The operator-configured default SYSTEM template slug, used whenever a page
 * has no templateUID, or its templateUID does not resolve to an active
 * SYSTEM template row.
 *
 * Reads the 'linkspage.default_template' setting (seeded as 'default' in
 * web/_sql/seeds/003_default_settings.sql). Falls back to the literal string
 * 'default' when settings are unavailable or the value is empty.
 *
 * @return string
 */
function g2ml_linkspageDefaultTemplateSlug(): string
{
    if (function_exists('getSetting'))
    {
        $configured = getSetting('linkspage.default_template', 'default');

        if (is_string($configured) && trim($configured) !== '')
        {
            return trim($configured);
        }
    }

    return 'default';
}

/**
 * Load ONE active SYSTEM template row by templateUID.
 *
 * Deliberately requires isSystem = 1 AND isActive = 1 — a page that points at
 * a non-system or deactivated template row is treated exactly like a page
 * with no template at all by the caller (g2ml_resolveLinksPage()), which then
 * falls back to the configured default system template.
 *
 * customHTML/customCSS do not exist on this table — templateHTML/templateCSS
 * here are the SYSTEM (trusted, isSystem = 1) template body, not user content.
 *
 * @param  int $templateUID
 * @return array|null
 */
function g2ml_linkspageLoadSystemTemplate(int $templateUID): ?array
{
    $row = dbSelectOne(
        "SELECT templateUID, templateSlug, templateName, templateHTML, templateCSS
         FROM tblLinksPageTemplates
         WHERE templateUID = ? AND isSystem = 1 AND isActive = 1
         LIMIT 1",
        'i',
        [$templateUID]
    );

    if ($row === null || $row === false)
    {
        return null;
    }

    return $row;
}

/**
 * Load the operator-configured default SYSTEM template by slug, falling back
 * to the FIRST active system template (by sortOrder) if even the configured
 * default slug cannot be found — so a render is always possible as long as at
 * least one active system template row exists in the database.
 *
 * @return array|null
 */
function g2ml_linkspageLoadDefaultSystemTemplate(): ?array
{
    $defaultSlug = g2ml_linkspageDefaultTemplateSlug();

    $row = dbSelectOne(
        "SELECT templateUID, templateSlug, templateName, templateHTML, templateCSS
         FROM tblLinksPageTemplates
         WHERE templateSlug = ? AND isSystem = 1 AND isActive = 1
         LIMIT 1",
        's',
        [$defaultSlug]
    );

    if ($row !== null && $row !== false)
    {
        return $row;
    }

    $fallbackRows = dbSelect(
        "SELECT templateUID, templateSlug, templateName, templateHTML, templateCSS
         FROM tblLinksPageTemplates
         WHERE isSystem = 1 AND isActive = 1
         ORDER BY sortOrder ASC, templateUID ASC
         LIMIT 1"
    );

    if ($fallbackRows === false || count($fallbackRows) === 0)
    {
        return null;
    }

    return $fallbackRows[0];
}

// ============================================================================
// 📋 Main resolver
// ============================================================================

/**
 * Resolve a public lnks.page/<slug> request into a structured page model.
 *
 * @param  string $slug  The ALREADY shape-validated slug — see
 *                        g2ml_linkspageIsValidSlug(). Callers should reject an
 *                        invalid shape before ever calling this function.
 * @return array|null    ['page' => array, 'template' => array, 'items' => array],
 *                        or null when the slug does not resolve to a publicly
 *                        visible page (missing, unpublished, inactive, or the
 *                        service is administratively disabled).
 */
function g2ml_resolveLinksPage(string $slug): ?array
{
    if ($slug === '')
    {
        return null;
    }

    // Operator kill-switch — mirrors the cuercode.integration_enabled pattern
    // used elsewhere in the codebase: when off, EVERY slug behaves as
    // not-found rather than partially working.
    if (function_exists('getSetting'))
    {
        $serviceEnabled = getSetting('linkspage.enabled', true);

        if ($serviceEnabled === false || $serviceEnabled === '0' || $serviceEnabled === 0)
        {
            return null;
        }
    }

    // 🔒 customHTML / customCSS are deliberately NEVER selected — see the file
    // header. Only the columns the renderer actually needs are read.
    $pageRow = dbSelectOne(
        "SELECT pageUID, userUID, orgHandle, slug, pageTitle, pageDescription, avatarPath,
                templateUID, themeColour, backgroundColour, fontFamily, showSocialIcons, socialLinks
         FROM tblLinksPages
         WHERE slug = ? AND isPublished = 1 AND isActive = 1
         LIMIT 1",
        's',
        [$slug]
    );

    if ($pageRow === null || $pageRow === false)
    {
        return null;
    }

    $templateRow = null;

    if ($pageRow['templateUID'] !== null)
    {
        $templateRow = g2ml_linkspageLoadSystemTemplate((int) $pageRow['templateUID']);
    }

    if ($templateRow === null)
    {
        $templateRow = g2ml_linkspageLoadDefaultSystemTemplate();
    }

    if ($templateRow === null)
    {
        // No usable system template exists at all (a misconfigured install
        // with an empty/deactivated tblLinksPageTemplates) — the page cannot
        // be rendered safely, so treat it as not-found rather than emitting
        // a broken/empty page.
        error_log('[Go2My.Link] ERROR: g2ml_resolveLinksPage — no active system template available for slug: ' . $slug);
        return null;
    }

    $itemRows = dbSelect(
        "SELECT itemUID, itemTitle, itemURL, itemDescription, itemIcon, faviconCacheURL, requiresAgeGate
         FROM tblLinksPageItems
         WHERE pageUID = ? AND isActive = 1
         ORDER BY sortOrder ASC, itemUID ASC",
        'i',
        [(int) $pageRow['pageUID']]
    );

    if ($itemRows === false)
    {
        $itemRows = [];
    }

    return [
        'page'     => $pageRow,
        'template' => $templateRow,
        'items'    => $itemRows,
    ];
}
