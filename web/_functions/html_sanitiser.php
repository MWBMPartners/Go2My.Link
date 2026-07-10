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
 * 🧼 Go2My.Link — User HTML / CSS Sanitiser (Component C.6, #49)
 * ============================================================================
 *
 * The single strongest stored-XSS surface in the whole product: LinksPage
 * custom HTML / CSS. An owner may supply free-form HTML (typed, or uploaded as
 * a .html file) and CSS that REPLACES the escaped system-template render. This
 * file is the sanitiser that makes that safe, and the gate helpers that decide
 * whether the feature may run at all.
 *
 * 🛡️ DEFENCE IN DEPTH — this file is layer 1 (sanitisation); it is NOT the only
 * layer. The feature is also (a) OFF by default behind an operator kill-switch
 * (linkspage.custom_html_enabled), (b) PREMIUM-gated per-org via the #146
 * entitlement layer (hasCustomHTML), and (c) served under a strict per-response
 * Content-Security-Policy with `script-src 'none'` (g2ml_linkspageCustomHtmlCSP())
 * so that NO inline/external script and NO inline event handler can execute even
 * if a payload were to slip past this sanitiser. Sanitisation runs BOTH on input
 * (before the value is stored) AND on output (again, at render time).
 *
 * 🧩 SANITISER APPROACH — a rigorous DOM allowlist, NOT regex.
 *   Regex "sanitisers" are notoriously bypassable; this uses PHP's built-in
 *   ext-dom / libxml (always present on Dreamhost — no Composer needed) to
 *   PARSE the markup into a real DOM tree, then walk it:
 *     - a strict TAG allowlist (inert, non-scriptable, non-interactive
 *       elements only). Any tag not on the allowlist is either removed WITH
 *       its whole subtree (the dangerous / raw-text / interactive /
 *       foreign-content set: script, style, iframe, object, embed, form,
 *       input, svg, math, template, noscript, title, xmp, …) or UNWRAPPED
 *       (a benign unknown wrapper is dropped but its already-sanitised
 *       children are kept).
 *     - a strict ATTRIBUTE allowlist (href, src, alt, title, class, id, style
 *       — and ONLY those). EVERY `on*` event-handler attribute is stripped.
 *     - href is forced through an http/https/relative/anchor allowlist so
 *       javascript:, data:, vbscript: (and control-char-obfuscated variants
 *       such as `java\tscript:`) can never reach a live href.
 *     - src is forced through an http/https/relative/`data:image` (NON-SVG)
 *       allowlist — `data:image/svg+xml` is rejected because SVG can carry
 *       script.
 *     - inline `style` is run through the same CSS cleaner as the stylesheet
 *       (strips expression(), url(javascript:), @import, behavior, -moz-binding
 *       and any url() that is not a data:image or same-origin reference; and
 *       strips `<` so a value can never break out of the <style> element).
 *   Why DOM and not a vendored library (e.g. HTML Purifier)? No Composer on the
 *   host; HTML Purifier is a large multi-file dependency that also writes a
 *   serialiser cache to disk — a poor fit for Dreamhost shared hosting. A
 *   single-file, fully-auditable DOM allowlist parser PLUS the `script-src
 *   'none'` CSP backstop is the pragmatic, defensible choice, and it is
 *   re-run on output so a stored value is never trusted.
 *
 * 🧬 mXSS (mutation-XSS) DEFENCE — the sanitiser is run REPEATEDLY on its own
 *   output until the output stops changing (a fixed point). Markup that mutates
 *   into something different when re-parsed — the classic mXSS shape — never
 *   reaches a stable fixed point within the pass budget, and such input falls
 *   back to the safest possible rendering: ALL tags stripped, text only.
 *
 * These functions are deliberately self-contained (function_exists() guards
 * around g2ml_sanitiseURL()/getSetting()/g2ml_canUseFeature()) so this file —
 * and its unit tests — run without a database or the full bootstrap, mirroring
 * the pattern already used by web/Lnks.page/_functions/linkspage_renderer.php.
 *
 * Functions:
 *   - g2ml_sanitiseUserHTML()               — the DOM allowlist HTML sanitiser
 *   - g2ml_sanitiseUserCSS()                — the stylesheet CSS sanitiser
 *   - g2ml_linkspageCustomHtmlCSP()         — the strict per-response CSP string
 *   - g2ml_linkspageCustomHtmlKillSwitchEnabled() — operator kill-switch check
 *   - g2ml_linkspageCustomHtmlAllowedForOrg()      — kill-switch AND premium gate
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    1.0.0
 * @since      v1.2.0 — Phase 8 (#49)
 *
 * 📖 References:
 *     - Renderer:     web/Lnks.page/_functions/linkspage_renderer.php (#45; custom-HTML render path #49)
 *     - Resolver:     web/Lnks.page/_functions/linkspage_resolver.php (#45; gated custom-HTML fetch #49)
 *     - Manage:       web/_functions/linkspage_manage.php (#48; custom-HTML save/upload #49)
 *     - Entitlements: web/_functions/entitlements.php (#146; hasCustomHTML flag)
 *     - Kill-switch:  linkspage.custom_html_enabled (web/_sql/seeds/003_default_settings.sql, default OFF)
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
// 📋 Caps and the strict Content-Security-Policy
// ============================================================================

/** Hard byte cap on the raw custom-HTML input (before sanitisation). */
if (!defined('G2ML_CUSTOM_HTML_MAX_BYTES'))
{
    define('G2ML_CUSTOM_HTML_MAX_BYTES', 100000);
}

/** Hard byte cap on the raw custom-CSS input (before sanitisation). */
if (!defined('G2ML_CUSTOM_CSS_MAX_BYTES'))
{
    define('G2ML_CUSTOM_CSS_MAX_BYTES', 50000);
}

/** Maximum number of stabilising sanitisation passes before falling back to text-only. */
if (!defined('G2ML_CUSTOM_HTML_MAX_PASSES'))
{
    define('G2ML_CUSTOM_HTML_MAX_PASSES', 4);
}

/**
 * The strict per-response Content-Security-Policy served with ANY LinksPage
 * that renders custom HTML. `script-src 'none'` is the backstop: no inline or
 * external script and no inline event handler can execute even if the
 * sanitiser were imperfect.
 *
 * @return string
 */
function g2ml_linkspageCustomHtmlCSP(): string
{
    return "default-src 'self'; "
        . "script-src 'none'; "
        . "object-src 'none'; "
        . "base-uri 'none'; "
        . "frame-ancestors 'self'; "
        . "form-action 'self'; "
        . "style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' https: data:; "
        . "font-src 'self' data:";
}

// ============================================================================
// 📚 Allowlists
// ============================================================================

/**
 * The strict TAG allowlist — inert, non-scriptable, non-interactive elements
 * only. Anything not here is removed-with-subtree (if dangerous/raw-text) or
 * unwrapped (if a benign unknown wrapper).
 *
 * @return array<int, string>
 */
function _g2ml_htmlSanitiserAllowedTags(): array
{
    return [
        'a', 'p', 'div', 'span', 'br', 'hr',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        'strong', 'em', 'b', 'i', 'u', 's', 'small', 'sub', 'sup',
        'mark', 'code', 'pre', 'abbr', 'cite', 'q', 'del', 'ins', 'time', 'wbr',
        'img', 'figure', 'figcaption', 'blockquote',
        'section', 'header', 'footer', 'main', 'article', 'aside', 'nav',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'caption', 'colgroup', 'col',
    ];
}

/**
 * Tags removed together with their ENTIRE subtree — the dangerous, raw-text,
 * interactive, and foreign-content elements. Removing (not unwrapping) these
 * is deliberate: unwrapping a <script>/<style>/<title>/<xmp> would surface its
 * raw text content, which is exactly what mXSS relies on.
 *
 * @return array<int, string>
 */
function _g2ml_htmlSanitiserRemoveSubtreeTags(): array
{
    return [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed',
        'applet', 'param', 'base', 'link', 'meta', 'head', 'html', 'body',
        'form', 'input', 'textarea', 'button', 'select', 'option', 'optgroup',
        'datalist', 'output', 'svg', 'math', 'template', 'noscript', 'noembed',
        'noframes', 'title', 'xmp', 'plaintext', 'listing', 'audio', 'video',
        'source', 'track', 'canvas', 'keygen', 'bgsound', 'blink', 'marquee',
    ];
}

/**
 * The strict ATTRIBUTE allowlist — and ONLY these.
 *
 * @return array<int, string>
 */
function _g2ml_htmlSanitiserAllowedAttributes(): array
{
    return ['href', 'src', 'alt', 'title', 'class', 'id', 'style'];
}

// ============================================================================
// 🌐 URL value sanitisers (href / src)
// ============================================================================

/**
 * Strip every ASCII control character (C0 range plus DEL) and surrounding
 * whitespace from a URL-ish value BEFORE scheme detection, so an
 * obfuscated scheme such as `java\tscript:` / `java\nscript:` / `  javascript:`
 * can never survive as a live scheme.
 *
 * @param  string $value
 * @return string
 */
function _g2ml_htmlSanitiserStripUrlControlChars(string $value): string
{
    $stripped = preg_replace('/[\x00-\x20\x7F]+/', '', $value);

    if ($stripped === null)
    {
        return '';
    }

    return $stripped;
}

/**
 * Does this value carry an explicit URL scheme (e.g. `http:`, `javascript:`)?
 *
 * @param  string $value  A value that has ALREADY had control chars stripped.
 * @return bool
 */
function _g2ml_htmlSanitiserHasScheme(string $value): bool
{
    if (preg_match('/^[A-Za-z][A-Za-z0-9+.\-]*:/', $value) === 1)
    {
        return true;
    }

    return false;
}

/**
 * Validate an <a href> value against a strict allowlist: an anchor (#…), a
 * same-origin relative reference, or an absolute http/https URL. Everything
 * else — javascript:, data:, vbscript:, mailto:, tel:, protocol-relative //,
 * etc. — is rejected (the attribute is dropped by the caller).
 *
 * @param  string $rawValue
 * @return string|false  The safe value, or false when it must be removed.
 */
function _g2ml_htmlSanitiserSanitiseHref(string $rawValue): string|false
{
    $value = _g2ml_htmlSanitiserStripUrlControlChars($rawValue);

    if ($value === '')
    {
        return false;
    }

    // A pure fragment / same-page anchor is always safe.
    if (str_starts_with($value, '#'))
    {
        return $value;
    }

    // Protocol-relative ("//host/…") is treated as an off-origin absolute and
    // is NOT allowed here (it could load a foreign origin).
    if (str_starts_with($value, '//'))
    {
        return false;
    }

    // No scheme at all → a same-origin relative reference ("/path", "path",
    // "./path", "?q=1"). Safe.
    if (!_g2ml_htmlSanitiserHasScheme($value))
    {
        return $value;
    }

    // Has a scheme → require http/https only.
    if (function_exists('g2ml_sanitiseURL'))
    {
        $sanitised = g2ml_sanitiseURL($value);

        if ($sanitised === false || $sanitised === '')
        {
            return false;
        }

        return $sanitised;
    }

    $scheme = parse_url($value, PHP_URL_SCHEME);

    if (is_string($scheme) && in_array(strtolower($scheme), ['http', 'https'], true))
    {
        return $value;
    }

    return false;
}

/**
 * Validate an <img src> value: an absolute http/https URL, a same-origin
 * relative reference, or a `data:image/…` of a NON-SVG raster type. A
 * `data:image/svg+xml` is rejected outright (SVG can carry script).
 *
 * @param  string $rawValue
 * @return string|false  The safe value, or false when it must be removed.
 */
function _g2ml_htmlSanitiserSanitiseImageSrc(string $rawValue): string|false
{
    $value = _g2ml_htmlSanitiserStripUrlControlChars($rawValue);

    if ($value === '')
    {
        return false;
    }

    // Allowlisted data: image types (explicitly NOT svg).
    if (preg_match('#^data:image/(png|jpe?g|gif|webp|avif|bmp|x-icon|vnd\.microsoft\.icon)[;,]#i', $value) === 1)
    {
        return $value;
    }

    // Any other data:/blob:/etc. URI is rejected.
    if (stripos($value, 'data:') === 0 || stripos($value, 'blob:') === 0)
    {
        return false;
    }

    if (str_starts_with($value, '//'))
    {
        return false;
    }

    // No scheme → same-origin relative reference. Safe.
    if (!_g2ml_htmlSanitiserHasScheme($value))
    {
        return $value;
    }

    // Has a scheme → http/https only (CSP img-src also permits https:).
    if (function_exists('g2ml_sanitiseURL'))
    {
        $sanitised = g2ml_sanitiseURL($value);

        if ($sanitised === false || $sanitised === '')
        {
            return false;
        }

        return $sanitised;
    }

    $scheme = parse_url($value, PHP_URL_SCHEME);

    if (is_string($scheme) && in_array(strtolower($scheme), ['http', 'https'], true))
    {
        return $value;
    }

    return false;
}

// ============================================================================
// 🎨 CSS sanitisation (stylesheet + inline style)
// ============================================================================

/**
 * Validate the target INSIDE a CSS url(...) reference. Allows a NON-SVG
 * data:image, or a same-origin relative reference, or an https:// absolute
 * URL. Everything else resolves to the harmless keyword `none`.
 *
 * @param  string $inner  The raw content between the url( and ).
 * @return string  A safe `url('…')` fragment, or the literal `none`.
 */
function _g2ml_htmlSanitiserSanitiseCssUrl(string $inner): string
{
    $trimmed = trim($inner);
    $trimmed = trim($trimmed, "\"'");
    $trimmed = _g2ml_htmlSanitiserStripUrlControlChars($trimmed);

    if ($trimmed === '')
    {
        return 'none';
    }

    if (preg_match('#^data:image/(png|jpe?g|gif|webp|avif|bmp|x-icon|vnd\.microsoft\.icon)[;,]#i', $trimmed) === 1)
    {
        return "url('" . $trimmed . "')";
    }

    if (preg_match('#^https://#i', $trimmed) === 1)
    {
        return "url('" . $trimmed . "')";
    }

    // Any remaining scheme (including a leftover colon), protocol-relative, or
    // data:/blob: reference is rejected.
    if (str_starts_with($trimmed, '//') || str_contains($trimmed, ':'))
    {
        return 'none';
    }

    // Same-origin relative reference.
    return "url('" . $trimmed . "')";
}

/**
 * Core CSS token cleaner shared by the stylesheet sanitiser and the inline
 * `style=""` attribute sanitiser. Strips comments, neutralises the classic
 * dangerous CSS constructs, validates every url(), and strips `<` so a value
 * can never break out of a surrounding <style> element.
 *
 * @param  string $css
 * @return string
 */
function _g2ml_htmlSanitiserCleanCssTokens(string $css): string
{
    // Remove NUL and C0 control characters except tab/newline/carriage-return.
    $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $css);

    if ($clean === null)
    {
        return '';
    }

    // Strip CSS comments FIRST so a keyword cannot be hidden as expr/**/ession.
    $clean = preg_replace('#/\*.*?\*/#s', '', $clean);

    if ($clean === null)
    {
        return '';
    }

    // Strip `<` entirely — CSS never needs it, and this prevents a `</style>`
    // (or any tag) breakout when the value is placed inside a <style> element.
    $clean = str_replace('<', '', $clean);

    // Remove @import rules entirely (external resource load / smuggling).
    $clean = preg_replace('/@import[^;]*;?/i', '', $clean);

    if ($clean === null)
    {
        return '';
    }

    // Neutralise dead-but-dangerous constructs (IE/legacy). Rendered inert by
    // renaming the keyword so it can never be parsed as the real construct.
    // 🔁 Each replacement MUST be idempotent — its output must NOT re-match its
    // own pattern — otherwise a benign value containing the keyword would keep
    // changing on every re-sanitisation pass and be wrongly treated as mXSS.
    $clean = preg_replace('/expression\s*\(/i', 'blocked(', $clean);

    if ($clean === null)
    {
        return '';
    }

    $clean = preg_replace('/-moz-binding/i', 'blockedbinding', $clean);

    if ($clean === null)
    {
        return '';
    }

    $clean = preg_replace('/behaviou?r\s*:/i', 'blockedbhvr:', $clean);

    if ($clean === null)
    {
        return '';
    }

    // Remove any javascript:/vbscript: scheme text wherever it appears.
    $clean = preg_replace('/(javascript|vbscript)\s*:/i', 'blocked:', $clean);

    if ($clean === null)
    {
        return '';
    }

    // Validate every url(...) reference.
    $clean = preg_replace_callback(
        '/url\s*\(([^)]*)\)/i',
        function (array $matches): string
        {
            return _g2ml_htmlSanitiserSanitiseCssUrl($matches[1]);
        },
        $clean
    );

    if ($clean === null)
    {
        return '';
    }

    return $clean;
}

/**
 * Sanitise a full user-supplied CSS stylesheet for safe injection into a
 * scoped <style> block. Byte-capped, comment-stripped, dangerous-construct
 * neutralised, url()-validated, and `<`-stripped.
 *
 * @param  string|null $css
 * @return string
 */
function g2ml_sanitiseUserCSS(?string $css): string
{
    if ($css === null)
    {
        return '';
    }

    if ($css === '')
    {
        return '';
    }

    if (strlen($css) > G2ML_CUSTOM_CSS_MAX_BYTES)
    {
        $css = substr($css, 0, G2ML_CUSTOM_CSS_MAX_BYTES);
    }

    return trim(_g2ml_htmlSanitiserCleanCssTokens($css));
}

/**
 * Sanitise a single inline `style=""` attribute value (a declaration list).
 *
 * @param  string $style
 * @return string
 */
function _g2ml_htmlSanitiserSanitiseInlineStyle(string $style): string
{
    if (strlen($style) > G2ML_CUSTOM_CSS_MAX_BYTES)
    {
        $style = substr($style, 0, G2ML_CUSTOM_CSS_MAX_BYTES);
    }

    return trim(_g2ml_htmlSanitiserCleanCssTokens($style));
}

// ============================================================================
// 🌳 DOM walk
// ============================================================================

/**
 * Sanitise the attributes of an ALLOWED element in place: drop everything not
 * on the attribute allowlist, drop every on* handler, and force href/src/style
 * through their value sanitisers.
 *
 * @param  DOMElement $element
 * @return void
 */
function _g2ml_htmlSanitiserSanitiseAttributes(DOMElement $element): void
{
    $allowedAttributes = _g2ml_htmlSanitiserAllowedAttributes();

    // Snapshot the attribute names first — removing attributes mutates the
    // live NamedNodeMap we would otherwise be iterating.
    $attributeNames = [];

    if ($element->attributes !== null)
    {
        foreach ($element->attributes as $attributeNode)
        {
            $attributeNames[] = $attributeNode->nodeName;
        }
    }

    foreach ($attributeNames as $attributeName)
    {
        $lowerName = strtolower($attributeName);

        // Every on* event handler is stripped, unconditionally.
        if (str_starts_with($lowerName, 'on'))
        {
            $element->removeAttribute($attributeName);
            continue;
        }

        if (!in_array($lowerName, $allowedAttributes, true))
        {
            $element->removeAttribute($attributeName);
            continue;
        }

        $rawValue = $element->getAttribute($attributeName);

        if ($lowerName === 'href')
        {
            $safeHref = _g2ml_htmlSanitiserSanitiseHref($rawValue);

            if ($safeHref === false)
            {
                $element->removeAttribute($attributeName);
            }
            else
            {
                $element->setAttribute($attributeName, $safeHref);
            }

            continue;
        }

        if ($lowerName === 'src')
        {
            $safeSrc = _g2ml_htmlSanitiserSanitiseImageSrc($rawValue);

            if ($safeSrc === false)
            {
                $element->removeAttribute($attributeName);
            }
            else
            {
                $element->setAttribute($attributeName, $safeSrc);
            }

            continue;
        }

        if ($lowerName === 'style')
        {
            $safeStyle = _g2ml_htmlSanitiserSanitiseInlineStyle($rawValue);

            if ($safeStyle === '')
            {
                $element->removeAttribute($attributeName);
            }
            else
            {
                $element->setAttribute($attributeName, $safeStyle);
            }

            continue;
        }

        // alt / title / class / id — free text; the serialiser escapes the
        // value, so no breakout is possible. Left as-is.
    }
}

/**
 * Recursively sanitise one element: remove-with-subtree if dangerous, else
 * recurse into its children and finally either keep it (allowlisted, with
 * attributes sanitised) or unwrap it (benign unknown wrapper).
 *
 * @param  DOMElement $element
 * @return void
 */
function _g2ml_htmlSanitiserSanitiseElement(DOMElement $element): void
{
    $parent = $element->parentNode;

    if ($parent === null)
    {
        return;
    }

    $tag = strtolower($element->tagName);

    // A namespaced/prefixed tag (e.g. an injected foreign element) is treated
    // as suspicious and removed with its subtree.
    if (str_contains($tag, ':') || in_array($tag, _g2ml_htmlSanitiserRemoveSubtreeTags(), true))
    {
        $parent->removeChild($element);
        return;
    }

    // Recurse into (a snapshot of) the children BEFORE deciding this element's
    // own fate, so that if we unwrap, the children promoted upward are already
    // fully sanitised.
    _g2ml_htmlSanitiserSanitiseChildren($element);

    if (in_array($tag, _g2ml_htmlSanitiserAllowedTags(), true))
    {
        _g2ml_htmlSanitiserSanitiseAttributes($element);
        return;
    }

    // Benign, non-allowlisted wrapper: unwrap it (keep sanitised children).
    while ($element->firstChild !== null)
    {
        $parent->insertBefore($element->firstChild, $element);
    }

    $parent->removeChild($element);
}

/**
 * Sanitise every child of a node. Elements recurse; text is kept; comments,
 * processing instructions, CDATA, and anything else are removed.
 *
 * @param  DOMNode $node
 * @return void
 */
function _g2ml_htmlSanitiserSanitiseChildren(DOMNode $node): void
{
    if (!$node->hasChildNodes())
    {
        return;
    }

    // Snapshot the child list — the walk mutates it.
    $children = [];

    foreach ($node->childNodes as $child)
    {
        $children[] = $child;
    }

    foreach ($children as $child)
    {
        if ($child instanceof DOMElement)
        {
            _g2ml_htmlSanitiserSanitiseElement($child);
            continue;
        }

        if ($child instanceof DOMText)
        {
            // Kept — its value is escaped on serialisation.
            continue;
        }

        // DOMComment, DOMProcessingInstruction, DOMCdataSection, entity refs,
        // and everything else are removed.
        $node->removeChild($child);
    }
}

/**
 * Run ONE sanitisation pass over an HTML fragment and return the serialised,
 * sanitised result.
 *
 * @param  string $html
 * @return string
 */
function _g2ml_sanitiseUserHTMLOnePass(string $html): string
{
    if (trim($html) === '')
    {
        return '';
    }

    // Convert every non-ASCII byte to a numeric entity so libxml (which
    // assumes ISO-8859-1) preserves UTF-8 content correctly. The mask MUST be
    // 0x1FFFFF (21 all-ones bits) — a mask of 0x10FFFF would AND-corrupt any
    // supplementary-plane codepoint (e.g. emoji) into the wrong character.
    $encoded = mb_encode_numericentity(
        $html,
        [0x80, 0x10FFFF, 0, 0x1FFFFF],
        'UTF-8'
    );

    $document = new DOMDocument('1.0', 'UTF-8');

    $previousUseErrors = libxml_use_internal_errors(true);

    // LIBXML_NONET forbids any network access during parse; the explicit
    // <html><body> wrapper gives us a stable body to read children back from.
    $loaded = $document->loadHTML(
        '<html><body>' . $encoded . '</body></html>',
        LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
    );

    libxml_clear_errors();
    libxml_use_internal_errors($previousUseErrors);

    if ($loaded === false)
    {
        return '';
    }

    $bodyList = $document->getElementsByTagName('body');

    if ($bodyList->length === 0)
    {
        return '';
    }

    $body = $bodyList->item(0);

    if (!($body instanceof DOMElement))
    {
        return '';
    }

    _g2ml_htmlSanitiserSanitiseChildren($body);

    // Serialise ONLY the (sanitised) children of body — never the html/body
    // wrappers, and never anything the parser hoisted into <head>.
    $output = '';

    foreach ($body->childNodes as $child)
    {
        $serialised = $document->saveHTML($child);

        if (is_string($serialised))
        {
            $output = $output . $serialised;
        }
    }

    return $output;
}

/**
 * Sanitise user-supplied HTML into a strictly allowlisted, script-free,
 * event-handler-free fragment safe to embed in a page body.
 *
 * The sanitiser is applied REPEATEDLY to its own output until the output stops
 * changing (a fixed point). Input that never stabilises within the pass budget
 * is mutation-XSS-shaped and is rendered as text only (all tags stripped).
 *
 * @param  string|null $html
 * @return string
 */
function g2ml_sanitiseUserHTML(?string $html): string
{
    if ($html === null)
    {
        return '';
    }

    if ($html === '')
    {
        return '';
    }

    if (strlen($html) > G2ML_CUSTOM_HTML_MAX_BYTES)
    {
        $html = substr($html, 0, G2ML_CUSTOM_HTML_MAX_BYTES);
    }

    $current = $html;

    for ($pass = 0; $pass < G2ML_CUSTOM_HTML_MAX_PASSES; $pass++)
    {
        $next = _g2ml_sanitiseUserHTMLOnePass($current);

        if ($next === $current)
        {
            // Reached a fixed point — safe and stable.
            return $next;
        }

        $current = $next;
    }

    // Never stabilised within the pass budget — treat as mutation-XSS-shaped
    // and fall back to the safest possible rendering: text only.
    $textOnly = strip_tags($current);

    return htmlspecialchars($textOnly, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ============================================================================
// 🔌 Feature gate — operator kill-switch + premium entitlement
// ============================================================================

/**
 * The operator kill-switch for the whole custom-HTML feature. DEFAULT OFF:
 * unless linkspage.custom_html_enabled is explicitly truthy ('1'/true/'on'/
 * 'yes'), the feature is disabled everywhere — nothing is saved or rendered.
 *
 * @return bool
 */
function g2ml_linkspageCustomHtmlKillSwitchEnabled(): bool
{
    if (!function_exists('getSetting'))
    {
        // No settings layer available → fail CLOSED (this high-risk feature is
        // OFF unless an operator has explicitly turned it on).
        return false;
    }

    $value = getSetting('linkspage.custom_html_enabled', false);

    if ($value === true || $value === 1)
    {
        return true;
    }

    if (is_string($value) && in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true))
    {
        return true;
    }

    return false;
}

/**
 * May the org identified by $orgHandle use custom HTML right now? True only
 * when BOTH the operator kill-switch is on AND the org's tier grants the
 * hasCustomHTML entitlement.
 *
 * Fails CLOSED (returns false) whenever the entitlement layer is unavailable —
 * deliberately stricter than the general #146 fail-OPEN posture, because this
 * is the single highest-risk feature in the product.
 *
 * @param  string|null $orgHandle
 * @return bool
 */
function g2ml_linkspageCustomHtmlAllowedForOrg(?string $orgHandle): bool
{
    if (!g2ml_linkspageCustomHtmlKillSwitchEnabled())
    {
        return false;
    }

    if ($orgHandle === null || $orgHandle === '')
    {
        return false;
    }

    if (!function_exists('g2ml_canUseFeature'))
    {
        return false;
    }

    return g2ml_canUseFeature($orgHandle, 'hasCustomHTML');
}
