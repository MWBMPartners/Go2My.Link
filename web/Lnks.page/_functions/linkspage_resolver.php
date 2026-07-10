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
 * 🔒 SECURITY (C.3, #47 — owner-only page preview):
 *   - g2ml_linkspageBuildOwnerPreviewModel() is the "adjusted renderer entry"
 *     the admin dashboard's preview route (pages/linkspage/preview/index.php)
 *     uses to show an owner their OWN page — including an UNPUBLISHED draft —
 *     through this SAME rendering pipeline. It deliberately takes an
 *     ALREADY ownership-verified $pageRow/$itemRows pair (from
 *     web/_functions/linkspage_manage.php's g2ml_linkspageManageGetPageForOwner()
 *     / g2ml_linkspageManageListItemsForPage(), both scoped by `userUID = ?`
 *     in SQL) and NEVER itself queries tblLinksPages — so it can never be
 *     used to loosen g2ml_resolveLinksPage()'s public `isPublished = 1`
 *     filter above. Ownership enforcement is entirely the CALLER's
 *     responsibility; this function only resolves the template and shapes
 *     the page model, reusing g2ml_linkspageLoadSystemTemplate() /
 *     g2ml_linkspageLoadDefaultSystemTemplate() rather than duplicating that
 *     lookup.
 *
 * 🔒 SECURITY (C.4, #46 — custom-domain LinksPage fallback):
 *   - g2ml_resolveLinksPageByUID() is the second PUBLIC entry point, used by
 *     web/G2My.Link/_functions/linkspage_fallback.php (Component B's redirect
 *     hot path) to render an org's designated LinksPage at a verified custom
 *     domain's root, or when a requested path does not resolve to a short
 *     code, instead of a 404. It applies the EXACT SAME `isPublished = 1 AND
 *     isActive = 1` filter as g2ml_resolveLinksPage() — a designated but
 *     unpublished/inactive page can NEVER be surfaced through a custom
 *     domain, even though the caller already trusts the pageUID came from
 *     tblOrgShortDomains.linksPageUID (itself only ever settable by
 *     setShortDomainLinksPage() in web/_functions/org.php, which re-verifies
 *     ownership + isPublished at write time — this is deliberate
 *     defence-in-depth against a page being unpublished AFTER it was
 *     designated). Both public entry points share one internal model builder
 *     (_g2ml_linkspageBuildPublicModelFromRow()) so the template-resolution
 *     and items-lookup logic is written once, not duplicated.
 *
 * Functions:
 *   - g2ml_linkspageIsValidSlug()                 — charset/length guard for the URL slug
 *   - g2ml_linkspageDefaultTemplateSlug()          — the operator-configured fallback template slug
 *   - g2ml_linkspageLoadSystemTemplate()           — load ONE system template row by templateUID
 *   - g2ml_linkspageLoadDefaultSystemTemplate()    — load the default (or first active) system template
 *   - g2ml_linkspageServiceEnabled()                — the linkspage.enabled operator kill-switch check
 *   - _g2ml_linkspageBuildPublicModelFromRow()      — shared template+items model builder (internal)
 *   - g2ml_resolveLinksPage()                       — the main PUBLIC resolver, by slug (DB-touching)
 *   - g2ml_resolveLinksPageByUID()                  — C.4/#46 PUBLIC resolver, by pageUID (DB-touching)
 *   - g2ml_linkspageBuildOwnerPreviewModel()         — C.3/#47 OWNER PREVIEW model builder (pre-verified input only)
 *   - g2ml_linkspageHasAgeGatedItems()                — C.5/#50 whole-page age-gate detector (pure, DB-free)
 *
 * Dependencies: web/_functions/db_query.php (dbSelect()/dbSelectOne()) and
 *               web/_functions/settings.php (getSetting()) — both already
 *               loaded via page_init.php before this file is required.
 *
 * @package    Go2My.Link
 * @subpackage ComponentC
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.3.0
 * @since      Phase 8 (#45; by-pageUID public resolver added v1.2.0 / #46; age gate v1.2.0 / #50)
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
// 🔌 Operator kill-switch
// ============================================================================

/**
 * The linkspage.enabled operator kill-switch — mirrors the
 * cuercode.integration_enabled pattern used elsewhere in the codebase: when
 * off, EVERY page (by slug or by pageUID) behaves as not-found rather than
 * partially working. Shared by BOTH public resolvers below.
 *
 * @return bool
 */
function g2ml_linkspageServiceEnabled(): bool
{
    if (!function_exists('getSetting'))
    {
        return true;
    }

    $serviceEnabled = getSetting('linkspage.enabled', true);

    if ($serviceEnabled === false || $serviceEnabled === '0' || $serviceEnabled === 0)
    {
        return false;
    }

    return true;
}

// ============================================================================
// 🧱 Shared public-model builder (template + items) — internal
// ============================================================================

/**
 * Resolve the template + items for an ALREADY-fetched, ALREADY publicly
 * visible (isPublished = 1 AND isActive = 1) tblLinksPages row, and shape the
 * complete page model g2ml_renderLinksPage() expects.
 *
 * Shared by g2ml_resolveLinksPage() (by slug) and g2ml_resolveLinksPageByUID()
 * (by pageUID, #46) so the template-resolution and items-lookup logic is
 * written exactly once. This function does NOT itself enforce the
 * isPublished/isActive filter — both callers already applied it in their own
 * tblLinksPages SELECT before calling this.
 *
 * @param  array $pageRow  A row shaped like g2ml_resolveLinksPage()'s own
 *                          SELECT (pageUID, userUID, orgHandle, slug,
 *                          pageTitle, pageDescription, avatarPath, templateUID,
 *                          themeColour, backgroundColour, fontFamily,
 *                          showSocialIcons, socialLinks).
 * @return array|null    ['page' => array, 'template' => array, 'items' => array],
 *                        or null when no usable active SYSTEM template exists
 *                        at all (a misconfigured install).
 */
function _g2ml_linkspageBuildPublicModelFromRow(array $pageRow): ?array
{
    $templateRow = null;

    if (isset($pageRow['templateUID']) && $pageRow['templateUID'] !== null)
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
        error_log('[Go2My.Link] ERROR: _g2ml_linkspageBuildPublicModelFromRow — no active system template available for pageUID: ' . (string) ($pageRow['pageUID'] ?? '?'));
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

// ============================================================================
// 📋 Main resolver — by slug (public lnks.page/<slug>)
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

    if (!g2ml_linkspageServiceEnabled())
    {
        return null;
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

    return _g2ml_linkspageBuildPublicModelFromRow($pageRow);
}

// ============================================================================
// 📋 Second public resolver — by pageUID (C.4, #46 — custom-domain fallback)
// ============================================================================

/**
 * Resolve a page model BY pageUID, for the custom-domain LinksPage fallback
 * (web/G2My.Link/_functions/linkspage_fallback.php, Component B). The
 * pageUID comes from tblOrgShortDomains.linksPageUID — a value only ever
 * written by setShortDomainLinksPage() (web/_functions/org.php), which itself
 * re-verifies the page belongs to the requesting org AND is published at
 * write time. This function independently re-applies the SAME
 * `isPublished = 1 AND isActive = 1` filter g2ml_resolveLinksPage() uses
 * regardless — defence-in-depth against a page being unpublished (or
 * deactivated) AFTER it was designated, which must instantly stop it from
 * being rendered here even though the domain's designation row still points
 * at it.
 *
 * 🔒 This is a PUBLIC, org-agnostic read — it deliberately does NOT check
 * which org owns the page. That is safe ONLY because the caller already
 * restricts pageUID to one drawn from a specific tblOrgShortDomains row (a
 * value nothing here lets a visitor supply directly) — this function itself
 * performs no ownership check beyond isPublished/isActive, exactly mirroring
 * how g2ml_resolveLinksPage() is like public "by slug" reads.
 *
 * @param  int $pageUID
 * @return array|null    ['page' => array, 'template' => array, 'items' => array],
 *                        or null when the pageUID does not resolve to a
 *                        publicly visible page (missing, unpublished,
 *                        inactive, or the service is administratively
 *                        disabled).
 */
function g2ml_resolveLinksPageByUID(int $pageUID): ?array
{
    if ($pageUID <= 0)
    {
        return null;
    }

    if (!g2ml_linkspageServiceEnabled())
    {
        return null;
    }

    // 🔒 customHTML / customCSS are deliberately NEVER selected — see the file
    // header. Same column set as g2ml_resolveLinksPage()'s own SELECT.
    $pageRow = dbSelectOne(
        "SELECT pageUID, userUID, orgHandle, slug, pageTitle, pageDescription, avatarPath,
                templateUID, themeColour, backgroundColour, fontFamily, showSocialIcons, socialLinks
         FROM tblLinksPages
         WHERE pageUID = ? AND isPublished = 1 AND isActive = 1
         LIMIT 1",
        'i',
        [$pageUID]
    );

    if ($pageRow === null || $pageRow === false)
    {
        return null;
    }

    return _g2ml_linkspageBuildPublicModelFromRow($pageRow);
}

// ============================================================================
// 👁️ Owner-only page preview model (Component C.3, #47)
// ============================================================================

/**
 * Build a render-ready page model for an OWNER PREVIEW of a page that has
 * ALREADY been fetched and verified as belonging to the acting user —
 * including an UNPUBLISHED draft, which g2ml_resolveLinksPage() above would
 * never return (its `isPublished = 1` filter is deliberately never touched
 * or bypassed by this function).
 *
 * 🔒 This function does NOT query tblLinksPages or tblLinksPageItems at all —
 * it trusts $pageRow/$itemRows completely. The caller MUST have obtained
 * both via an ownership-scoped lookup (web/_functions/linkspage_manage.php's
 * g2ml_linkspageManageGetPageForOwner() / g2ml_linkspageManageListItemsForPage(),
 * both of which bind `userUID = ?` in SQL — a pageUID belonging to another
 * user returns null/empty from those, identically to a pageUID that does not
 * exist). Passing an un-verified $pageRow here would be an IDOR bug in the
 * CALLER, not something this function can detect or prevent.
 *
 * Template resolution mirrors g2ml_resolveLinksPage() exactly — the same
 * templateUID -> g2ml_linkspageLoadSystemTemplate() -> (on miss)
 * g2ml_linkspageLoadDefaultSystemTemplate() fallback chain, so an owner's
 * preview always matches what the public page would actually show once
 * published, using the identical SYSTEM (isSystem = 1) template resolution —
 * REUSED here, not reimplemented.
 *
 * @param  array $pageRow   An ownership-verified row shaped like
 *                           g2ml_linkspageManageGetPageForOwner()'s return
 *                           (pageUID, pageTitle, pageDescription, avatarPath,
 *                           templateUID, themeColour, backgroundColour,
 *                           fontFamily, showSocialIcons, socialLinks, ...).
 *                           customHTML/customCSS are irrelevant even if
 *                           present — g2ml_renderLinksPage() never reads them.
 * @param  array $itemRows  Rows shaped like
 *                           g2ml_linkspageManageListItemsForPage()'s return.
 * @return array|null  ['page' => array, 'template' => array, 'items' => array]
 *                      ready for g2ml_renderLinksPage(), or null when no
 *                      usable active SYSTEM template exists at all (a
 *                      misconfigured install — mirrors g2ml_resolveLinksPage()'s
 *                      own "cannot render safely" outcome).
 */
function g2ml_linkspageBuildOwnerPreviewModel(array $pageRow, array $itemRows): ?array
{
    $templateRow = null;

    if (isset($pageRow['templateUID']) && $pageRow['templateUID'] !== null && $pageRow['templateUID'] !== '')
    {
        $templateRow = g2ml_linkspageLoadSystemTemplate((int) $pageRow['templateUID']);
    }

    if ($templateRow === null)
    {
        $templateRow = g2ml_linkspageLoadDefaultSystemTemplate();
    }

    if ($templateRow === null)
    {
        error_log('[Go2My.Link] ERROR: g2ml_linkspageBuildOwnerPreviewModel — no active system template available for pageUID: ' . (string) ($pageRow['pageUID'] ?? '?'));
        return null;
    }

    return [
        'page'     => $pageRow,
        'template' => $templateRow,
        'items'    => $itemRows,
    ];
}

// ============================================================================
// 🔞 Whole-page age-gate detector (Component C.5, #50)
// ============================================================================

/**
 * Determine whether ANY item on an already-resolved page model is flagged
 * requiresAgeGate = 1.
 *
 * 🔒 WHOLE-PAGE GATING CHOICE (documented per the task's "your call" note):
 * if a page has AT LEAST ONE gated item, the ENTIRE page is gated behind the
 * good-faith interstitial (web/Lnks.page/_functions/linkspage_renderer.php's
 * g2ml_linkspageRenderAgeGateInterstitial()) rather than attempting a
 * per-item reveal. A per-item reveal would need JavaScript to intercept a
 * click and swap in the real href afterwards — this component's CSP
 * (script-src 'self', no inline/external scripts — see
 * web/Lnks.page/public_html/.htaccess) forbids that outright, and the task
 * explicitly requires the flow to work with NO JavaScript. Gating the whole
 * page is the simplest mechanism that satisfies "gated item hrefs are never
 * emitted before confirmation" without any script at all — see
 * web/Lnks.page/public_html/index.php's Step 8.5 for where this is used.
 *
 * @param  array $items  The page model's 'items' array (already isActive = 1
 *                        filtered by the resolver above).
 * @return bool
 */
function g2ml_linkspageHasAgeGatedItems(array $items): bool
{
    foreach ($items as $item)
    {
        if (!is_array($item))
        {
            continue;
        }

        if (isset($item['requiresAgeGate']) && (int) $item['requiresAgeGate'] === 1)
        {
            return true;
        }
    }

    return false;
}
