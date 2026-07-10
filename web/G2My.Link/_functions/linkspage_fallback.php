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
 * 📄 Go2My.Link — Custom-Domain LinksPage Fallback (Component B, C.4 — #46)
 * ============================================================================
 *
 * When a partner's VERIFIED custom short domain (tblOrgShortDomains) is hit
 * at its root (no short code at all), or a requested path does NOT resolve to
 * a short code, AND the org has designated a published LinksPage for that
 * domain, this renders that LinksPage instead of a 404.
 *
 * 🔒 SECURITY:
 *   - Only a domain row with verificationStatus = 'verified' AND isActive = 1
 *     is ever eligible — this mirrors the EXACT gate sp_lookupShortURL /
 *     getOrgByDomain() already enforce (#91). An unverified/claimed domain
 *     (status domain_not_configured) NEVER reaches a LinksPage render; it
 *     keeps its existing branded "domain not configured" page.
 *   - g2ml_resolveLinksPageByUID() (web/Lnks.page/_functions/
 *     linkspage_resolver.php, #45/#46) independently re-enforces
 *     `isPublished = 1 AND isActive = 1` — a designated page that was since
 *     unpublished or deactivated is NEVER rendered here, even though this
 *     domain row still points at it. There is no way to reach a draft page
 *     through a custom domain.
 *   - The renderer itself (g2ml_renderLinksPage(), #45) is REUSED, never
 *     reimplemented — every user-authored string is escaped, every URL is
 *     scheme-validated, customHTML/customCSS are never read (deferred to
 *     C.6/#49), exactly as for the public lnks.page/<slug> route and the
 *     admin owner-preview route (C.3/#47).
 *
 * 🔒 HOT-PATH RULE: this file is ONLY ever require_once'd from index.php on
 * the two branches that are ALREADY off the normal successful-redirect path
 * — the empty-short-code (root) branch, and the 'not_found' error branch.
 * A normal short-code redirect never triggers a require of this file, let
 * alone a call into it, so it adds ZERO overhead to the hot path.
 *
 * Functions:
 *   - g2ml_lookupOrgShortDomainForLinksPage() — the single tblOrgShortDomains lookup
 *   - g2ml_renderCustomDomainLinksPageFallback() — top-level: domain -> rendered HTML (or false)
 *
 * Dependencies: web/_functions/db_query.php (dbSelectOne()) — already loaded
 *               via page_init.php before this file is required. Cross-component
 *               requires (guarded) for web/Lnks.page/_functions/
 *               linkspage_resolver.php + linkspage_renderer.php — see the note
 *               inside g2ml_renderCustomDomainLinksPageFallback().
 *
 * @package    Go2My.Link
 * @subpackage ComponentB
 * @author     MWBM Partners Ltd (MWservices)
 * @version    1.0.0
 * @since      v1.2.0 — Phase 8 (#46)
 *
 * 📖 References:
 *     - Schema:            web/_sql/schema/012_core_organisations.sql (tblOrgShortDomains.linksPageUID)
 *     - Migration:         web/_sql/migrations/015_org_short_domain_linkspage.sql
 *     - Admin designation: web/_functions/org.php — setShortDomainLinksPage() / getOrgPublishedLinksPages()
 *     - Public resolver:   web/Lnks.page/_functions/linkspage_resolver.php — g2ml_resolveLinksPageByUID()
 *     - Public renderer:   web/Lnks.page/_functions/linkspage_renderer.php — g2ml_renderLinksPage()
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
// 🔎 g2ml_lookupOrgShortDomainForLinksPage — the single tblOrgShortDomains lookup
// ============================================================================
// A single prepared-statement lookup by exact shortDomain match — the SAME
// value (already port-stripped, but otherwise unmodified) index.php passes to
// resolveShortCode()/sp_lookupShortURL, so this function's verdict about
// "which domain row matches this Host" is always consistent with whatever the
// SP itself just decided for the SAME request.
//
// @param  string $requestDomain
// @return array|null  ['verificationStatus' => string, 'isActive' => int,
//                       'linksPageUID' => int|null], or null when no
//                       tblOrgShortDomains row claims this exact host at all.
// ============================================================================
function g2ml_lookupOrgShortDomainForLinksPage(string $requestDomain): ?array
{
    if ($requestDomain === '')
    {
        return null;
    }

    $row = dbSelectOne(
        "SELECT verificationStatus, isActive, linksPageUID
         FROM tblOrgShortDomains
         WHERE shortDomain = ?
         LIMIT 1",
        's',
        [$requestDomain]
    );

    if ($row === null || $row === false)
    {
        return null;
    }

    return $row;
}

// ============================================================================
// 🚀 g2ml_renderCustomDomainLinksPageFallback — top-level entry point
// ============================================================================
// Attempts to render the org's designated LinksPage for a VERIFIED custom
// short domain. Returns false (renders NOTHING, echoes NOTHING) the instant
// any precondition is not met, so the caller (index.php) can simply fall
// through to its EXISTING behaviour unchanged.
//
// Deliberately does NOT call exit() itself — the caller decides when to stop
// execution (mirrors g2ml_renderLinksPage() and every OTHER function in this
// codebase that returns a value rather than terminating the request), and
// this keeps the function directly unit/integration-testable via output
// buffering without ending the test process.
//
// @param  string $requestDomain  The already port-stripped request Host, as
//                                 passed to resolveShortCode() in index.php.
// @return bool  true  — a LinksPage was found and its HTML has been echoed
//                       (the caller should exit() immediately afterwards);
//               false — nothing was rendered; the caller's existing fallback
//                       behaviour (redirect to redirect.fallback_url, or the
//                       branded 404 page) applies unchanged.
// ============================================================================
function g2ml_renderCustomDomainLinksPageFallback(string $requestDomain): bool
{
    $domainRow = g2ml_lookupOrgShortDomainForLinksPage($requestDomain);

    if ($domainRow === null)
    {
        // No custom-domain claim at all for this host (e.g. an unknown/stray
        // Host, or the system's own default domain with no row) — nothing to
        // fall back to.
        return false;
    }

    // 🔒 Mirrors the EXACT gate sp_lookupShortURL / getOrgByDomain() already
    // enforce (#91) — an unverified or inactive domain claim must NEVER
    // surface anything, including a LinksPage.
    if ($domainRow['verificationStatus'] !== 'verified' || (int) $domainRow['isActive'] !== 1)
    {
        return false;
    }

    if ($domainRow['linksPageUID'] === null)
    {
        // Verified domain, but the org has not designated a LinksPage for it
        // — the caller's existing behaviour (fallback redirect, or 404)
        // applies completely unchanged.
        return false;
    }

    $linksPageUID = (int) $domainRow['linksPageUID'];

    // ------------------------------------------------------------------------
    // Load the Component C resolver/renderer cross-component (guarded,
    // deploy-safe — mirrors the SAME pattern used by the admin owner-preview
    // route, web/Go2My.Link/_admin/public_html/pages/linkspage/preview/index.php,
    // #47). Component B and Component C are channel-split only for their
    // public_html/ web roots; everything else (including Lnks.page/_functions)
    // deploys to the SAME remote root (see .github/workflows/sftp-deploy.yml),
    // so this relative require is deploy-safe.
    // ------------------------------------------------------------------------
    $linkspageResolverPath = dirname(__DIR__, 2)
        . DIRECTORY_SEPARATOR . 'Lnks.page'
        . DIRECTORY_SEPARATOR . '_functions'
        . DIRECTORY_SEPARATOR . 'linkspage_resolver.php';

    $linkspageRendererPath = dirname(__DIR__, 2)
        . DIRECTORY_SEPARATOR . 'Lnks.page'
        . DIRECTORY_SEPARATOR . '_functions'
        . DIRECTORY_SEPARATOR . 'linkspage_renderer.php';

    if (file_exists($linkspageResolverPath) && !function_exists('g2ml_resolveLinksPageByUID'))
    {
        require_once $linkspageResolverPath;
    }

    if (file_exists($linkspageRendererPath) && !function_exists('g2ml_renderLinksPage'))
    {
        require_once $linkspageRendererPath;
    }

    if (!function_exists('g2ml_resolveLinksPageByUID') || !function_exists('g2ml_renderLinksPage'))
    {
        error_log('[Go2My.Link] ERROR: g2ml_renderCustomDomainLinksPageFallback — Component C resolver/renderer could not be loaded from: ' . $linkspageResolverPath);
        return false;
    }

    // 🔒 Independently re-enforces isPublished = 1 AND isActive = 1 — see the
    // file header's defence-in-depth note. A designated page that has since
    // been unpublished/deactivated returns null here and this function
    // correctly falls back to false (existing 404 behaviour), never a
    // half-rendered or draft page.
    $pageModel = g2ml_resolveLinksPageByUID($linksPageUID);

    if ($pageModel === null)
    {
        return false;
    }

    // A previously set error status code (e.g. http_response_code(404) in the
    // 'not_found' branch of index.php) must be overridden — this is now a
    // genuinely successful response, not an error. Guarded by headers_sent()
    // exactly like buildRedirectResponse() (redirect_resolver.php) already
    // does — harmless to skip if, for any reason, output has already begun.
    if (!headers_sent())
    {
        http_response_code(200);
    }

    echo g2ml_renderLinksPage($pageModel);

    return true;
}
