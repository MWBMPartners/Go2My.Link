<?php
/**
 * ============================================================================
 * 🌐 Go2My.Link — Domain Resolver (Component B)
 * ============================================================================
 *
 * Resolves incoming request domains to organisation handles and provides
 * fallback URL logic for the redirect engine.
 *
 * Functions:
 *   - getOrgByDomain()        — Map a request domain to an org handle
 *   - getDomainFallbackURL()  — Get the fallback URL cascade for an org
 *   - getOrgFavicon()         — Get the org-specific favicon path
 *
 * Dependencies: db_query.php, settings.php (loaded via page_init.php)
 *
 * @package    Go2My.Link
 * @subpackage ComponentB
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
// 🌐 getOrgByDomain — Map a request domain to an organisation handle
// ============================================================================
// Looks up tblOrgShortDomains to find which organisation owns the requesting
// domain. Falls back to '[default]' if the domain is not registered.
//
// 📖 Reference: https://www.php.net/manual/en/mysqli-stmt.bind-param.php
//
// @param  string $domain  The request domain (e.g., 'g2my.link', 'camsda.link')
// @return string          The org handle (e.g., '[default]', 'camsda')
// ============================================================================
function getOrgByDomain(string $domain): string
{
    // Normalise domain — lowercase, strip www. prefix
    // 📖 Reference: https://www.php.net/manual/en/function.strtolower.php
    $domain = strtolower(trim($domain));

    if (strpos($domain, 'www.') === 0)
    {
        $domain = substr($domain, 4);
    }

    // Query tblOrgShortDomains for active domain mapping
    $row = dbSelectOne(
        "SELECT orgHandle
         FROM tblOrgShortDomains
         WHERE shortDomain = ?
           AND isActive = 1
         LIMIT 1",
        's',
        [$domain]
    );

    if ($row !== null && $row !== false && !empty($row['orgHandle']))
    {
        return $row['orgHandle'];
    }

    // Default organisation for unmapped domains (g2my.link, etc.)
    return '[default]';
}

// ============================================================================
// 🔗 getDomainFallbackURL — Get the fallback URL for an organisation
// ============================================================================
// Implements the fallback URL cascade:
//   1. Category fallback URL (if categoryID provided)
//   2. Organisation fallback URL (tblOrganisations.orgFallbackURL)
//   3. System setting (redirect.fallback_url)
//   4. Hardcoded default (https://go2my.link)
//
// @param  string      $orgHandle   The organisation handle
// @param  string|null $categoryID  Optional category ID for category-level fallback
// @return string                   The fallback URL
// ============================================================================
function getDomainFallbackURL(string $orgHandle, ?string $categoryID = null): string
{
    // 🏷️ Step 1: Try category-specific fallback URL
    if ($categoryID !== null && $categoryID !== '')
    {
        $catRow = dbSelectOne(
            "SELECT categoryFallbackURL
             FROM tblCategories
             WHERE categoryID = ?
               AND orgHandle = ?
               AND isActive = 1
             LIMIT 1",
            'ss',
            [$categoryID, $orgHandle]
        );

        if ($catRow !== null
            && $catRow !== false
            && !empty($catRow['categoryFallbackURL']))
        {
            return $catRow['categoryFallbackURL'];
        }
    }

    // 🏢 Step 2: Try organisation-specific fallback URL
    $orgRow = dbSelectOne(
        "SELECT orgFallbackURL
         FROM tblOrganisations
         WHERE orgHandle = ?
           AND isActive = 1
         LIMIT 1",
        's',
        [$orgHandle]
    );

    if ($orgRow !== null
        && $orgRow !== false
        && !empty($orgRow['orgFallbackURL']))
    {
        return $orgRow['orgFallbackURL'];
    }

    // ⚙️ Step 3: System default from settings
    // 📖 Reference: settings.php — getSetting() with scope cascade
    $systemFallback = getSetting('redirect.fallback_url', 'https://go2my.link');

    if ($systemFallback !== '' && $systemFallback !== null)
    {
        return $systemFallback;
    }

    // 🔒 Step 4: Hardcoded absolute last resort
    return 'https://go2my.link';
}

// ============================================================================
// 🎨 getOrgFavicon — Get the organisation-specific favicon path
// ============================================================================
// Returns the relative path to the org's logo/favicon from the _uploads/
// directory, or null if no custom favicon is configured.
//
// @param  string      $orgHandle  The organisation handle
// @return string|null             Relative path within _uploads/, or null
// ============================================================================
function getOrgFavicon(string $orgHandle): ?string
{
    if ($orgHandle === '' || $orgHandle === '[default]')
    {
        return null;
    }

    $orgRow = dbSelectOne(
        "SELECT orgLogoPath
         FROM tblOrganisations
         WHERE orgHandle = ?
           AND isActive = 1
         LIMIT 1",
        's',
        [$orgHandle]
    );

    if ($orgRow !== null
        && $orgRow !== false
        && !empty($orgRow['orgLogoPath']))
    {
        return $orgRow['orgLogoPath'];
    }

    return null;
}
