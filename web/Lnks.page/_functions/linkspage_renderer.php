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
 * 🎨 Go2My.Link — LinksPage Renderer (Component C)
 * ============================================================================
 *
 * Pure templating layer: given an ALREADY-resolved page model (see
 * linkspage_resolver.php — g2ml_resolveLinksPage()), substitutes the 7 system
 * placeholder markers ({{avatar}}, {{name}}, {{bio}}, {{links}}, {{social}},
 * {{theme}}, {{background}}) into the page's SYSTEM templateHTML/templateCSS
 * (web/_sql/seeds/004_linkspage_templates.sql) and returns a complete,
 * self-contained HTML document.
 *
 * 🔒 SECURITY (this renders a PUBLIC page carrying third-party-authored data):
 *   - customHTML / customCSS are NEVER read or emitted here — deferred to
 *     C.6 / #49 (WYSIWYG editor + HTML upload), which needs its own sanitiser
 *     and a dedicated security review before it ships.
 *   - Every user-authored string (pageTitle, pageDescription, item titles /
 *     descriptions, social labels) is escaped via g2ml_sanitiseOutput() /
 *     htmlspecialchars(ENT_QUOTES) before it reaches the page.
 *   - Every URL (item destinations, social links, the avatar, item icons) is
 *     passed through g2ml_sanitiseURL()'s http/https scheme allowlist before
 *     it can appear in an href/src — javascript:, data:-from-user-input, and
 *     any other scheme are rejected outright. A rejected link/icon is simply
 *     OMITTED, never half-rendered.
 *   - themeColour / backgroundColour are hex-validated (reject → a safe
 *     built-in default); fontFamily is validated against a CSS-safe character
 *     allowlist (no `;`, `{`, `}`, parentheses — so `url(...)`/`expression(...)`
 *     can never appear) before either is ever placed inside a `<style>` block.
 *   - Favicons are NEVER fetched at render time (SSRF + latency risk) — only
 *     the already-stored itemIcon / faviconCacheURL is consumed, falling back
 *     to a self-contained inline (data: URI) default icon.
 *
 * These helpers are deliberately self-contained — function_exists() guards
 * around g2ml_sanitiseOutput()/g2ml_sanitiseURL() — so this file (and its unit
 * tests) can run without a database connection or the full bootstrap, mirroring
 * the pattern already used by web/_functions/avatar.php.
 *
 * Functions:
 *   - g2ml_linkspageValidateHexColour()   — hex-colour allowlist
 *   - g2ml_linkspageValidateFontFamily()  — CSS-safe font-family allowlist
 *   - g2ml_linkspageDefaultThemeColour()/g2ml_linkspageDefaultBackgroundColour() — safe fallback colours
 *   - g2ml_linkspageEscape()              — HTML-escape with a safe fallback
 *   - g2ml_linkspageSanitiseHref()        — http/https scheme allowlist with a safe fallback
 *   - g2ml_linkspageDefaultAvatarSrc()/g2ml_linkspageResolveAvatarSrc() — avatar URL resolution
 *   - g2ml_linkspageDefaultItemIconSrc()  — self-contained default link icon (data: URI)
 *   - g2ml_linkspageRenderItemIcon()      — one item's icon <img>
 *   - g2ml_linkspageRenderItem()          — one item's <a> markup (or '' if its URL is unsafe)
 *   - g2ml_linkspageRenderItems()         — the full {{links}} fragment
 *   - g2ml_linkspageRenderSocial()        — the full {{social}} fragment
 *   - g2ml_linkspageApplyTemplate()       — the 7-placeholder substitution engine
 *   - g2ml_linkspageBuildDocument()       — wraps rendered body + CSS into a full HTML document
 *   - g2ml_renderLinksPage()              — top-level: page model -> full HTML document
 *   - g2ml_linkspageRenderAgeGateInterstitial() — C.5/#50 good-faith age-verification interstitial
 *
 * @package    Go2My.Link
 * @subpackage ComponentC
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.2.0
 * @since      Phase 8 (#45; age gate v1.2.0 / #50)
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
// 🎨 Colour / font validation (CSS-injection prevention)
// ============================================================================

/**
 * Validate a stored colour value as a strict hex-colour literal.
 *
 * @param  string|null $colour
 * @return string|false  The trimmed, validated colour, or false when invalid.
 */
function g2ml_linkspageValidateHexColour(?string $colour): string|false
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
 * Validate a stored font-family value against a CSS-safe character allowlist.
 *
 * Only letters, digits, spaces, commas, hyphens, and single/double quotes are
 * permitted — the allowlist itself excludes `;`, `{`, `}`, `(`, `)`, `<`, `>`,
 * so a value can never break out of a `font-family: ...;` declaration, inject
 * an additional rule, or smuggle `url(...)` / `expression(...)`.
 *
 * @param  string|null $fontFamily
 * @return string|false  The trimmed, validated value, or false when invalid/absent.
 */
function g2ml_linkspageValidateFontFamily(?string $fontFamily): string|false
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
 * The safe built-in default theme (accent) colour, used whenever a page has
 * no themeColour, or it fails hex validation.
 *
 * @return string
 */
function g2ml_linkspageDefaultThemeColour(): string
{
    return '#1E88E5';
}

/**
 * The safe built-in default background colour, used whenever a page has no
 * backgroundColour, or it fails hex validation.
 *
 * @return string
 */
function g2ml_linkspageDefaultBackgroundColour(): string
{
    return '#FFFFFF';
}

// ============================================================================
// 🧹 Escaping / URL sanitisation (with DB-free fallbacks for unit tests)
// ============================================================================

/**
 * Escape a value for safe HTML output.
 *
 * Prefers the project's g2ml_sanitiseOutput() when loaded; otherwise falls
 * back to htmlspecialchars() so this file stays testable without the full
 * application bootstrap.
 *
 * @param  string|null $value
 * @return string
 */
function g2ml_linkspageEscape(?string $value): string
{
    if ($value === null)
    {
        return '';
    }

    if (function_exists('g2ml_sanitiseOutput'))
    {
        return g2ml_sanitiseOutput($value);
    }

    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Validate a URL against the http/https scheme allowlist before it is ever
 * allowed to reach an href or src attribute.
 *
 * Prefers the project's g2ml_sanitiseURL() when loaded; otherwise falls back
 * to an equivalent filter_var()-based check so this file stays testable
 * without the full application bootstrap.
 *
 * @param  string|null $url
 * @return string|false  The sanitised URL, or false when absent/unsafe.
 */
function g2ml_linkspageSanitiseHref(?string $url): string|false
{
    if ($url === null)
    {
        return false;
    }

    $trimmed = trim($url);

    if ($trimmed === '')
    {
        return false;
    }

    if (function_exists('g2ml_sanitiseURL'))
    {
        return g2ml_sanitiseURL($trimmed);
    }

    // DB-free fallback (unit tests): mirrors g2ml_sanitiseURL()'s http/https
    // scheme allowlist in web/_functions/security.php.
    $sanitised = filter_var($trimmed, FILTER_SANITIZE_URL);

    if (filter_var($sanitised, FILTER_VALIDATE_URL) === false)
    {
        return false;
    }

    $scheme = parse_url($sanitised, PHP_URL_SCHEME);

    if ($scheme === null || !in_array(strtolower($scheme), ['http', 'https'], true))
    {
        return false;
    }

    return $sanitised;
}

// ============================================================================
// 🖼️ Avatar / icon resolution
// ============================================================================

/**
 * The sensible fallback avatar shown when a page has no avatarPath, or it
 * fails scheme validation. A relative, same-origin path — never user data.
 *
 * @return string
 */
function g2ml_linkspageDefaultAvatarSrc(): string
{
    return '/img/logo.png';
}

/**
 * Resolve the avatar `src` value for the {{avatar}} placeholder.
 *
 * NOTE: every system template already wraps this value in its OWN <img> tag
 * (e.g. `<img class="lp-avatar" src="{{avatar}}" alt="{{name}}">` — see
 * web/_sql/seeds/004_linkspage_templates.sql), so this returns the bare,
 * scheme-validated URL string, not a full <img> element.
 *
 * @param  string|null $avatarPath
 * @return string  A scheme-validated URL, or the built-in default avatar path.
 */
function g2ml_linkspageResolveAvatarSrc(?string $avatarPath): string
{
    if ($avatarPath === null || trim($avatarPath) === '')
    {
        return g2ml_linkspageDefaultAvatarSrc();
    }

    $sanitised = g2ml_linkspageSanitiseHref($avatarPath);

    if ($sanitised === false || $sanitised === '')
    {
        return g2ml_linkspageDefaultAvatarSrc();
    }

    return $sanitised;
}

/**
 * A minimal, self-contained default link icon (an inline chain-link glyph,
 * base64 data: URI). Used whenever an item has no itemIcon/faviconCacheURL,
 * or the stored value fails scheme validation.
 *
 * Deliberately NEVER fetched over the network (see the file header's SSRF
 * note) — this is a fixed, static asset baked into the renderer itself.
 *
 * @return string
 */
function g2ml_linkspageDefaultItemIconSrc(): string
{
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" '
        . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
        . '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>'
        . '<path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>'
        . '</svg>';

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

/**
 * Render one item's icon <img>, preferring itemIcon over faviconCacheURL,
 * falling back to the built-in default icon when neither is present/safe.
 *
 * Favicons are NEVER fetched here (see file header) — only already-stored
 * values are consumed.
 *
 * @param  array $item
 * @return string  Safe <img> HTML (always non-empty).
 */
function g2ml_linkspageRenderItemIcon(array $item): string
{
    $iconCandidate = null;

    if (isset($item['itemIcon']) && is_string($item['itemIcon']) && trim($item['itemIcon']) !== '')
    {
        $iconCandidate = trim($item['itemIcon']);
    }
    elseif (isset($item['faviconCacheURL']) && is_string($item['faviconCacheURL']) && trim($item['faviconCacheURL']) !== '')
    {
        $iconCandidate = trim($item['faviconCacheURL']);
    }

    $iconSrc = null;

    if ($iconCandidate !== null)
    {
        $sanitisedIcon = g2ml_linkspageSanitiseHref($iconCandidate);

        if ($sanitisedIcon !== false && $sanitisedIcon !== '')
        {
            $iconSrc = $sanitisedIcon;
        }
    }

    if ($iconSrc === null)
    {
        $iconSrc = g2ml_linkspageDefaultItemIconSrc();
    }

    return '<img class="lp-link-icon" src="' . g2ml_linkspageEscape($iconSrc) . '" alt="" aria-hidden="true" loading="lazy" width="20" height="20">';
}

// ============================================================================
// 🔗 Item / social rendering ({{links}} and {{social}})
// ============================================================================

/**
 * Render one LinksPage item as a safe <a> element, or '' when its destination
 * URL fails the http/https scheme allowlist (the item is OMITTED entirely —
 * never rendered with a stripped/blank href).
 *
 * @param  array $item  A row from tblLinksPageItems (itemTitle, itemURL,
 *                       itemDescription, itemIcon, faviconCacheURL, requiresAgeGate).
 * @return string
 */
function g2ml_linkspageRenderItem(array $item): string
{
    $rawURL = null;

    if (isset($item['itemURL']) && is_string($item['itemURL']))
    {
        $rawURL = $item['itemURL'];
    }

    $href = g2ml_linkspageSanitiseHref($rawURL);

    if ($href === false)
    {
        // Unsafe/unparseable destination (e.g. javascript:, data:, malformed) —
        // never render a dangerous or dead link.
        return '';
    }

    $rawTitle = null;

    if (isset($item['itemTitle']) && is_string($item['itemTitle']))
    {
        $rawTitle = $item['itemTitle'];
    }

    $escapedTitle = g2ml_linkspageEscape($rawTitle);

    $descriptionHTML = '';

    if (isset($item['itemDescription']) && is_string($item['itemDescription']) && trim($item['itemDescription']) !== '')
    {
        $descriptionHTML = '<span class="lp-link-desc">' . g2ml_linkspageEscape($item['itemDescription']) . '</span>';
    }

    $iconHTML = g2ml_linkspageRenderItemIcon($item);

    // 🔞 C.5 (age verification, #50) is enforced at the PAGE level, BEFORE
    // this renderer is ever invoked — see linkspage_resolver.php's
    // g2ml_linkspageHasAgeGatedItems() and public_html/index.php's Step 8.5.
    // By the time g2ml_renderLinksPage() (and therefore this function) runs,
    // either the page had no gated items at all, or the visitor already
    // carries a valid signed g2ml_agegate cookie — so it is safe to render
    // this item's real href here. The data-age-gate attribute below is
    // therefore purely a COSMETIC post-confirmation hook (e.g. an optional
    // "18+" badge via CSS) — it carries no security meaning of its own.
    $ageGateAttribute = '';

    if (isset($item['requiresAgeGate']) && (int) $item['requiresAgeGate'] === 1)
    {
        $ageGateAttribute = ' data-age-gate="1"';
    }

    return '<a class="lp-link-item" href="' . g2ml_linkspageEscape($href) . '" target="_blank" rel="noopener noreferrer nofollow"' . $ageGateAttribute . '>'
        . $iconHTML
        . '<span class="lp-link-title">' . $escapedTitle . '</span>'
        . $descriptionHTML
        . '</a>';
}

/**
 * Render the full {{links}} fragment from a list of item rows.
 *
 * @param  array $items  Rows from tblLinksPageItems, already filtered to
 *                        isActive = 1 and ordered by sortOrder (see
 *                        g2ml_resolveLinksPage()).
 * @return string
 */
function g2ml_linkspageRenderItems(array $items): string
{
    $html = '';

    foreach ($items as $item)
    {
        if (!is_array($item))
        {
            continue;
        }

        $html = $html . g2ml_linkspageRenderItem($item);
    }

    return $html;
}

/**
 * The allowlisted social networks and their accessible labels. Any key in
 * socialLinks JSON that is NOT in this list is silently ignored, regardless
 * of its value — this is a closed allowlist, not a denylist.
 *
 * @return array<string, string>
 */
function g2ml_linkspageAllowedSocialNetworks(): array
{
    return [
        'twitter'   => 'X / Twitter',
        'x'         => 'X / Twitter',
        'instagram' => 'Instagram',
        'facebook'  => 'Facebook',
        'linkedin'  => 'LinkedIn',
        'youtube'   => 'YouTube',
        'tiktok'    => 'TikTok',
        'github'    => 'GitHub',
        'website'   => 'Website',
        'email'     => 'Email',
    ];
}

/**
 * Render the full {{social}} fragment from the page's socialLinks JSON.
 *
 * Renders nothing at all when showSocialIcons is false. Each network key is
 * checked against a closed allowlist (g2ml_linkspageAllowedSocialNetworks())
 * and each URL is scheme-validated — an unrecognised network key, or a URL
 * that fails validation, is skipped rather than rendered.
 *
 * @param  string|null $socialLinksJSON  The raw socialLinks JSON column value.
 * @param  bool        $showSocialIcons
 * @return string
 */
function g2ml_linkspageRenderSocial(?string $socialLinksJSON, bool $showSocialIcons): string
{
    if ($showSocialIcons === false)
    {
        return '';
    }

    if ($socialLinksJSON === null || trim($socialLinksJSON) === '')
    {
        return '';
    }

    $decoded = json_decode($socialLinksJSON, true);

    if (!is_array($decoded))
    {
        return '';
    }

    $allowedNetworks = g2ml_linkspageAllowedSocialNetworks();
    $iconsHTML       = '';

    foreach ($decoded as $network => $url)
    {
        if (!is_string($network) || !is_string($url))
        {
            continue;
        }

        $networkKey = strtolower(trim($network));

        if (!isset($allowedNetworks[$networkKey]))
        {
            continue;
        }

        $sanitisedURL = g2ml_linkspageSanitiseHref($url);

        if ($sanitisedURL === false || $sanitisedURL === '')
        {
            continue;
        }

        $label = $allowedNetworks[$networkKey];

        $iconsHTML = $iconsHTML
            . '<a class="lp-social-icon lp-social-' . g2ml_linkspageEscape($networkKey) . '" href="'
            . g2ml_linkspageEscape($sanitisedURL) . '" target="_blank" rel="noopener noreferrer nofollow" aria-label="'
            . g2ml_linkspageEscape($label) . '">'
            . g2ml_linkspageEscape($label)
            . '</a>';
    }

    return $iconsHTML;
}

// ============================================================================
// 🧩 Template placeholder substitution
// ============================================================================

/**
 * Substitute the 7 system placeholder markers into a template's HTML or CSS.
 *
 * A single-pass strtr() replacement — every value in $data is expected to
 * ALREADY be safe for the context it will land in (escaped HTML text/attribute
 * values, or hex/CSS-safe validated colour and font values). This function
 * performs NO escaping itself; see g2ml_renderLinksPage() for how each value
 * is prepared before being passed in here.
 *
 * @param  string $templateSource  The SYSTEM templateHTML or templateCSS.
 * @param  array  $data            Map with keys: avatar, name, bio, links,
 *                                  social, theme, background.
 * @return string
 */
function g2ml_linkspageApplyTemplate(string $templateSource, array $data): string
{
    $replacements = [
        '{{avatar}}'     => $data['avatar'] ?? '',
        '{{name}}'       => $data['name'] ?? '',
        '{{bio}}'        => $data['bio'] ?? '',
        '{{links}}'      => $data['links'] ?? '',
        '{{social}}'     => $data['social'] ?? '',
        '{{theme}}'      => $data['theme'] ?? '',
        '{{background}}' => $data['background'] ?? '',
    ];

    return strtr($templateSource, $replacements);
}

// ============================================================================
// 📄 Full document assembly
// ============================================================================

/**
 * Wrap the rendered body and resolved CSS into a complete, self-contained
 * HTML document. No external scripts or stylesheets — everything the page
 * needs is either same-origin static assets (the avatar default) or inline.
 *
 * @param  string $escapedTitle        Already-escaped page title (may be '').
 * @param  string $escapedDescription  Already-escaped, NON-<br>-converted description (may be '').
 * @param  string $templateCSS         The resolved (placeholder-substituted) SYSTEM templateCSS.
 * @param  string $customFontCSS       An additional, already-validated font-family rule, or ''.
 * @param  string $bodyHTML            The resolved (placeholder-substituted) SYSTEM templateHTML.
 * @return string
 */
function g2ml_linkspageBuildDocument(string $escapedTitle, string $escapedDescription, string $templateCSS, string $customFontCSS, string $bodyHTML): string
{
    $siteName = 'Lnks.page';

    if (function_exists('getSetting'))
    {
        $configuredSiteName = getSetting('site.name', null);

        if (is_string($configuredSiteName) && trim($configuredSiteName) !== '')
        {
            $siteName = $configuredSiteName;
        }
    }

    $titleTag = $escapedTitle;

    if ($titleTag === '')
    {
        $titleTag = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
    }

    $html  = '<!DOCTYPE html>' . "\n";
    $html .= '<html lang="en">' . "\n";
    $html .= '<head>' . "\n";
    $html .= '    <meta charset="UTF-8">' . "\n";
    $html .= '    <meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
    $html .= '    <meta name="robots" content="index, follow">' . "\n";
    $html .= '    <title>' . $titleTag . '</title>' . "\n";

    if ($escapedDescription !== '')
    {
        $html .= '    <meta name="description" content="' . $escapedDescription . '">' . "\n";
        $html .= '    <meta property="og:description" content="' . $escapedDescription . '">' . "\n";
    }

    $html .= '    <meta property="og:type" content="profile">' . "\n";
    $html .= '    <meta property="og:title" content="' . $titleTag . '">' . "\n";
    $html .= '    <link rel="icon" type="image/x-icon" href="/favicon.ico">' . "\n";
    $html .= '    <style>' . "\n";
    $html .= $templateCSS . "\n";

    if ($customFontCSS !== '')
    {
        $html .= $customFontCSS . "\n";
    }

    $html .= '    </style>' . "\n";
    $html .= '</head>' . "\n";
    $html .= '<body>' . "\n";
    $html .= $bodyHTML . "\n";
    $html .= '</body>' . "\n";
    $html .= '</html>';

    return $html;
}

// ============================================================================
// 🚀 Top-level render entry point
// ============================================================================

/**
 * Render a resolved LinksPage model into a complete, self-contained HTML
 * document.
 *
 * @param  array $pageModel  The structure returned by g2ml_resolveLinksPage():
 *                            ['page' => array, 'template' => array, 'items' => array].
 * @return string
 */
function g2ml_renderLinksPage(array $pageModel): string
{
    $page     = $pageModel['page'] ?? [];
    $template = $pageModel['template'] ?? [];
    $items    = $pageModel['items'] ?? [];

    $pageTitleRaw = null;

    if (isset($page['pageTitle']) && is_string($page['pageTitle']))
    {
        $pageTitleRaw = $page['pageTitle'];
    }

    $pageDescriptionRaw = null;

    if (isset($page['pageDescription']) && is_string($page['pageDescription']))
    {
        $pageDescriptionRaw = $page['pageDescription'];
    }

    $escapedName        = g2ml_linkspageEscape($pageTitleRaw);
    $escapedDescription = g2ml_linkspageEscape($pageDescriptionRaw);
    $bioForBody         = nl2br($escapedDescription);

    $avatarPathRaw = null;

    if (isset($page['avatarPath']) && is_string($page['avatarPath']))
    {
        $avatarPathRaw = $page['avatarPath'];
    }

    $avatarSrc = g2ml_linkspageEscape(g2ml_linkspageResolveAvatarSrc($avatarPathRaw));

    $themeColourRaw = null;

    if (isset($page['themeColour']) && is_string($page['themeColour']))
    {
        $themeColourRaw = $page['themeColour'];
    }

    $themeColour = g2ml_linkspageValidateHexColour($themeColourRaw);

    if ($themeColour === false)
    {
        $themeColour = g2ml_linkspageDefaultThemeColour();
    }

    $backgroundColourRaw = null;

    if (isset($page['backgroundColour']) && is_string($page['backgroundColour']))
    {
        $backgroundColourRaw = $page['backgroundColour'];
    }

    $backgroundColour = g2ml_linkspageValidateHexColour($backgroundColourRaw);

    if ($backgroundColour === false)
    {
        $backgroundColour = g2ml_linkspageDefaultBackgroundColour();
    }

    $fontFamilyRaw = null;

    if (isset($page['fontFamily']) && is_string($page['fontFamily']))
    {
        $fontFamilyRaw = $page['fontFamily'];
    }

    $fontFamily = g2ml_linkspageValidateFontFamily($fontFamilyRaw);

    $showSocialIcons = false;

    if (isset($page['showSocialIcons']) && (int) $page['showSocialIcons'] === 1)
    {
        $showSocialIcons = true;
    }

    $socialLinksRaw = null;

    if (isset($page['socialLinks']) && is_string($page['socialLinks']))
    {
        $socialLinksRaw = $page['socialLinks'];
    }

    $linksHTML  = g2ml_linkspageRenderItems($items);
    $socialHTML = g2ml_linkspageRenderSocial($socialLinksRaw, $showSocialIcons);

    $templateData = [
        'avatar'     => $avatarSrc,
        'name'       => $escapedName,
        'bio'        => $bioForBody,
        'links'      => $linksHTML,
        'social'     => $socialHTML,
        'theme'      => $themeColour,
        'background' => $backgroundColour,
    ];

    $templateHTML = '';

    if (isset($template['templateHTML']) && is_string($template['templateHTML']))
    {
        $templateHTML = $template['templateHTML'];
    }

    $templateCSSRaw = '';

    if (isset($template['templateCSS']) && is_string($template['templateCSS']))
    {
        $templateCSSRaw = $template['templateCSS'];
    }

    $bodyHTML    = g2ml_linkspageApplyTemplate($templateHTML, $templateData);
    $resolvedCSS = g2ml_linkspageApplyTemplate($templateCSSRaw, $templateData);

    $customFontCSS = '';

    if ($fontFamily !== false)
    {
        // fontFamily has already passed the CSS-safe character allowlist
        // (g2ml_linkspageValidateFontFamily) — it cannot contain `;`, `{`,
        // `}`, or parentheses, so it can never inject an additional rule or a
        // url()/expression() call.
        $customFontCSS = '.lp-container { font-family: ' . $fontFamily . ', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }';
    }

    return g2ml_linkspageBuildDocument($escapedName, $escapedDescription, $resolvedCSS, $customFontCSS, $bodyHTML);
}

// ============================================================================
// 🔞 Good-faith age-verification interstitial (Component C.5, #50)
// ============================================================================

/**
 * Render the good-faith age-verification interstitial shown INSTEAD OF the
 * real page whenever web/Lnks.page/public_html/index.php determines the
 * page has at least one requiresAgeGate = 1 item (see
 * linkspage_resolver.php's g2ml_linkspageHasAgeGatedItems()) and the visitor
 * does not already carry a valid signed g2ml_agegate cookie (see
 * web/_functions/adult_content.php's g2ml_ageGateHasValidCookie()).
 *
 * 🔒 SECURITY / PRIVACY — this function is the enforcement point that
 * guarantees gated (and non-gated) item destinations are NEVER emitted
 * before confirmation:
 *   - This function takes ONLY the page's slug — no page/template/items
 *     data is passed in or read here at all. It is therefore architecturally
 *     IMPOSSIBLE for any item's itemURL/itemTitle to leak through this
 *     output, because this function never has access to it in the first
 *     place. The caller (index.php) must call this function INSTEAD OF
 *     g2ml_renderLinksPage() while the gate is unconfirmed — never both.
 *   - This is a GOOD-FAITH gate: a single boolean confirmation. No date of
 *     birth (or any other personal data) is ever requested, collected, or
 *     stored — see web/_functions/adult_content.php's file header. The
 *     exact legal-age threshold is not disclosed beyond the generic "18+"
 *     label, per the task's requirement not to reveal the precise value in
 *     a way that would help a minor argue around the gate.
 *   - The Confirm action is a same-origin POST back to this exact slug,
 *     protected by the standard session-backed CSRF pair
 *     (g2ml_csrfField()/g2ml_validateCSRFToken()) — Component C already
 *     starts a PHP session for every visitor (see page_init.php Step 7), so
 *     this reuses the SAME CSRF mechanism the rest of the application uses,
 *     rather than inventing a parallel one.
 *   - The Leave action is a FIXED, hard-coded URL (never derived from any
 *     request parameter, query string, or the slug) — no open-redirect
 *     surface here.
 *   - No inline or external <script> anywhere in this markup — every page
 *     this component serves must work with NO JavaScript at all, per this
 *     component's CSP (script-src 'self', no 'unsafe-inline' — see
 *     web/Lnks.page/public_html/.htaccess).
 *
 * ♿ ACCESSIBILITY: a `<main>` landmark with an `aria-labelledby` heading, a
 * visible (non-colour-only) heading + body text, native keyboard-operable
 * controls (a real <button> and a real <a>) with visible focus outlines,
 * and `autofocus` on the primary action so keyboard/screen-reader users
 * land on it immediately.
 *
 * @param  string $slug  The ALREADY shape-validated slug (see
 *                         g2ml_linkspageIsValidSlug()) for this page — used
 *                         ONLY to build the same-origin form action path and
 *                         the per-page CSRF form name.
 * @return string  A complete, self-contained HTML document.
 */
function g2ml_linkspageRenderAgeGateInterstitial(string $slug): string
{
    $escapedSlug = g2ml_linkspageEscape($slug);

    if (function_exists('__'))
    {
        $pageTitle    = __('linkspage.agegate_title');
        $headingText  = __('linkspage.agegate_heading');
        $bodyText     = __('linkspage.agegate_body');
        $confirmLabel = __('linkspage.agegate_confirm');
        $leaveLabel   = __('linkspage.agegate_leave');
    }
    else
    {
        $pageTitle    = 'Age Verification Required';
        $headingText  = 'Age Verification Required';
        $bodyText     = 'This page contains age-restricted content. Please confirm you are of legal age (18+) to continue.';
        $confirmLabel = 'I am 18 or older — Continue';
        $leaveLabel   = 'Leave this site';
    }

    $csrfFieldHTML = '';

    if (function_exists('g2ml_csrfField'))
    {
        $csrfFieldHTML = g2ml_csrfField('linkspage_agegate_confirm_' . $slug);
    }

    $escapedTitle   = htmlspecialchars($pageTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $escapedHeading = htmlspecialchars($headingText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $escapedBody    = htmlspecialchars($bodyText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $escapedConfirm = htmlspecialchars($confirmLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $escapedLeave   = htmlspecialchars($leaveLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $html  = '<!DOCTYPE html>' . "\n";
    $html .= '<html lang="en">' . "\n";
    $html .= '<head>' . "\n";
    $html .= '    <meta charset="UTF-8">' . "\n";
    $html .= '    <meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
    $html .= '    <meta name="robots" content="noindex, nofollow">' . "\n";
    $html .= '    <title>' . $escapedTitle . '</title>' . "\n";
    $html .= '    <style>' . "\n";
    $html .= '        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f8f9fa; color: #212529; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 1.5rem; box-sizing: border-box; }' . "\n";
    $html .= '        .lp-agegate-container { max-width: 480px; width: 100%; text-align: center; background: #ffffff; border: 1px solid #dee2e6; border-radius: 12px; padding: 2rem 1.75rem; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08); }' . "\n";
    $html .= '        .lp-agegate-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }' . "\n";
    $html .= '        .lp-agegate-heading { font-size: 1.4rem; font-weight: 700; margin: 0 0 0.75rem; }' . "\n";
    $html .= '        .lp-agegate-body { font-size: 1rem; color: #495057; margin: 0 0 1.5rem; line-height: 1.5; }' . "\n";
    $html .= '        .lp-agegate-actions { display: flex; flex-direction: column; gap: 0.75rem; }' . "\n";
    $html .= '        .lp-agegate-confirm { display: block; width: 100%; padding: 0.75rem 1.25rem; background: #1E88E5; color: #ffffff; border: none; border-radius: 8px; font-weight: 600; font-size: 1rem; cursor: pointer; }' . "\n";
    $html .= '        .lp-agegate-confirm:hover, .lp-agegate-confirm:focus { background: #1668ab; }' . "\n";
    $html .= '        .lp-agegate-confirm:focus-visible { outline: 3px solid #0d47a1; outline-offset: 2px; }' . "\n";
    $html .= '        .lp-agegate-leave { display: block; padding: 0.6rem 1.25rem; background: transparent; color: #495057; border: 1px solid #adb5bd; border-radius: 8px; font-weight: 600; font-size: 0.95rem; text-decoration: none; }' . "\n";
    $html .= '        .lp-agegate-leave:hover, .lp-agegate-leave:focus { background: #e9ecef; }' . "\n";
    $html .= '        .lp-agegate-leave:focus-visible { outline: 3px solid #495057; outline-offset: 2px; }' . "\n";
    $html .= '    </style>' . "\n";
    $html .= '</head>' . "\n";
    $html .= '<body>' . "\n";
    $html .= '    <main class="lp-agegate-container" aria-labelledby="lp-agegate-heading">' . "\n";
    $html .= '        <div class="lp-agegate-icon" aria-hidden="true">&#128286;</div>' . "\n";
    $html .= '        <h1 id="lp-agegate-heading" class="lp-agegate-heading">' . $escapedHeading . '</h1>' . "\n";
    $html .= '        <p class="lp-agegate-body">' . $escapedBody . '</p>' . "\n";
    $html .= '        <div class="lp-agegate-actions">' . "\n";
    $html .= '            <form method="POST" action="/' . $escapedSlug . '">' . "\n";
    $html .= '                ' . $csrfFieldHTML . "\n";
    $html .= '                <input type="hidden" name="g2ml_agegate_confirm" value="1">' . "\n";
    $html .= '                <button type="submit" class="lp-agegate-confirm" autofocus>' . $escapedConfirm . '</button>' . "\n";
    $html .= '            </form>' . "\n";
    $html .= '            <a class="lp-agegate-leave" href="https://go2my.link">' . $escapedLeave . '</a>' . "\n";
    $html .= '        </div>' . "\n";
    $html .= '    </main>' . "\n";
    $html .= '</body>' . "\n";
    $html .= '</html>';

    return $html;
}
