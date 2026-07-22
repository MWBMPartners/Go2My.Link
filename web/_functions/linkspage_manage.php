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
 * 📄 Go2My.Link — LinksPage Management Functions (Component C.2, #48)
 * ============================================================================
 *
 * CRUD backend for the admin dashboard's LinksPage manager: page create/
 * update/delete and item add/update/delete/toggle/move — every mutation is
 * OWNERSHIP-ENFORCED against the caller-supplied acting userUID.
 *
 * 🔒 SECURITY — ownership / IDOR:
 *   - Every function below takes the ACTING user's userUID as a parameter.
 *     Callers (the admin dashboard pages) must ALWAYS pass
 *     getCurrentUser()['userUID'] — never a client-supplied value from
 *     $_POST/$_GET — as that argument. A page/item row is only ever
 *     read, updated, deleted, or reordered when its owning userUID (for a
 *     page) or its PARENT page's owning userUID (for an item) matches the
 *     acting user. There is no "org-wide" management here: a member of the
 *     same organisation cannot touch another member's LinksPage.
 *   - Ownership is enforced IN THE SQL itself (a `userUID = ?` / a
 *     correlated `pageUID IN (SELECT pageUID FROM tblLinksPages WHERE
 *     userUID = ?)` clause on every read AND every write) — never checked
 *     only in PHP after an unscoped query. A row that does not belong to the
 *     caller behaves EXACTLY like a row that does not exist at all (a
 *     single, generic "not found" outcome), so this layer never leaks
 *     whether a given pageUID/itemUID exists for someone else.
 *
 * 🔒 SECURITY — structured fields vs. gated custom HTML:
 *   - The page/item CRUD fields are all discrete, validated, structured values
 *     (slug, title, description, avatar URL, template selection, hex colour,
 *     font family, a fixed social-network URL set, item title/url/description/
 *     icon) — never free-form HTML.
 *   - The ONLY free-form HTML path is the Component C.6 (#49) custom-HTML/CSS
 *     editor: g2ml_linkspageManageSaveCustomHTML() /
 *     g2ml_linkspageManageSaveCustomHTMLFromUpload(), sharing
 *     _g2ml_linkspageManageStoreSanitisedCustom(). Every such write is
 *     ownership-checked, GATED (operator kill-switch + premium hasCustomHTML
 *     entitlement, both via g2ml_linkspageCustomHtmlAllowedForOrg()), and
 *     SANITISED on input with g2ml_sanitiseUserHTML()/g2ml_sanitiseUserCSS()
 *     (web/_functions/html_sanitiser.php) BEFORE storage — the SANITISED form
 *     is what lands in tblLinksPages.customHTML/customCSS, never the raw
 *     submission. It is re-sanitised again on output by the renderer and served
 *     under a strict `script-src 'none'` CSP.
 *
 * 🔒 SECURITY — template picker + owner preview (C.3, #47):
 *   - g2ml_linkspageManageRenderTemplateCardThumbnail() builds each picker
 *     card's LIVE-rendered thumbnail by calling the Component C renderer's
 *     g2ml_renderLinksPage() (web/Lnks.page/_functions/linkspage_renderer.php)
 *     — REUSED, never reimplemented, so the exact same escaping/URL-allowlist/
 *     hex/font validation the public page uses also protects this preview.
 *     It is guarded by function_exists('g2ml_renderLinksPage') and falls
 *     back to the template's templateThumbnail image (or a static
 *     placeholder) whenever the renderer has not been loaded by the caller —
 *     this file itself never require()s the Component C renderer (Component
 *     A/Admin and Component C remain independently requirable trees; only
 *     the calling admin page decides to load the renderer).
 *   - g2ml_linkspageManageBuildTemplatePreviewSampleModel() uses ONLY a
 *     fixed, hard-coded sample name/bio/links/social — never the current
 *     user's in-progress form input — so a template preview can never carry
 *     anything an authenticated user typed before it was validated/saved.
 *   - The OWNER PAGE preview itself (a live render of a user's own, possibly
 *     UNPUBLISHED page) is assembled by
 *     web/Lnks.page/_functions/linkspage_resolver.php's
 *     g2ml_linkspageBuildOwnerPreviewModel() — NOT by this file. That
 *     function takes an ALREADY ownership-verified page/items pair (see
 *     g2ml_linkspageManageGetPageForOwner() / g2ml_linkspageManageListItemsForPage()
 *     below) and never queries tblLinksPages itself, so it can never be used
 *     to loosen the public resolver's `isPublished = 1` filter — the caller
 *     (the admin preview route) is solely responsible for the ownership check.
 *
 * 🔒 SECURITY — validation parity with the PUBLIC renderer:
 *   - slug / hex-colour / font-family validation here is a DELIBERATE mirror
 *     of web/Lnks.page/_functions/linkspage_resolver.php
 *     (g2ml_linkspageIsValidSlug) and linkspage_renderer.php
 *     (g2ml_linkspageValidateHexColour / g2ml_linkspageValidateFontFamily) —
 *     same regexes, same length limits. The two files are NOT require'd into
 *     one another (Component A admin vs Component C public renderer are
 *     separate deployable trees; only web/_functions/* is shared across all
 *     3 components per project convention), so the logic is duplicated
 *     on purpose with this cross-reference rather than a cross-component
 *     require_once. A value this file accepts is guaranteed to also be
 *     accepted (and rendered identically) by the public renderer.
 *   - Item/avatar/icon/social-link URLs are validated with the shared
 *     g2ml_sanitiseURL() (web/_functions/security.php) http/https allowlist —
 *     the SAME function the public renderer falls back to when
 *     g2ml_sanitiseURL() is loaded, so a value saved here is renderable.
 *
 * Functions:
 *   Validators:
 *     - g2ml_linkspageManageIsValidSlug()
 *     - g2ml_linkspageManageValidateHexColour()
 *     - g2ml_linkspageManageValidateFontFamily()
 *     - g2ml_linkspageManageValidateSocialLinks()
 *   Templates (Component C.2/#48; picker + live preview added by C.3/#47):
 *     - g2ml_linkspageManageListSystemTemplates()
 *     - g2ml_linkspageManageIsValidSystemTemplate()
 *     - g2ml_linkspageManageBuildTemplatePreviewSampleModel()
 *     - g2ml_linkspageManageRenderTemplateCardThumbnail()
 *   Pages:
 *     - g2ml_linkspageManageListPagesForUser()
 *     - g2ml_linkspageManageGetPageForOwner()
 *     - g2ml_linkspageManageCountActivePagesForUser()
 *     - g2ml_linkspageManageCreatePage()
 *     - g2ml_linkspageManageUpdatePage()
 *     - g2ml_linkspageManageSetPublished()
 *     - g2ml_linkspageManageDeletePage()
 *   Items:
 *     - g2ml_linkspageManageListItemsForPage()
 *     - g2ml_linkspageManageGetItemForOwner()
 *     - g2ml_linkspageManageListShortURLsForUser()
 *     - _g2ml_linkspageManageResolveRequiresAgeGate() — C.5/#50 auto-flag resolver
 *     - g2ml_linkspageManageAddItem()
 *     - g2ml_linkspageManageUpdateItem()
 *     - g2ml_linkspageManageDeleteItem()
 *     - g2ml_linkspageManageToggleItemActive()
 *     - g2ml_linkspageManageMoveItem()
 *
 * 🔒 SECURITY / PRIVACY — age verification auto-flag (C.5, #50):
 *   - g2ml_linkspageManageAddItem() / g2ml_linkspageManageUpdateItem() both
 *     resolve the item's FINAL requiresAgeGate value via
 *     _g2ml_linkspageManageResolveRequiresAgeGate(): the owner's own
 *     submitted checkbox choice is honoured, EXCEPT that a destination whose
 *     host matches the curated/operator-configured adult-domain allowlist
 *     (web/_functions/adult_content.php's g2ml_isAdultDomain()) always forces
 *     the gate ON — a known-adult destination cannot be silently left
 *     unprotected by leaving a form checkbox unticked. For any other
 *     destination the owner has full manual control (their checkbox choice
 *     is used as-is), which is the "the owner can still toggle it" freedom.
 *   - No date of birth or other personal data is collected/stored anywhere
 *     in this file — requiresAgeGate is a single boolean column.
 *
 * Dependencies: db_query.php (dbSelect/dbSelectOne/dbInsert/dbUpdate/dbDelete/
 *               dbBeginTransaction/dbCommit/dbRollback/dbLastErrno), security.php
 *               (g2ml_sanitiseInput/g2ml_sanitiseURL), entitlements.php
 *               (g2ml_checkLimit), activity_logger.php (logActivity) — all
 *               already loaded by page_init.php before this file.
 *               OPTIONAL (C.3, #47): g2ml_linkspageManageRenderTemplateCardThumbnail()
 *               calls the Component C renderer's g2ml_renderLinksPage() when the
 *               CALLING admin page has loaded it (web/Lnks.page/_functions/
 *               linkspage_renderer.php) — guarded by function_exists(), never
 *               require()d from this file itself.
 *               OPTIONAL (C.5, #50): _g2ml_linkspageManageResolveRequiresAgeGate()
 *               calls web/_functions/adult_content.php's g2ml_isAdultDomain()
 *               when loaded — guarded by function_exists(); page_init.php
 *               already loads adult_content.php application-wide.
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    1.1.0
 * @since      v1.2.0 — Phase 8 (#48; age-gate auto-flag #50)
 *
 * 📖 References:
 *     - Schema:          web/_sql/schema/032_linkspage.sql
 *     - Public resolver:  web/Lnks.page/_functions/linkspage_resolver.php (#45)
 *     - Public renderer:  web/Lnks.page/_functions/linkspage_renderer.php (#45)
 *     - Entitlement gate: web/_functions/entitlements.php (#146) — maxLinksPages
 *     - Adult-domain detection / age-gate cookie: web/_functions/adult_content.php (#50)
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
// 📋 Constants
// ============================================================================

/**
 * The fixed, allowlisted set of social-network fields the management form
 * exposes, and their display labels. Deliberately mirrors (a subset of) the
 * public renderer's own allowlist
 * (g2ml_linkspageAllowedSocialNetworks() in linkspage_renderer.php) so every
 * value saved here is guaranteed to be recognised and rendered publicly.
 * 'x' is omitted here as a distinct field — it is only an alternate JSON key
 * the renderer also accepts; the form offers a single 'twitter' field.
 *
 * NOTE: every value (including 'email') must be an http/https URL per the
 * renderer's own scheme allowlist — the renderer never supports mailto:
 * links, so an "Email" field here is a link TO a contact page, not a mailto:
 * address. This is documented in the form's help text.
 */
if (!defined('G2ML_LINKSPAGE_MANAGE_SOCIAL_NETWORKS'))
{
    define('G2ML_LINKSPAGE_MANAGE_SOCIAL_NETWORKS', [
        'twitter'   => 'X / Twitter',
        'instagram' => 'Instagram',
        'facebook'  => 'Facebook',
        'linkedin'  => 'LinkedIn',
        'youtube'   => 'YouTube',
        'tiktok'    => 'TikTok',
        'github'    => 'GitHub',
        'website'   => 'Website',
        'email'     => 'Email (link to a contact page)',
    ]);
}

/** Application-level cap on pageDescription / itemDescription length (TEXT column, no DB-enforced cap). */
if (!defined('G2ML_LINKSPAGE_MANAGE_DESCRIPTION_MAX_LENGTH'))
{
    define('G2ML_LINKSPAGE_MANAGE_DESCRIPTION_MAX_LENGTH', 2000);
}

// ============================================================================
// ✅ Validators
// ============================================================================

/**
 * Validate a LinksPage slug's shape — see the file header's parity note.
 *
 * Mirrors web/Lnks.page/_functions/linkspage_resolver.php's
 * g2ml_linkspageIsValidSlug() exactly: URL-safe characters only, 1–100
 * characters, matching the UNIQUE `slug` column's VARCHAR(100) width.
 *
 * @param  string $slug
 * @return bool
 */
function g2ml_linkspageManageIsValidSlug(string $slug): bool
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

/**
 * Validate a hex colour literal — see the file header's parity note.
 *
 * Mirrors web/Lnks.page/_functions/linkspage_renderer.php's
 * g2ml_linkspageValidateHexColour() exactly.
 *
 * @param  string|null $colour
 * @return string|false  The trimmed, validated colour, or false when invalid.
 */
function g2ml_linkspageManageValidateHexColour(?string $colour): string|false
{
    if ($colour === null)
    {
        return false;
    }

    $trimmed = trim($colour);

    if (preg_match('/^#[0-9A-Fa-f]{3,6}$/', $trimmed) !== 1)
    {
        return false;
    }

    return $trimmed;
}

/**
 * Validate a font-family value — see the file header's parity note.
 *
 * Mirrors web/Lnks.page/_functions/linkspage_renderer.php's
 * g2ml_linkspageValidateFontFamily() exactly: only letters, digits, spaces,
 * commas, hyphens, and single/double quotes are permitted — no `;`, `{`,
 * `}`, `(`, `)`, so a value can never break out of a CSS declaration.
 *
 * @param  string|null $fontFamily
 * @return string|false  The trimmed, validated value, or false when invalid/absent.
 */
function g2ml_linkspageManageValidateFontFamily(?string $fontFamily): string|false
{
    if ($fontFamily === null)
    {
        return false;
    }

    $trimmed = trim($fontFamily);

    if ($trimmed === '')
    {
        return false;
    }

    if (strlen($trimmed) > 100)
    {
        return false;
    }

    if (preg_match('/^[A-Za-z0-9 ,\'"\-]+$/', $trimmed) !== 1)
    {
        return false;
    }

    return $trimmed;
}

/**
 * Validate a raw social-links submission (e.g. straight from $_POST) down to
 * a clean associative array safe to json_encode() into tblLinksPages.socialLinks.
 *
 * Only keys present in G2ML_LINKSPAGE_MANAGE_SOCIAL_NETWORKS are considered —
 * any other key is silently ignored (closed allowlist, not a denylist). A
 * blank value for an allowlisted key is dropped (the network is simply not
 * set). A non-blank value that fails g2ml_sanitiseURL()'s http/https scheme
 * validation is also dropped rather than raising a hard error — it is a
 * cosmetic field, and this mirrors the public renderer's own "skip, don't
 * fail" behaviour for an individual bad link.
 *
 * @param  array<string, mixed> $rawSocialLinks  e.g. $_POST['social'] shape: ['twitter' => 'https://...', ...]
 * @return array<string, string>  Validated network => URL pairs (may be empty).
 */
function g2ml_linkspageManageValidateSocialLinks(array $rawSocialLinks): array
{
    $validated = [];

    foreach (G2ML_LINKSPAGE_MANAGE_SOCIAL_NETWORKS as $networkKey => $networkLabel)
    {
        if (!isset($rawSocialLinks[$networkKey]) || !is_string($rawSocialLinks[$networkKey]))
        {
            continue;
        }

        $rawValue = trim($rawSocialLinks[$networkKey]);

        if ($rawValue === '')
        {
            continue;
        }

        $sanitisedURL = g2ml_sanitiseURL($rawValue);

        if ($sanitisedURL === false || $sanitisedURL === '')
        {
            continue;
        }

        $validated[$networkKey] = $sanitisedURL;
    }

    return $validated;
}

// ============================================================================
// 🎨 System templates
// ============================================================================

/**
 * List every active SYSTEM template, for the management form's visual picker
 * (C.3, #47 — previously a plain dropdown, C.2/#48).
 *
 * Only isSystem = 1 rows are ever offered — user-authored/custom templates
 * (not built anywhere yet) would need a completely separate ownership-aware
 * listing, not this one.
 *
 * templateHTML/templateCSS/templateThumbnail are included (widened from the
 * original name/description-only SELECT) so the picker can render a LIVE
 * thumbnail of each template with sample data — see
 * g2ml_linkspageManageRenderTemplateCardThumbnail(). These are SYSTEM
 * (isSystem = 1, operator-authored, trusted) template bodies, never
 * user-supplied HTML.
 *
 * @return array
 */
function g2ml_linkspageManageListSystemTemplates(): array
{
    $rows = dbSelect(
        "SELECT templateUID, templateName, templateDescription, templateHTML, templateCSS, templateThumbnail
         FROM tblLinksPageTemplates
         WHERE isSystem = 1 AND isActive = 1
         ORDER BY sortOrder ASC, templateUID ASC"
    );

    if ($rows === false)
    {
        return [];
    }

    return $rows;
}

/**
 * Confirm a templateUID refers to an active SYSTEM template row.
 *
 * @param  int $templateUID
 * @return bool
 */
function g2ml_linkspageManageIsValidSystemTemplate(int $templateUID): bool
{
    $row = dbSelectOne(
        "SELECT templateUID FROM tblLinksPageTemplates WHERE templateUID = ? AND isSystem = 1 AND isActive = 1 LIMIT 1",
        'i',
        [$templateUID]
    );

    if ($row === null || $row === false)
    {
        return false;
    }

    return true;
}

/**
 * Build a FIXED, safe sample page model for the template picker's live-render
 * thumbnails (C.3, #47).
 *
 * Deliberately static: a hard-coded sample name/bio/items/social — NEVER the
 * current user's in-progress create/edit form input, and never anything
 * read from the database beyond the template row itself. This guarantees a
 * template preview is identical for every user and can never carry
 * unvalidated data.
 *
 * @param  array $templateRow  A row shaped like g2ml_linkspageManageListSystemTemplates()'s
 *                              return (must include templateHTML/templateCSS).
 * @return array  ['page' => array, 'template' => array, 'items' => array] ready for g2ml_renderLinksPage().
 */
function g2ml_linkspageManageBuildTemplatePreviewSampleModel(array $templateRow): array
{
    $sampleTemplateUID = null;

    if (isset($templateRow['templateUID']))
    {
        $sampleTemplateUID = (int) $templateRow['templateUID'];
    }

    return [
        'page' => [
            'pageTitle'        => 'Jane Doe',
            'pageDescription'  => 'Photographer and digital creator — sharing my favourite links below.',
            'avatarPath'       => null,
            'templateUID'      => $sampleTemplateUID,
            'themeColour'      => '#1E88E5',
            'backgroundColour' => '#FFFFFF',
            'fontFamily'       => null,
            'showSocialIcons'  => 1,
            'socialLinks'      => json_encode([
                'twitter'   => 'https://example.com/sample-profile',
                'instagram' => 'https://example.com/sample-profile',
            ]),
        ],
        'template' => $templateRow,
        'items'    => [
            [
                'itemUID'         => 0,
                'itemTitle'       => 'My Website',
                'itemURL'         => 'https://example.com/website',
                'itemDescription' => null,
                'itemIcon'        => null,
                'faviconCacheURL' => null,
                'requiresAgeGate' => 0,
            ],
            [
                'itemUID'         => 0,
                'itemTitle'       => 'Latest Project',
                'itemURL'         => 'https://example.com/project',
                'itemDescription' => 'A short sample description',
                'itemIcon'        => null,
                'faviconCacheURL' => null,
                'requiresAgeGate' => 0,
            ],
            [
                'itemUID'         => 0,
                'itemTitle'       => 'Get in Touch',
                'itemURL'         => 'https://example.com/contact',
                'itemDescription' => null,
                'itemIcon'        => null,
                'faviconCacheURL' => null,
                'requiresAgeGate' => 0,
            ],
        ],
    ];
}

/**
 * Render one template picker card's thumbnail markup (C.3, #47).
 *
 * Prefers a LIVE render of the template (via the Component C renderer's
 * g2ml_renderLinksPage() — REUSED, never reimplemented here) with the fixed
 * sample model above, embedded as a sandboxed, scaled-down `<iframe srcdoc>`
 * so visitors compare templates using the SAME escaping/validation the
 * public page uses. Falls back to the template's own templateThumbnail
 * image, and finally to a static placeholder icon, whenever a live render is
 * not practical (the renderer not loaded, or no templateHTML stored).
 *
 * The iframe is `aria-hidden="true"` and `tabindex="-1"` — it is purely
 * decorative; the accessible name of the picker card comes from the visible
 * template name/description text in its `<label>`, not from this markup.
 * `sandbox=""` (no tokens) fully sandboxes the framed content: no scripts,
 * no forms, no top-navigation, unique/opaque origin — appropriate for
 * static, escaped, system-template HTML that never needs script execution.
 *
 * @param  array $templateRow  A row shaped like g2ml_linkspageManageListSystemTemplates()'s
 *                              return (templateUID/templateName/templateHTML/
 *                              templateCSS/templateThumbnail).
 * @return string  Safe HTML for the card's thumbnail area (always non-empty).
 */
function g2ml_linkspageManageRenderTemplateCardThumbnail(array $templateRow): string
{
    $templateHTMLValue = '';

    if (isset($templateRow['templateHTML']) && is_string($templateRow['templateHTML']))
    {
        $templateHTMLValue = trim($templateRow['templateHTML']);
    }

    if ($templateHTMLValue !== '' && function_exists('g2ml_renderLinksPage'))
    {
        $sampleModel = g2ml_linkspageManageBuildTemplatePreviewSampleModel($templateRow);
        $renderedHTML = g2ml_renderLinksPage($sampleModel);

        // htmlspecialchars(ENT_QUOTES) makes the ENTIRE rendered document safe
        // to embed as the value of the srcdoc="..." attribute — this is
        // attribute escaping of the outer page, not a weakening of the
        // renderer's own escaping of the sample data inside the document.
        $safeSrcDoc = htmlspecialchars($renderedHTML, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<span class="lp-picker-thumb lp-picker-thumb-live">'
            . '<iframe class="lp-picker-thumb-iframe" srcdoc="' . $safeSrcDoc . '" '
            . 'tabindex="-1" aria-hidden="true" loading="lazy" scrolling="no" sandbox="" '
            . 'title=""></iframe>'
            . '</span>';
    }

    $templateThumbnailValue = '';

    if (isset($templateRow['templateThumbnail']) && is_string($templateRow['templateThumbnail']))
    {
        $templateThumbnailValue = trim($templateRow['templateThumbnail']);
    }

    if ($templateThumbnailValue !== '')
    {
        return '<span class="lp-picker-thumb lp-picker-thumb-static">'
            . '<img src="' . htmlspecialchars($templateThumbnailValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" alt="" loading="lazy">'
            . '</span>';
    }

    return '<span class="lp-picker-thumb lp-picker-thumb-placeholder" aria-hidden="true">'
        . '<i class="fas fa-image" aria-hidden="true"></i>'
        . '</span>';
}

// ============================================================================
// 📄 Pages — reads
// ============================================================================

/**
 * List every LinksPage owned by a user (both published and draft), most
 * recently created first.
 *
 * @param  int $userUID  The ACTING user's own userUID — never a client-supplied value.
 * @return array
 */
function g2ml_linkspageManageListPagesForUser(int $userUID): array
{
    $rows = dbSelect(
        "SELECT pageUID, slug, pageTitle, pageDescription, templateUID, isPublished, isActive, createdAt, updatedAt
         FROM tblLinksPages
         WHERE userUID = ?
         ORDER BY createdAt DESC",
        'i',
        [$userUID]
    );

    if ($rows === false)
    {
        return [];
    }

    return $rows;
}

/**
 * Fetch ONE LinksPage, scoped to its owning user.
 *
 * 🔒 Ownership is enforced IN the query (`userUID = ?`) — a pageUID that
 * belongs to someone else returns null, identically to a pageUID that does
 * not exist at all.
 *
 * @param  int $pageUID
 * @param  int $userUID  The ACTING user's own userUID.
 * @return array|null
 */
function g2ml_linkspageManageGetPageForOwner(int $pageUID, int $userUID): ?array
{
    $row = dbSelectOne(
        "SELECT pageUID, userUID, orgHandle, slug, pageTitle, pageDescription, avatarPath,
                templateUID, customHTML, customCSS, themeColour, backgroundColour, fontFamily, showSocialIcons,
                socialLinks, isPublished, isActive, createdAt, updatedAt
         FROM tblLinksPages
         WHERE pageUID = ? AND userUID = ?
         LIMIT 1",
        'ii',
        [$pageUID, $userUID]
    );

    if ($row === null || $row === false)
    {
        return null;
    }

    return $row;
}

/**
 * Count a user's own ACTIVE LinksPages — the count fed into
 * g2ml_checkLimit(orgHandle, 'maxLinksPages', ...) before a NEW page create.
 *
 * @param  int $userUID
 * @return int
 */
function g2ml_linkspageManageCountActivePagesForUser(int $userUID): int
{
    $row = dbSelectOne(
        "SELECT COUNT(*) AS cnt FROM tblLinksPages WHERE userUID = ? AND isActive = 1",
        'i',
        [$userUID]
    );

    if ($row === null || $row === false)
    {
        return 0;
    }

    return (int) $row['cnt'];
}

// ============================================================================
// 📄 Pages — internal field-validation helper (shared by create/update)
// ============================================================================

/**
 * Validate and normalise the shared structured-field subset of a page
 * create/update submission. Returns either ['ok' => true, 'fields' => [...]]
 * with every value ready to bind, or ['ok' => false, 'error' => string,
 * 'errorCode' => string] on the FIRST validation failure.
 *
 * Deliberately does NOT touch slug uniqueness or the entitlement limit —
 * those are caller-specific (create-only) concerns handled by
 * g2ml_linkspageManageCreatePage() itself.
 *
 * @param  array $input  Raw, already trim()-able submission fields.
 * @return array
 */
function _g2ml_linkspageManageValidateFields(array $input): array
{
    $slugRaw = '';

    if (isset($input['slug']) && is_string($input['slug']))
    {
        $slugRaw = trim($input['slug']);
    }

    if (!g2ml_linkspageManageIsValidSlug($slugRaw))
    {
        return [
            'ok'        => false,
            'error'     => 'Please enter a URL slug using only letters, numbers, hyphens, and underscores (1-100 characters).',
            'errorCode' => 'validation',
        ];
    }

    $pageTitleRaw = '';

    if (isset($input['pageTitle']) && is_string($input['pageTitle']))
    {
        $pageTitleRaw = trim(g2ml_sanitiseInput($input['pageTitle']));
    }

    if ($pageTitleRaw === '')
    {
        return [
            'ok'        => false,
            'error'     => 'Please enter a page title.',
            'errorCode' => 'validation',
        ];
    }

    if (mb_strlen($pageTitleRaw) > 255)
    {
        return [
            'ok'        => false,
            'error'     => 'Page title must be 255 characters or fewer.',
            'errorCode' => 'validation',
        ];
    }

    $pageDescriptionRaw = '';

    if (isset($input['pageDescription']) && is_string($input['pageDescription']))
    {
        $pageDescriptionRaw = trim(g2ml_sanitiseInput($input['pageDescription']));
    }

    if (mb_strlen($pageDescriptionRaw) > G2ML_LINKSPAGE_MANAGE_DESCRIPTION_MAX_LENGTH)
    {
        return [
            'ok'        => false,
            'error'     => 'Page description must be ' . G2ML_LINKSPAGE_MANAGE_DESCRIPTION_MAX_LENGTH . ' characters or fewer.',
            'errorCode' => 'validation',
        ];
    }

    if ($pageDescriptionRaw === '')
    {
        $pageDescriptionValue = null;
    }
    else
    {
        $pageDescriptionValue = $pageDescriptionRaw;
    }

    $avatarRaw = '';

    if (isset($input['avatarPath']) && is_string($input['avatarPath']))
    {
        $avatarRaw = trim($input['avatarPath']);
    }

    if ($avatarRaw === '')
    {
        $avatarValue = null;
    }
    else
    {
        $sanitisedAvatar = g2ml_sanitiseURL($avatarRaw);

        if ($sanitisedAvatar === false || mb_strlen($sanitisedAvatar) > 500)
        {
            return [
                'ok'        => false,
                'error'     => 'The avatar must be a valid http:// or https:// image URL.',
                'errorCode' => 'validation',
            ];
        }

        $avatarValue = $sanitisedAvatar;
    }

    $templateUIDValue = null;

    if (isset($input['templateUID']) && is_string($input['templateUID']) && trim($input['templateUID']) !== '')
    {
        $templateUIDCandidate = (int) trim($input['templateUID']);

        if (!g2ml_linkspageManageIsValidSystemTemplate($templateUIDCandidate))
        {
            return [
                'ok'        => false,
                'error'     => 'Please choose a valid template.',
                'errorCode' => 'validation',
            ];
        }

        $templateUIDValue = $templateUIDCandidate;
    }

    $themeColourRaw = '';

    if (isset($input['themeColour']) && is_string($input['themeColour']))
    {
        $themeColourRaw = trim($input['themeColour']);
    }

    if ($themeColourRaw === '')
    {
        $themeColourValue = null;
    }
    else
    {
        $validatedThemeColour = g2ml_linkspageManageValidateHexColour($themeColourRaw);

        if ($validatedThemeColour === false)
        {
            return [
                'ok'        => false,
                'error'     => 'Theme colour must be a hex value like #1E88E5.',
                'errorCode' => 'validation',
            ];
        }

        $themeColourValue = $validatedThemeColour;
    }

    $backgroundColourRaw = '';

    if (isset($input['backgroundColour']) && is_string($input['backgroundColour']))
    {
        $backgroundColourRaw = trim($input['backgroundColour']);
    }

    if ($backgroundColourRaw === '')
    {
        $backgroundColourValue = null;
    }
    else
    {
        $validatedBackgroundColour = g2ml_linkspageManageValidateHexColour($backgroundColourRaw);

        if ($validatedBackgroundColour === false)
        {
            return [
                'ok'        => false,
                'error'     => 'Background colour must be a hex value like #FFFFFF.',
                'errorCode' => 'validation',
            ];
        }

        $backgroundColourValue = $validatedBackgroundColour;
    }

    $fontFamilyRaw = '';

    if (isset($input['fontFamily']) && is_string($input['fontFamily']))
    {
        $fontFamilyRaw = trim($input['fontFamily']);
    }

    if ($fontFamilyRaw === '')
    {
        $fontFamilyValue = null;
    }
    else
    {
        $validatedFontFamily = g2ml_linkspageManageValidateFontFamily($fontFamilyRaw);

        if ($validatedFontFamily === false)
        {
            return [
                'ok'        => false,
                'error'     => 'Font family may only contain letters, numbers, spaces, commas, hyphens, and quotes.',
                'errorCode' => 'validation',
            ];
        }

        $fontFamilyValue = $validatedFontFamily;
    }

    if (isset($input['showSocialIcons']) && $input['showSocialIcons'] === true)
    {
        $showSocialIconsValue = 1;
    }
    else
    {
        $showSocialIconsValue = 0;
    }

    $rawSocialLinksInput = [];

    if (isset($input['socialLinks']) && is_array($input['socialLinks']))
    {
        $rawSocialLinksInput = $input['socialLinks'];
    }

    $validatedSocialLinks = g2ml_linkspageManageValidateSocialLinks($rawSocialLinksInput);

    if (count($validatedSocialLinks) === 0)
    {
        $socialLinksValue = null;
    }
    else
    {
        $socialLinksValue = json_encode($validatedSocialLinks);
    }

    if (isset($input['isPublished']) && $input['isPublished'] === true)
    {
        $isPublishedValue = 1;
    }
    else
    {
        $isPublishedValue = 0;
    }

    return [
        'ok'     => true,
        'fields' => [
            'slug'             => $slugRaw,
            'pageTitle'        => $pageTitleRaw,
            'pageDescription'  => $pageDescriptionValue,
            'avatarPath'       => $avatarValue,
            'templateUID'      => $templateUIDValue,
            'themeColour'      => $themeColourValue,
            'backgroundColour' => $backgroundColourValue,
            'fontFamily'       => $fontFamilyValue,
            'showSocialIcons'  => $showSocialIconsValue,
            'socialLinks'      => $socialLinksValue,
            'isPublished'      => $isPublishedValue,
        ],
    ];
}

// ============================================================================
// 📄 Pages — mutations
// ============================================================================

/**
 * Create a NEW LinksPage owned by the acting user, gated by the
 * maxLinksPages entitlement.
 *
 * @param  int    $userUID    The ACTING user's own userUID — never a client-supplied value.
 * @param  string $orgHandle  The acting user's OWN orgHandle (from getCurrentUser(), not client input).
 * @param  array  $input      Raw form fields — see _g2ml_linkspageManageValidateFields().
 * @return array  ['success' => bool, 'pageUID' => int|null, 'error' => string|null, 'errorCode' => string|null]
 */
function g2ml_linkspageManageCreatePage(int $userUID, string $orgHandle, array $input): array
{
    $validation = _g2ml_linkspageManageValidateFields($input);

    if ($validation['ok'] === false)
    {
        return [
            'success'   => false,
            'pageUID'   => null,
            'error'     => $validation['error'],
            'errorCode' => $validation['errorCode'],
        ];
    }

    $currentPageCount = g2ml_linkspageManageCountActivePagesForUser($userUID);
    $limitCheck        = g2ml_checkLimit($orgHandle, 'maxLinksPages', $currentPageCount);

    if ($limitCheck['allowed'] === false)
    {
        $limitMessage = 'You have reached your plan\'s LinksPage limit';

        if ($limitCheck['limit'] !== null)
        {
            $limitMessage = $limitMessage . ' of ' . $limitCheck['limit'];
        }

        $limitMessage = $limitMessage . '. Please upgrade your plan to create more LinksPages.';

        return [
            'success'   => false,
            'pageUID'   => null,
            'error'     => $limitMessage,
            'errorCode' => 'limit_reached',
        ];
    }

    $fields = $validation['fields'];

    $insertedPageUID = dbInsert(
        "INSERT INTO tblLinksPages
            (userUID, orgHandle, slug, pageTitle, pageDescription, avatarPath, templateUID,
             themeColour, backgroundColour, fontFamily, showSocialIcons, socialLinks, isPublished)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        'isssssisssiii',
        [
            $userUID,
            $orgHandle,
            $fields['slug'],
            $fields['pageTitle'],
            $fields['pageDescription'],
            $fields['avatarPath'],
            $fields['templateUID'],
            $fields['themeColour'],
            $fields['backgroundColour'],
            $fields['fontFamily'],
            $fields['showSocialIcons'],
            $fields['socialLinks'],
            $fields['isPublished'],
        ]
    );

    if ($insertedPageUID === false)
    {
        if (function_exists('dbLastErrno') && dbLastErrno() === 1062)
        {
            return [
                'success'   => false,
                'pageUID'   => null,
                'error'     => 'That URL slug is already taken. Please choose a different one.',
                'errorCode' => 'slug_taken',
            ];
        }

        return [
            'success'   => false,
            'pageUID'   => null,
            'error'     => 'Could not create the LinksPage. Please try again.',
            'errorCode' => 'server_error',
        ];
    }

    if (function_exists('logActivity'))
    {
        logActivity('create_linkspage', 'success', 200, [
            'userUID' => $userUID,
            'logData' => ['pageUID' => $insertedPageUID, 'slug' => $fields['slug']],
        ]);
    }

    return [
        'success'   => true,
        'pageUID'   => (int) $insertedPageUID,
        'error'     => null,
        'errorCode' => null,
    ];
}

/**
 * Update an EXISTING LinksPage — ownership-checked (the page must belong to
 * the acting user). No entitlement re-check (a page already counted against
 * the limit at creation time is never re-blocked by editing it).
 *
 * @param  int   $userUID  The ACTING user's own userUID.
 * @param  int   $pageUID
 * @param  array $input    Raw form fields — see _g2ml_linkspageManageValidateFields().
 * @return array  ['success' => bool, 'error' => string|null, 'errorCode' => string|null]
 */
function g2ml_linkspageManageUpdatePage(int $userUID, int $pageUID, array $input): array
{
    $existingPage = g2ml_linkspageManageGetPageForOwner($pageUID, $userUID);

    if ($existingPage === null)
    {
        return [
            'success'   => false,
            'error'     => 'LinksPage not found, or you do not have permission to edit it.',
            'errorCode' => 'not_found',
        ];
    }

    $validation = _g2ml_linkspageManageValidateFields($input);

    if ($validation['ok'] === false)
    {
        return [
            'success'   => false,
            'error'     => $validation['error'],
            'errorCode' => $validation['errorCode'],
        ];
    }

    $fields = $validation['fields'];

    // 🔒 Ownership enforced again on the UPDATE itself (defence in depth,
    // beyond the pre-check above) via "WHERE pageUID = ? AND userUID = ?".
    $affectedRows = dbUpdate(
        "UPDATE tblLinksPages SET
            slug = ?, pageTitle = ?, pageDescription = ?, avatarPath = ?, templateUID = ?,
            themeColour = ?, backgroundColour = ?, fontFamily = ?, showSocialIcons = ?,
            socialLinks = ?, isPublished = ?
         WHERE pageUID = ? AND userUID = ?",
        'sssssssiisiii',
        [
            $fields['slug'],
            $fields['pageTitle'],
            $fields['pageDescription'],
            $fields['avatarPath'],
            $fields['templateUID'],
            $fields['themeColour'],
            $fields['backgroundColour'],
            $fields['fontFamily'],
            $fields['showSocialIcons'],
            $fields['socialLinks'],
            $fields['isPublished'],
            $pageUID,
            $userUID,
        ]
    );

    if ($affectedRows === false)
    {
        if (function_exists('dbLastErrno') && dbLastErrno() === 1062)
        {
            return [
                'success'   => false,
                'error'     => 'That URL slug is already taken. Please choose a different one.',
                'errorCode' => 'slug_taken',
            ];
        }

        return [
            'success'   => false,
            'error'     => 'Could not update the LinksPage. Please try again.',
            'errorCode' => 'server_error',
        ];
    }

    if (function_exists('logActivity'))
    {
        logActivity('update_linkspage', 'success', 200, [
            'userUID' => $userUID,
            'logData' => ['pageUID' => $pageUID, 'slug' => $fields['slug']],
        ]);
    }

    return [
        'success'   => true,
        'error'     => null,
        'errorCode' => null,
    ];
}

/**
 * Quickly flip a page's isPublished flag — ownership-checked. Used by the
 * list view's one-click publish/unpublish action, so a quick status change
 * does not require resubmitting the entire edit form.
 *
 * @param  int  $userUID      The ACTING user's own userUID.
 * @param  int  $pageUID
 * @param  bool $isPublished  The new desired published state.
 * @return array  ['success' => bool, 'error' => string|null]
 */
function g2ml_linkspageManageSetPublished(int $userUID, int $pageUID, bool $isPublished): array
{
    if ($isPublished === true)
    {
        $publishedValue = 1;
    }
    else
    {
        $publishedValue = 0;
    }

    $affectedRows = dbUpdate(
        "UPDATE tblLinksPages SET isPublished = ? WHERE pageUID = ? AND userUID = ?",
        'iii',
        [$publishedValue, $pageUID, $userUID]
    );

    if ($affectedRows === false || $affectedRows === 0)
    {
        return [
            'success' => false,
            'error'   => 'LinksPage not found, or you do not have permission to edit it.',
        ];
    }

    if (function_exists('logActivity'))
    {
        logActivity('toggle_linkspage_published', 'success', 200, [
            'userUID' => $userUID,
            'logData' => ['pageUID' => $pageUID, 'isPublished' => $publishedValue],
        ]);
    }

    return [
        'success' => true,
        'error'   => null,
    ];
}

/**
 * Delete a LinksPage — ownership-checked. Cascades to its items via
 * FK_item_page ON DELETE CASCADE (see 032_linkspage.sql).
 *
 * @param  int $userUID  The ACTING user's own userUID.
 * @param  int $pageUID
 * @return array  ['success' => bool, 'error' => string|null]
 */
function g2ml_linkspageManageDeletePage(int $userUID, int $pageUID): array
{
    $deletedRows = dbDelete(
        "DELETE FROM tblLinksPages WHERE pageUID = ? AND userUID = ?",
        'ii',
        [$pageUID, $userUID]
    );

    if ($deletedRows === false || $deletedRows === 0)
    {
        return [
            'success' => false,
            'error'   => 'LinksPage not found, or you do not have permission to delete it.',
        ];
    }

    if (function_exists('logActivity'))
    {
        logActivity('delete_linkspage', 'success', 200, [
            'userUID' => $userUID,
            'logData' => ['pageUID' => $pageUID],
        ]);
    }

    return [
        'success' => true,
        'error'   => null,
    ];
}

// ============================================================================
// 🔗 Items — reads
// ============================================================================

/**
 * List every item on a page, ownership-checked via a JOIN back to
 * tblLinksPages so an itemUID/pageUID belonging to another user can never be
 * enumerated through this function.
 *
 * @param  int $pageUID
 * @param  int $userUID  The ACTING user's own userUID.
 * @return array
 */
function g2ml_linkspageManageListItemsForPage(int $pageUID, int $userUID): array
{
    $rows = dbSelect(
        "SELECT i.itemUID, i.pageUID, i.urlUID, i.itemTitle, i.itemURL, i.itemDescription,
                i.itemIcon, i.requiresAgeGate, i.sortOrder, i.isActive
         FROM tblLinksPageItems i
         INNER JOIN tblLinksPages p ON i.pageUID = p.pageUID
         WHERE i.pageUID = ? AND p.userUID = ?
         ORDER BY i.sortOrder ASC, i.itemUID ASC",
        'ii',
        [$pageUID, $userUID]
    );

    if ($rows === false)
    {
        return [];
    }

    return $rows;
}

/**
 * Fetch ONE item, ownership-checked via a JOIN back to the OWNING page.
 *
 * @param  int $itemUID
 * @param  int $userUID  The ACTING user's own userUID.
 * @return array|null
 */
function g2ml_linkspageManageGetItemForOwner(int $itemUID, int $userUID): ?array
{
    $row = dbSelectOne(
        "SELECT i.itemUID, i.pageUID, i.urlUID, i.itemTitle, i.itemURL, i.itemDescription,
                i.itemIcon, i.requiresAgeGate, i.sortOrder, i.isActive
         FROM tblLinksPageItems i
         INNER JOIN tblLinksPages p ON i.pageUID = p.pageUID
         WHERE i.itemUID = ? AND p.userUID = ?
         LIMIT 1",
        'ii',
        [$itemUID, $userUID]
    );

    if ($row === null || $row === false)
    {
        return null;
    }

    return $row;
}

/**
 * List a user's own active short URLs, for the "add item from an existing
 * short URL" picker. Deliberately scoped to createdByUserUID (the same
 * ownership boundary the Links dashboard itself uses — see
 * web/Go2My.Link/_admin/public_html/pages/links/index.php), so a LinksPage
 * item can never be created by referencing another org member's link.
 * Capped at 200 rows — a picker dropdown, not a full listing page.
 *
 * @param  int $userUID  The ACTING user's own userUID.
 * @return array
 */
function g2ml_linkspageManageListShortURLsForUser(int $userUID): array
{
    $rows = dbSelect(
        "SELECT urlUID, shortCode, destinationURL, title, orgHandle
         FROM tblShortURLs
         WHERE createdByUserUID = ? AND isActive = 1
         ORDER BY createdAt DESC
         LIMIT 200",
        'i',
        [$userUID]
    );

    if ($rows === false)
    {
        return [];
    }

    return $rows;
}

// ============================================================================
// 🔞 Items — age-gate auto-flag resolver (Component C.5, #50)
// ============================================================================

/**
 * Resolve the FINAL requiresAgeGate value for an item being added or edited.
 *
 * Auto-flag: if the resolved destination URL's host matches the curated (or
 * operator-configured) adult-domain allowlist — see
 * web/_functions/adult_content.php's g2ml_isAdultDomain() — the gate is
 * FORCED on regardless of the owner's submitted checkbox state. This is a
 * deliberate, protective default: automatic age-gating for a verified
 * known-adult destination cannot be silently bypassed by simply leaving the
 * item form's checkbox unticked.
 *
 * For any OTHER destination, the owner's own explicit checkbox choice is
 * honoured exactly as submitted — this is the "the owner can still toggle
 * it" freedom described in the file header: full manual control over the
 * gate for anything not on the curated list.
 *
 * @param  bool        $ownerRequestedGate  Whether the item form's
 *                                           requiresAgeGate checkbox was
 *                                           submitted checked.
 * @param  string|null $resolvedURL         The item's FINAL resolved
 *                                           destination URL (already
 *                                           scheme-validated by the caller).
 * @return int  1 or 0, ready to bind into tblLinksPageItems.requiresAgeGate.
 */
function _g2ml_linkspageManageResolveRequiresAgeGate(bool $ownerRequestedGate, ?string $resolvedURL): int
{
    $autoDetectedAdultDomain = false;

    if (function_exists('g2ml_isAdultDomain'))
    {
        $autoDetectedAdultDomain = g2ml_isAdultDomain($resolvedURL);
    }

    if ($ownerRequestedGate === true || $autoDetectedAdultDomain === true)
    {
        return 1;
    }

    return 0;
}

// ============================================================================
// 🔗 Items — mutations
// ============================================================================

/**
 * Add an item to a page — ownership-checked (the page must belong to the
 * acting user). Two mutually exclusive sources:
 *   - 'shorturl': $input['urlUID'] must reference one of THIS user's own
 *     active short URLs (re-verified here, never trusted from the client
 *     beyond the numeric ID) — itemURL is derived server-side from the
 *     short URL's own domain + code, never taken from client input.
 *   - 'manual': $input['manualURL'] is scheme-validated via g2ml_sanitiseURL().
 *
 * requiresAgeGate (C.5, #50) is resolved via
 * _g2ml_linkspageManageResolveRequiresAgeGate() — the owner's own submitted
 * checkbox is honoured UNLESS the resolved destination matches the curated
 * adult-domain allowlist, in which case the gate is force-enabled.
 *
 * @param  int   $userUID  The ACTING user's own userUID.
 * @param  int   $pageUID
 * @param  array $input    ['source' => 'shorturl'|'manual', 'urlUID' => int|null,
 *                          'manualURL' => string|null, 'itemTitle' => string,
 *                          'itemDescription' => string|null, 'itemIcon' => string|null,
 *                          'requiresAgeGate' => bool|null]
 * @return array  ['success' => bool, 'itemUID' => int|null, 'error' => string|null]
 */
function g2ml_linkspageManageAddItem(int $userUID, int $pageUID, array $input): array
{
    $ownedPage = g2ml_linkspageManageGetPageForOwner($pageUID, $userUID);

    if ($ownedPage === null)
    {
        return [
            'success' => false,
            'itemUID' => null,
            'error'   => 'LinksPage not found, or you do not have permission to edit it.',
        ];
    }

    $source = '';

    if (isset($input['source']) && is_string($input['source']))
    {
        $source = $input['source'];
    }

    $resolvedURLUID = null;
    $resolvedItemURL = null;

    if ($source === 'shorturl')
    {
        $urlUIDCandidate = 0;

        if (isset($input['urlUID']))
        {
            $urlUIDCandidate = (int) $input['urlUID'];
        }

        if ($urlUIDCandidate <= 0)
        {
            return [
                'success' => false,
                'itemUID' => null,
                'error'   => 'Please choose one of your short URLs.',
            ];
        }

        // 🔒 Ownership re-verified here — a urlUID belonging to another user
        // (or to nobody) is rejected outright, regardless of client input.
        $ownedShortURL = dbSelectOne(
            "SELECT urlUID, shortCode, orgHandle FROM tblShortURLs WHERE urlUID = ? AND createdByUserUID = ? AND isActive = 1 LIMIT 1",
            'ii',
            [$urlUIDCandidate, $userUID]
        );

        if ($ownedShortURL === null || $ownedShortURL === false)
        {
            return [
                'success' => false,
                'itemUID' => null,
                'error'   => 'That short URL was not found, or you do not have permission to use it.',
            ];
        }

        $shortDomain = 'g2my.link';

        if (function_exists('getDefaultShortDomain'))
        {
            $shortDomain = getDefaultShortDomain((string) $ownedShortURL['orgHandle']);
        }

        $resolvedURLUID  = (int) $ownedShortURL['urlUID'];
        $resolvedItemURL = 'https://' . $shortDomain . '/' . $ownedShortURL['shortCode'];
    }
    elseif ($source === 'manual')
    {
        $manualURLRaw = '';

        if (isset($input['manualURL']) && is_string($input['manualURL']))
        {
            $manualURLRaw = trim($input['manualURL']);
        }

        $sanitisedManualURL = g2ml_sanitiseURL($manualURLRaw);

        if ($sanitisedManualURL === false)
        {
            return [
                'success' => false,
                'itemUID' => null,
                'error'   => 'Please enter a valid http:// or https:// URL.',
            ];
        }

        $resolvedURLUID  = null;
        $resolvedItemURL = $sanitisedManualURL;
    }
    else
    {
        return [
            'success' => false,
            'itemUID' => null,
            'error'   => 'Please choose a link source.',
        ];
    }

    $itemTitleRaw = '';

    if (isset($input['itemTitle']) && is_string($input['itemTitle']))
    {
        $itemTitleRaw = trim(g2ml_sanitiseInput($input['itemTitle']));
    }

    if ($itemTitleRaw === '')
    {
        return [
            'success' => false,
            'itemUID' => null,
            'error'   => 'Please enter a title for this link.',
        ];
    }

    if (mb_strlen($itemTitleRaw) > 255)
    {
        return [
            'success' => false,
            'itemUID' => null,
            'error'   => 'The link title must be 255 characters or fewer.',
        ];
    }

    $itemDescriptionRaw = '';

    if (isset($input['itemDescription']) && is_string($input['itemDescription']))
    {
        $itemDescriptionRaw = trim(g2ml_sanitiseInput($input['itemDescription']));
    }

    if (mb_strlen($itemDescriptionRaw) > G2ML_LINKSPAGE_MANAGE_DESCRIPTION_MAX_LENGTH)
    {
        return [
            'success' => false,
            'itemUID' => null,
            'error'   => 'The link description must be ' . G2ML_LINKSPAGE_MANAGE_DESCRIPTION_MAX_LENGTH . ' characters or fewer.',
        ];
    }

    if ($itemDescriptionRaw === '')
    {
        $itemDescriptionValue = null;
    }
    else
    {
        $itemDescriptionValue = $itemDescriptionRaw;
    }

    $itemIconRaw = '';

    if (isset($input['itemIcon']) && is_string($input['itemIcon']))
    {
        $itemIconRaw = trim($input['itemIcon']);
    }

    if ($itemIconRaw === '')
    {
        $itemIconValue = null;
    }
    else
    {
        $sanitisedIcon = g2ml_sanitiseURL($itemIconRaw);

        if ($sanitisedIcon === false || mb_strlen($sanitisedIcon) > 500)
        {
            return [
                'success' => false,
                'itemUID' => null,
                'error'   => 'The icon must be a valid http:// or https:// image URL.',
            ];
        }

        $itemIconValue = $sanitisedIcon;
    }

    // 🔞 C.5/#50 — resolve the auto-flag: the owner's own checkbox is
    // honoured UNLESS the destination matches the curated adult-domain
    // allowlist, in which case the gate is force-enabled. See
    // _g2ml_linkspageManageResolveRequiresAgeGate()'s docblock.
    $ownerRequestedAgeGate = false;

    if (isset($input['requiresAgeGate']) && $input['requiresAgeGate'] === true)
    {
        $ownerRequestedAgeGate = true;
    }

    $requiresAgeGateValue = _g2ml_linkspageManageResolveRequiresAgeGate($ownerRequestedAgeGate, $resolvedItemURL);

    $maxSortRow = dbSelectOne(
        "SELECT MAX(sortOrder) AS maxSort FROM tblLinksPageItems WHERE pageUID = ?",
        'i',
        [$pageUID]
    );

    $nextSortOrder = 0;

    if ($maxSortRow !== null && $maxSortRow !== false && $maxSortRow['maxSort'] !== null)
    {
        $nextSortOrder = (int) $maxSortRow['maxSort'] + 1;
    }

    $insertedItemUID = dbInsert(
        "INSERT INTO tblLinksPageItems
            (pageUID, urlUID, itemTitle, itemURL, itemDescription, itemIcon, requiresAgeGate, sortOrder)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        'iissssii',
        [
            $pageUID,
            $resolvedURLUID,
            $itemTitleRaw,
            $resolvedItemURL,
            $itemDescriptionValue,
            $itemIconValue,
            $requiresAgeGateValue,
            $nextSortOrder,
        ]
    );

    if ($insertedItemUID === false)
    {
        return [
            'success' => false,
            'itemUID' => null,
            'error'   => 'Could not add the link. Please try again.',
        ];
    }

    if (function_exists('logActivity'))
    {
        logActivity('add_linkspage_item', 'success', 200, [
            'userUID' => $userUID,
            'logData' => ['pageUID' => $pageUID, 'itemUID' => $insertedItemUID],
        ]);
    }

    return [
        'success' => true,
        'itemUID' => (int) $insertedItemUID,
        'error'   => null,
    ];
}

/**
 * Update an item's title/description/icon — and, ONLY when it was originally
 * a manual link (urlUID IS NULL), its destination URL. An item sourced from
 * one of the user's own short URLs keeps that link locked (mirrors
 * edit_link's read-only short-code convention) — edit the short URL itself
 * to change where it points.
 *
 * requiresAgeGate (C.5, #50) is re-resolved via
 * _g2ml_linkspageManageResolveRequiresAgeGate() against the item's FINAL
 * destination URL every time it is edited — the owner's own submitted
 * checkbox is honoured unless that destination matches the curated
 * adult-domain allowlist, in which case the gate stays force-enabled.
 *
 * @param  int   $userUID  The ACTING user's own userUID.
 * @param  int   $itemUID
 * @param  array $input    ['itemTitle' => string, 'itemDescription' => string|null,
 *                          'itemIcon' => string|null, 'manualURL' => string|null,
 *                          'requiresAgeGate' => bool|null]
 * @return array  ['success' => bool, 'error' => string|null]
 */
function g2ml_linkspageManageUpdateItem(int $userUID, int $itemUID, array $input): array
{
    $existingItem = g2ml_linkspageManageGetItemForOwner($itemUID, $userUID);

    if ($existingItem === null)
    {
        return [
            'success' => false,
            'error'   => 'Link not found, or you do not have permission to edit it.',
        ];
    }

    $itemTitleRaw = '';

    if (isset($input['itemTitle']) && is_string($input['itemTitle']))
    {
        $itemTitleRaw = trim(g2ml_sanitiseInput($input['itemTitle']));
    }

    if ($itemTitleRaw === '')
    {
        return [
            'success' => false,
            'error'   => 'Please enter a title for this link.',
        ];
    }

    if (mb_strlen($itemTitleRaw) > 255)
    {
        return [
            'success' => false,
            'error'   => 'The link title must be 255 characters or fewer.',
        ];
    }

    $itemDescriptionRaw = '';

    if (isset($input['itemDescription']) && is_string($input['itemDescription']))
    {
        $itemDescriptionRaw = trim(g2ml_sanitiseInput($input['itemDescription']));
    }

    if (mb_strlen($itemDescriptionRaw) > G2ML_LINKSPAGE_MANAGE_DESCRIPTION_MAX_LENGTH)
    {
        return [
            'success' => false,
            'error'   => 'The link description must be ' . G2ML_LINKSPAGE_MANAGE_DESCRIPTION_MAX_LENGTH . ' characters or fewer.',
        ];
    }

    if ($itemDescriptionRaw === '')
    {
        $itemDescriptionValue = null;
    }
    else
    {
        $itemDescriptionValue = $itemDescriptionRaw;
    }

    $itemIconRaw = '';

    if (isset($input['itemIcon']) && is_string($input['itemIcon']))
    {
        $itemIconRaw = trim($input['itemIcon']);
    }

    if ($itemIconRaw === '')
    {
        $itemIconValue = null;
    }
    else
    {
        $sanitisedIcon = g2ml_sanitiseURL($itemIconRaw);

        if ($sanitisedIcon === false || mb_strlen($sanitisedIcon) > 500)
        {
            return [
                'success' => false,
                'error'   => 'The icon must be a valid http:// or https:// image URL.',
            ];
        }

        $itemIconValue = $sanitisedIcon;
    }

    // Only a MANUAL item (no linked short URL) may have its destination URL
    // changed here.
    if ($existingItem['urlUID'] === null)
    {
        $manualURLRaw = '';

        if (isset($input['manualURL']) && is_string($input['manualURL']))
        {
            $manualURLRaw = trim($input['manualURL']);
        }

        $sanitisedManualURL = g2ml_sanitiseURL($manualURLRaw);

        if ($sanitisedManualURL === false)
        {
            return [
                'success' => false,
                'error'   => 'Please enter a valid http:// or https:// URL.',
            ];
        }

        $itemURLValue = $sanitisedManualURL;
    }
    else
    {
        // Short-URL-sourced item — the destination is locked to the short URL.
        $itemURLValue = $existingItem['itemURL'];
    }

    // 🔞 C.5/#50 — re-resolve the auto-flag against the FINAL destination
    // URL. See _g2ml_linkspageManageResolveRequiresAgeGate()'s docblock.
    $ownerRequestedAgeGate = false;

    if (isset($input['requiresAgeGate']) && $input['requiresAgeGate'] === true)
    {
        $ownerRequestedAgeGate = true;
    }

    $requiresAgeGateValue = _g2ml_linkspageManageResolveRequiresAgeGate($ownerRequestedAgeGate, $itemURLValue);

    // 🔒 Ownership enforced again on the UPDATE itself via a correlated
    // subquery scoped to pages owned by the acting user.
    $affectedRows = dbUpdate(
        "UPDATE tblLinksPageItems SET itemTitle = ?, itemURL = ?, itemDescription = ?, itemIcon = ?, requiresAgeGate = ?
         WHERE itemUID = ? AND pageUID IN (SELECT pageUID FROM tblLinksPages WHERE userUID = ?)",
        'ssssiii',
        [
            $itemTitleRaw,
            $itemURLValue,
            $itemDescriptionValue,
            $itemIconValue,
            $requiresAgeGateValue,
            $itemUID,
            $userUID,
        ]
    );

    if ($affectedRows === false)
    {
        return [
            'success' => false,
            'error'   => 'Could not update the link. Please try again.',
        ];
    }

    if (function_exists('logActivity'))
    {
        logActivity('update_linkspage_item', 'success', 200, [
            'userUID' => $userUID,
            'logData' => ['itemUID' => $itemUID],
        ]);
    }

    return [
        'success' => true,
        'error'   => null,
    ];
}

/**
 * Delete an item — ownership-checked via a correlated subquery scoped to
 * pages owned by the acting user.
 *
 * @param  int $userUID  The ACTING user's own userUID.
 * @param  int $itemUID
 * @return array  ['success' => bool, 'error' => string|null]
 */
function g2ml_linkspageManageDeleteItem(int $userUID, int $itemUID): array
{
    $deletedRows = dbDelete(
        "DELETE FROM tblLinksPageItems
         WHERE itemUID = ? AND pageUID IN (SELECT pageUID FROM tblLinksPages WHERE userUID = ?)",
        'ii',
        [$itemUID, $userUID]
    );

    if ($deletedRows === false || $deletedRows === 0)
    {
        return [
            'success' => false,
            'error'   => 'Link not found, or you do not have permission to delete it.',
        ];
    }

    if (function_exists('logActivity'))
    {
        logActivity('delete_linkspage_item', 'success', 200, [
            'userUID' => $userUID,
            'logData' => ['itemUID' => $itemUID],
        ]);
    }

    return [
        'success' => true,
        'error'   => null,
    ];
}

/**
 * Flip an item's isActive flag — ownership-checked.
 *
 * @param  int $userUID  The ACTING user's own userUID.
 * @param  int $itemUID
 * @return array  ['success' => bool, 'isActive' => int|null, 'error' => string|null]
 */
function g2ml_linkspageManageToggleItemActive(int $userUID, int $itemUID): array
{
    $existingItem = g2ml_linkspageManageGetItemForOwner($itemUID, $userUID);

    if ($existingItem === null)
    {
        return [
            'success'  => false,
            'isActive' => null,
            'error'    => 'Link not found, or you do not have permission to edit it.',
        ];
    }

    if ((int) $existingItem['isActive'] === 1)
    {
        $newActiveValue = 0;
    }
    else
    {
        $newActiveValue = 1;
    }

    $affectedRows = dbUpdate(
        "UPDATE tblLinksPageItems SET isActive = ?
         WHERE itemUID = ? AND pageUID IN (SELECT pageUID FROM tblLinksPages WHERE userUID = ?)",
        'iii',
        [$newActiveValue, $itemUID, $userUID]
    );

    if ($affectedRows === false)
    {
        return [
            'success'  => false,
            'isActive' => null,
            'error'    => 'Could not update the link. Please try again.',
        ];
    }

    if (function_exists('logActivity'))
    {
        logActivity('toggle_linkspage_item', 'success', 200, [
            'userUID' => $userUID,
            'logData' => ['itemUID' => $itemUID, 'isActive' => $newActiveValue],
        ]);
    }

    return [
        'success'  => true,
        'isActive' => $newActiveValue,
        'error'    => null,
    ];
}

/**
 * Move an item up or down (swap sortOrder with its adjacent sibling on the
 * SAME page) — ownership-checked. A no-sibling case (already first/last) is
 * reported as a successful no-op, not an error.
 *
 * @param  int    $userUID    The ACTING user's own userUID.
 * @param  int    $itemUID
 * @param  string $direction  'up' or 'down' — any other value is rejected.
 * @return array  ['success' => bool, 'moved' => bool, 'error' => string|null]
 */
function g2ml_linkspageManageMoveItem(int $userUID, int $itemUID, string $direction): array
{
    if ($direction !== 'up' && $direction !== 'down')
    {
        return [
            'success' => false,
            'moved'   => false,
            'error'   => 'Invalid move direction.',
        ];
    }

    $existingItem = g2ml_linkspageManageGetItemForOwner($itemUID, $userUID);

    if ($existingItem === null)
    {
        return [
            'success' => false,
            'moved'   => false,
            'error'   => 'Link not found, or you do not have permission to edit it.',
        ];
    }

    $pageUID          = (int) $existingItem['pageUID'];
    $currentSortOrder = (int) $existingItem['sortOrder'];

    if ($direction === 'up')
    {
        $sibling = dbSelectOne(
            "SELECT itemUID, sortOrder FROM tblLinksPageItems
             WHERE pageUID = ? AND sortOrder < ?
             ORDER BY sortOrder DESC, itemUID DESC
             LIMIT 1",
            'ii',
            [$pageUID, $currentSortOrder]
        );
    }
    else
    {
        $sibling = dbSelectOne(
            "SELECT itemUID, sortOrder FROM tblLinksPageItems
             WHERE pageUID = ? AND sortOrder > ?
             ORDER BY sortOrder ASC, itemUID ASC
             LIMIT 1",
            'ii',
            [$pageUID, $currentSortOrder]
        );
    }

    if ($sibling === null || $sibling === false)
    {
        // Already at the top/bottom — nothing to do, not an error.
        return [
            'success' => true,
            'moved'   => false,
            'error'   => null,
        ];
    }

    $siblingItemUID    = (int) $sibling['itemUID'];
    $siblingSortOrder  = (int) $sibling['sortOrder'];

    dbBeginTransaction();

    $firstUpdate = dbUpdate(
        "UPDATE tblLinksPageItems SET sortOrder = ? WHERE itemUID = ? AND pageUID = ?",
        'iii',
        [$siblingSortOrder, $itemUID, $pageUID]
    );

    $secondUpdate = dbUpdate(
        "UPDATE tblLinksPageItems SET sortOrder = ? WHERE itemUID = ? AND pageUID = ?",
        'iii',
        [$currentSortOrder, $siblingItemUID, $pageUID]
    );

    if ($firstUpdate === false || $secondUpdate === false)
    {
        dbRollback();

        return [
            'success' => false,
            'moved'   => false,
            'error'   => 'Could not reorder the links. Please try again.',
        ];
    }

    dbCommit();

    return [
        'success' => true,
        'moved'   => true,
        'error'   => null,
    ];
}

// ============================================================================
// 🧨 Custom HTML / CSS (Component C.6, #49)
// ============================================================================
// The single highest stored-XSS surface in the product. Every write here is:
//   1. OWNERSHIP-checked (the page must belong to the acting user);
//   2. GATED — the operator kill-switch (linkspage.custom_html_enabled) must be
//      ON *and* the PAGE'S OWN org tier must grant hasCustomHTML — via
//      g2ml_linkspageCustomHtmlAllowedForOrg() (web/_functions/html_sanitiser.php).
//      A non-premium org (or the whole feature being off) means the value is
//      NEITHER sanitised-and-stored NOR rendered;
//   3. SANITISED on input with g2ml_sanitiseUserHTML()/g2ml_sanitiseUserCSS()
//      (DOM allowlist + mXSS fixed-point) — the SANITISED form is what is
//      stored, never the raw submission;
//   4. re-sanitised again on OUTPUT by the renderer, and served under a strict
//      `script-src 'none'` CSP.
// Clearing custom HTML (submitting empty) is ALWAYS allowed (it is safe and
// simply reverts the page to its system template), even for a non-premium org.
// ============================================================================

/**
 * Shared gate + sanitise + store for a custom-HTML/CSS write. Internal — both
 * the textarea save and the file-upload save delegate here so the security
 * logic exists in exactly one place.
 *
 * @param  int    $userUID       The ACTING user's own userUID.
 * @param  int    $pageUID
 * @param  string $customHTMLRaw The raw (un-sanitised) HTML submission.
 * @param  string $customCSSRaw  The raw (un-sanitised) CSS submission.
 * @return array  ['success' => bool, 'error' => string|null, 'errorCode' => string|null,
 *                 'customHTML' => string|null, 'customCSS' => string|null]
 */
function _g2ml_linkspageManageStoreSanitisedCustom(int $userUID, int $pageUID, string $customHTMLRaw, string $customCSSRaw): array
{
    $ownedPage = g2ml_linkspageManageGetPageForOwner($pageUID, $userUID);

    if ($ownedPage === null)
    {
        return [
            'success'    => false,
            'error'      => 'LinksPage not found, or you do not have permission to edit it.',
            'errorCode'  => 'not_found',
            'customHTML' => null,
            'customCSS'  => null,
        ];
    }

    $isClearing = false;

    if (trim($customHTMLRaw) === '' && trim($customCSSRaw) === '')
    {
        $isClearing = true;
    }

    $orgHandle = null;

    if (isset($ownedPage['orgHandle']) && is_string($ownedPage['orgHandle']))
    {
        $orgHandle = $ownedPage['orgHandle'];
    }

    // 🔒 Enforce the gate for a SET; a CLEAR (empty submission) is always safe
    // and is allowed regardless of tier/kill-switch.
    if ($isClearing === false)
    {
        $allowed = false;

        if (function_exists('g2ml_linkspageCustomHtmlAllowedForOrg'))
        {
            $allowed = g2ml_linkspageCustomHtmlAllowedForOrg($orgHandle);
        }

        if ($allowed !== true)
        {
            return [
                'success'    => false,
                'error'      => 'Custom HTML is not available on your current plan, or has been disabled by the administrator.',
                'errorCode'  => 'feature_unavailable',
                'customHTML' => null,
                'customCSS'  => null,
            ];
        }
    }

    // Size caps on the RAW submission (before sanitisation) — reject oversized
    // input outright rather than silently truncating.
    if (defined('G2ML_CUSTOM_HTML_MAX_BYTES') && strlen($customHTMLRaw) > G2ML_CUSTOM_HTML_MAX_BYTES)
    {
        return [
            'success'    => false,
            'error'      => 'The custom HTML is too large. Please keep it under ' . (int) (G2ML_CUSTOM_HTML_MAX_BYTES / 1000) . ' KB.',
            'errorCode'  => 'too_large',
            'customHTML' => null,
            'customCSS'  => null,
        ];
    }

    if (defined('G2ML_CUSTOM_CSS_MAX_BYTES') && strlen($customCSSRaw) > G2ML_CUSTOM_CSS_MAX_BYTES)
    {
        return [
            'success'    => false,
            'error'      => 'The custom CSS is too large. Please keep it under ' . (int) (G2ML_CUSTOM_CSS_MAX_BYTES / 1000) . ' KB.',
            'errorCode'  => 'too_large',
            'customHTML' => null,
            'customCSS'  => null,
        ];
    }

    // 🔒 SANITISE ON INPUT — store the SANITISED form, never the raw submission.
    if (function_exists('g2ml_sanitiseUserHTML'))
    {
        $sanitisedHTML = g2ml_sanitiseUserHTML($customHTMLRaw);
    }
    else
    {
        // Fail closed: with no sanitiser available, refuse the write entirely
        // rather than store un-sanitised HTML.
        return [
            'success'    => false,
            'error'      => 'The custom HTML editor is temporarily unavailable. Please try again later.',
            'errorCode'  => 'sanitiser_unavailable',
            'customHTML' => null,
            'customCSS'  => null,
        ];
    }

    if (function_exists('g2ml_sanitiseUserCSS'))
    {
        $sanitisedCSS = g2ml_sanitiseUserCSS($customCSSRaw);
    }
    else
    {
        $sanitisedCSS = '';
    }

    // Empty sanitised values are stored as NULL (revert to the system template).
    if (trim($sanitisedHTML) === '')
    {
        $htmlToStore = null;
    }
    else
    {
        $htmlToStore = $sanitisedHTML;
    }

    if (trim($sanitisedCSS) === '')
    {
        $cssToStore = null;
    }
    else
    {
        $cssToStore = $sanitisedCSS;
    }

    // 🔒 Ownership enforced again on the UPDATE itself via "AND userUID = ?".
    $affectedRows = dbUpdate(
        "UPDATE tblLinksPages SET customHTML = ?, customCSS = ? WHERE pageUID = ? AND userUID = ?",
        'ssii',
        [$htmlToStore, $cssToStore, $pageUID, $userUID]
    );

    if ($affectedRows === false)
    {
        return [
            'success'    => false,
            'error'      => 'Could not save the custom HTML. Please try again.',
            'errorCode'  => 'server_error',
            'customHTML' => null,
            'customCSS'  => null,
        ];
    }

    if (function_exists('logActivity'))
    {
        if ($isClearing === true)
        {
            $customLogStatus = 'cleared';
        }
        else
        {
            $customLogStatus = 'saved';
        }

        logActivity('update_linkspage_custom_html', 'success', 200, [
            'userUID' => $userUID,
            'logData' => ['pageUID' => $pageUID, 'action' => $customLogStatus],
        ]);
    }

    return [
        'success'    => true,
        'error'      => null,
        'errorCode'  => null,
        'customHTML' => $htmlToStore,
        'customCSS'  => $cssToStore,
    ];
}

/**
 * Save custom HTML/CSS typed into the editor's source textareas.
 *
 * @param  int    $userUID       The ACTING user's own userUID.
 * @param  int    $pageUID
 * @param  string $customHTMLRaw
 * @param  string $customCSSRaw
 * @return array  See _g2ml_linkspageManageStoreSanitisedCustom().
 */
function g2ml_linkspageManageSaveCustomHTML(int $userUID, int $pageUID, string $customHTMLRaw, string $customCSSRaw): array
{
    return _g2ml_linkspageManageStoreSanitisedCustom($userUID, $pageUID, $customHTMLRaw, $customCSSRaw);
}

/**
 * Save custom HTML from an UPLOADED .html file (plus optional CSS from the
 * editor textarea). The file is read and run through the SAME sanitiser — the
 * raw upload is NEVER stored. Enforces a size cap and rejects non-HTML files.
 *
 * @param  int         $userUID      The ACTING user's own userUID.
 * @param  int         $pageUID
 * @param  array       $file         One entry from $_FILES (name/type/tmp_name/error/size).
 * @param  string      $customCSSRaw Optional CSS from the editor textarea.
 * @return array  See _g2ml_linkspageManageStoreSanitisedCustom().
 */
function g2ml_linkspageManageSaveCustomHTMLFromUpload(int $userUID, int $pageUID, array $file, string $customCSSRaw): array
{
    $uploadError = UPLOAD_ERR_NO_FILE;

    if (isset($file['error']))
    {
        $uploadError = (int) $file['error'];
    }

    if ($uploadError === UPLOAD_ERR_NO_FILE)
    {
        return [
            'success'    => false,
            'error'      => 'Please choose an HTML file to upload.',
            'errorCode'  => 'no_file',
            'customHTML' => null,
            'customCSS'  => null,
        ];
    }

    if ($uploadError !== UPLOAD_ERR_OK)
    {
        return [
            'success'    => false,
            'error'      => 'The file upload did not complete. Please try again.',
            'errorCode'  => 'upload_error',
            'customHTML' => null,
            'customCSS'  => null,
        ];
    }

    $fileSize = 0;

    if (isset($file['size']))
    {
        $fileSize = (int) $file['size'];
    }

    $maxBytes = 100000;

    if (defined('G2ML_CUSTOM_HTML_MAX_BYTES'))
    {
        $maxBytes = G2ML_CUSTOM_HTML_MAX_BYTES;
    }

    if ($fileSize <= 0 || $fileSize > $maxBytes)
    {
        return [
            'success'    => false,
            'error'      => 'The HTML file must be between 1 byte and ' . (int) ($maxBytes / 1000) . ' KB.',
            'errorCode'  => 'too_large',
            'customHTML' => null,
            'customCSS'  => null,
        ];
    }

    // Reject non-HTML by extension (defence in depth — the content is
    // sanitised regardless, but there is no reason to accept a .php/.js/etc.).
    $fileName = '';

    if (isset($file['name']) && is_string($file['name']))
    {
        $fileName = $file['name'];
    }

    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if ($extension !== 'html' && $extension !== 'htm')
    {
        return [
            'success'    => false,
            'error'      => 'Only .html files are accepted.',
            'errorCode'  => 'wrong_type',
            'customHTML' => null,
            'customCSS'  => null,
        ];
    }

    $tmpName = '';

    if (isset($file['tmp_name']) && is_string($file['tmp_name']))
    {
        $tmpName = $file['tmp_name'];
    }

    // Only ever read a genuine uploaded temp file (blocks a caller passing an
    // arbitrary server path). In the CLI/test context is_uploaded_file() is
    // false, so a test override global is honoured there instead.
    $isRealUpload = is_uploaded_file($tmpName);

    if ($isRealUpload === false && !isset($GLOBALS['g2ml_linkspage_test_allow_plain_upload']))
    {
        return [
            'success'    => false,
            'error'      => 'The uploaded file could not be read. Please try again.',
            'errorCode'  => 'not_uploaded_file',
            'customHTML' => null,
            'customCSS'  => null,
        ];
    }

    if ($tmpName === '' || !is_readable($tmpName))
    {
        return [
            'success'    => false,
            'error'      => 'The uploaded file could not be read. Please try again.',
            'errorCode'  => 'unreadable',
            'customHTML' => null,
            'customCSS'  => null,
        ];
    }

    $contents = file_get_contents($tmpName, false, null, 0, $maxBytes + 1);

    if ($contents === false)
    {
        return [
            'success'    => false,
            'error'      => 'The uploaded file could not be read. Please try again.',
            'errorCode'  => 'unreadable',
            'customHTML' => null,
            'customCSS'  => null,
        ];
    }

    if (strlen($contents) > $maxBytes)
    {
        return [
            'success'    => false,
            'error'      => 'The HTML file is too large. Please keep it under ' . (int) ($maxBytes / 1000) . ' KB.',
            'errorCode'  => 'too_large',
            'customHTML' => null,
            'customCSS'  => null,
        ];
    }

    return _g2ml_linkspageManageStoreSanitisedCustom($userUID, $pageUID, $contents, $customCSSRaw);
}
