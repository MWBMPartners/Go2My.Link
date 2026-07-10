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
 * 🧪 Unit tests — user HTML/CSS sanitiser (Component C.6, #49)
 * ============================================================================
 *
 * An adversarial XSS payload battery against g2ml_sanitiseUserHTML() /
 * g2ml_sanitiseUserCSS() (web/_functions/html_sanitiser.php), plus the benign
 * cases that must be PRESERVED, the mXSS fixed-point behaviour, the size caps,
 * and the strict CSP string.
 *
 * These are DB-FREE: the sanitiser only needs ext-dom/libxml and (optionally)
 * g2ml_sanitiseURL() from security.php, both loaded below.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.2.0 — Phase 8 (#49)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/security.php';
require_once dirname(__DIR__, 2) . '/web/_functions/html_sanitiser.php';

// ----------------------------------------------------------------------------
// Local helpers — "must NOT contain" and a case-insensitive variant.
// ----------------------------------------------------------------------------

/**
 * Assert that a haystack does NOT contain a needle (case-insensitive).
 *
 * @param  string $needle
 * @param  string $haystack
 * @param  string $message
 * @return void
 */
function g2ml_san_assert_not_contains_ci(string $needle, string $haystack, string $message = ''): void
{
    if (stripos($haystack, $needle) !== false)
    {
        $detail = 'Expected NOT to find ' . g2ml_test_export($needle) . ' within ' . g2ml_test_export($haystack);
        throw new G2mlAssertionError(g2ml_test_format_message($detail, $message));
    }
}

/**
 * The core "is this output safe?" smoke check applied to every payload result:
 * no script element, no on* handler, no javascript:/vbscript: scheme, no
 * iframe/object/embed/svg/math, no expression( CSS call.
 *
 * @param  string $output
 * @param  string $context
 * @return void
 */
function g2ml_san_assert_output_is_inert(string $output, string $context): void
{
    g2ml_san_assert_not_contains_ci('<script', $output, $context . ' — no <script');
    g2ml_san_assert_not_contains_ci('</script', $output, $context . ' — no </script');
    g2ml_san_assert_not_contains_ci('<iframe', $output, $context . ' — no <iframe');
    g2ml_san_assert_not_contains_ci('<object', $output, $context . ' — no <object');
    g2ml_san_assert_not_contains_ci('<embed', $output, $context . ' — no <embed');
    g2ml_san_assert_not_contains_ci('<svg', $output, $context . ' — no <svg');
    g2ml_san_assert_not_contains_ci('<math', $output, $context . ' — no <math');
    g2ml_san_assert_not_contains_ci('<form', $output, $context . ' — no <form');
    g2ml_san_assert_not_contains_ci('<meta', $output, $context . ' — no <meta');
    g2ml_san_assert_not_contains_ci('<link', $output, $context . ' — no <link');
    g2ml_san_assert_not_contains_ci('<base', $output, $context . ' — no <base');
    g2ml_san_assert_not_contains_ci('javascript:', $output, $context . ' — no javascript: scheme');
    g2ml_san_assert_not_contains_ci('vbscript:', $output, $context . ' — no vbscript: scheme');
    g2ml_san_assert_not_contains_ci('expression(', $output, $context . ' — no CSS expression(');

    if (preg_match('/\son[a-z]+\s*=/i', $output) === 1)
    {
        throw new G2mlAssertionError($context . ' — an on* event-handler attribute survived: ' . $output);
    }
}

// ============================================================================
// 🧨 XSS payload battery — each must be neutralised
// ============================================================================

test('sanitiser: <script> is stripped, surrounding text kept', function (): void
{
    $out = g2ml_sanitiseUserHTML('<script>alert(1)</script>Hello');
    g2ml_san_assert_output_is_inert($out, 'script');
    assert_contains('Hello', $out, 'Benign trailing text must survive');
    assert_false(str_contains($out, 'alert(1)'), 'The script body must not survive as markup');
});

test('sanitiser: <img onerror> — the handler is removed', function (): void
{
    $out = g2ml_sanitiseUserHTML('<img src=x onerror="alert(1)">');
    g2ml_san_assert_output_is_inert($out, 'img onerror');
});

test('sanitiser: <svg/onload> is removed entirely', function (): void
{
    $out = g2ml_sanitiseUserHTML('<svg/onload=alert(1)>');
    g2ml_san_assert_output_is_inert($out, 'svg onload');
    assert_same('', trim($out), 'A bare <svg onload> yields no output at all');
});

test('sanitiser: javascript: href is removed', function (): void
{
    $out = g2ml_sanitiseUserHTML('<a href="javascript:alert(1)">click</a>');
    g2ml_san_assert_output_is_inert($out, 'js href');
    assert_contains('click', $out, 'The link text is kept even though the href is dropped');
    assert_false(str_contains($out, 'href='), 'No href attribute must remain');
});

test('sanitiser: control-char-obfuscated javascript href is removed', function (): void
{
    // &#9; decodes to a TAB inside "java<TAB>script:" — a classic filter bypass.
    $out = g2ml_sanitiseUserHTML('<a href="java&#9;script:alert(1)">click</a>');
    g2ml_san_assert_output_is_inert($out, 'js href tab');
    assert_false(str_contains($out, 'href='), 'The obfuscated javascript href must be dropped');
});

test('sanitiser: entity-encoded javascript href is removed', function (): void
{
    $out = g2ml_sanitiseUserHTML('<a href="&#106;avascript:alert(1)">x</a>');
    g2ml_san_assert_output_is_inert($out, 'entity handler');
    assert_false(str_contains($out, 'href='), 'The entity-encoded javascript href must be dropped');
});

test('sanitiser: vbscript: href is removed', function (): void
{
    $out = g2ml_sanitiseUserHTML('<a href="vbscript:msgbox(1)">x</a>');
    g2ml_san_assert_output_is_inert($out, 'vbscript href');
    assert_false(str_contains($out, 'href='), 'The vbscript href must be dropped');
});

test('sanitiser: data:text/html href is removed', function (): void
{
    $out = g2ml_sanitiseUserHTML('<a href="data:text/html,<script>alert(1)</script>">x</a>');
    g2ml_san_assert_output_is_inert($out, 'data html href');
    assert_false(str_contains($out, 'href='), 'A data:text/html href must be dropped');
});

test('sanitiser: <iframe> is removed with its subtree', function (): void
{
    $out = g2ml_sanitiseUserHTML('<iframe src="https://evil.example/x"></iframe>after');
    g2ml_san_assert_output_is_inert($out, 'iframe');
    assert_contains('after', $out, 'Trailing text survives');
});

test('sanitiser: <style>@import is removed with its subtree', function (): void
{
    $out = g2ml_sanitiseUserHTML('<style>@import url(https://evil.example/x.css)</style>body');
    g2ml_san_assert_output_is_inert($out, 'style import');
    assert_contains('body', $out, 'Trailing text survives');
    g2ml_san_assert_not_contains_ci('@import', $out, 'No @import survives');
});

test('sanitiser: uppercase OnErRoR is removed', function (): void
{
    $out = g2ml_sanitiseUserHTML('<IMG SRC=x OnErRoR=alert(1)>');
    g2ml_san_assert_output_is_inert($out, 'mixed-case onerror');
});

test('sanitiser: nested/broken markup (mXSS shape) yields no script', function (): void
{
    $out = g2ml_sanitiseUserHTML('<div><p>hi<script>alert(1)</p></div>');
    g2ml_san_assert_output_is_inert($out, 'nested broken');
    assert_contains('hi', $out, 'Benign text survives');
});

test('sanitiser: mXSS via <noscript> raw-text is neutralised', function (): void
{
    $payload = '<noscript><p title="</noscript><img src=x onerror=alert(1)>">';
    $out     = g2ml_sanitiseUserHTML($payload);
    g2ml_san_assert_output_is_inert($out, 'noscript mXSS');
});

test('sanitiser: <form>/<input> are removed with subtree', function (): void
{
    $out = g2ml_sanitiseUserHTML('<form action="/x"><input name="a"></form>keep');
    g2ml_san_assert_output_is_inert($out, 'form');
    assert_contains('keep', $out, 'Trailing text survives');
    g2ml_san_assert_not_contains_ci('<input', $out, 'No <input survives');
});

test('sanitiser: inline style expression() is neutralised', function (): void
{
    $out = g2ml_sanitiseUserHTML('<div style="width:expression(alert(1));color:red">hi</div>');
    g2ml_san_assert_output_is_inert($out, 'style expression');
    assert_contains('hi', $out, 'Content survives');
});

test('sanitiser: inline style url(javascript:) is neutralised', function (): void
{
    $out = g2ml_sanitiseUserHTML('<div style="background:url(javascript:alert(1))">hi</div>');
    g2ml_san_assert_output_is_inert($out, 'style url js');
    assert_contains('hi', $out, 'Content survives');
});

test('sanitiser: HTML comments (conditional-comment vector) are removed', function (): void
{
    $out = g2ml_sanitiseUserHTML('a<!--[if IE]><script>alert(1)</script><![endif]-->b');
    g2ml_san_assert_output_is_inert($out, 'conditional comment');
    g2ml_san_assert_not_contains_ci('<!--', $out, 'No HTML comment survives');
});

test('sanitiser: data:image/svg src is rejected (SVG can carry script)', function (): void
{
    $out = g2ml_sanitiseUserHTML('<img src="data:image/svg+xml,<svg onload=alert(1)>">');
    g2ml_san_assert_output_is_inert($out, 'svg data img');
    g2ml_san_assert_not_contains_ci('data:image/svg', $out, 'A data:image/svg src must be dropped');
});

// ============================================================================
// ✅ Benign content must be PRESERVED (a sanitiser that destroys everything is
//    useless — prove it keeps the good parts)
// ============================================================================

test('sanitiser: benign rich HTML is preserved', function (): void
{
    $in  = '<h1>Title</h1><p class="lead">Hello <strong>world</strong> '
        . '<a href="https://ok.example">link</a></p>'
        . '<img src="https://ok.example/a.png" alt="pic">';
    $out = g2ml_sanitiseUserHTML($in);

    g2ml_san_assert_output_is_inert($out, 'benign');
    assert_contains('<h1>Title</h1>', $out, 'Heading preserved');
    assert_contains('<strong>world</strong>', $out, 'Strong preserved');
    assert_contains('href="https://ok.example"', $out, 'https href preserved');
    assert_contains('src="https://ok.example/a.png"', $out, 'https img src preserved');
    assert_contains('class="lead"', $out, 'class attribute preserved');
});

test('sanitiser: data:image/png src is allowed', function (): void
{
    $out = g2ml_sanitiseUserHTML('<img src="data:image/png;base64,iVBORw0KGgo=" alt="p">');
    g2ml_san_assert_output_is_inert($out, 'data png');
    assert_contains('data:image/png;base64', $out, 'A data:image/png src is allowed');
});

test('sanitiser: relative and anchor hrefs are allowed', function (): void
{
    $out = g2ml_sanitiseUserHTML('<a href="#top">top</a><a href="/about">about</a>');
    g2ml_san_assert_output_is_inert($out, 'relative href');
    assert_contains('href="#top"', $out, 'Anchor href preserved');
    assert_contains('href="/about"', $out, 'Relative href preserved');
});

test('sanitiser: protocol-relative href is rejected', function (): void
{
    $out = g2ml_sanitiseUserHTML('<a href="//evil.example/x">x</a>');
    assert_false(str_contains($out, 'href='), 'A protocol-relative // href must be dropped');
});

test('sanitiser: a table is preserved', function (): void
{
    $out = g2ml_sanitiseUserHTML('<table><tr><td>cell</td></tr></table>');
    assert_contains('<td>cell</td>', $out, 'Table cell content preserved');
});

test('sanitiser: UTF-8 content is preserved', function (): void
{
    $out = g2ml_sanitiseUserHTML('<p>Héllo — wörld 日本語 😀</p>');
    assert_contains('日本語', $out, 'CJK preserved');
    assert_contains('Héllo', $out, 'Accented Latin preserved');
    assert_contains('😀', $out, 'Emoji preserved');
});

// ============================================================================
// 🧬 mXSS fixed-point, caps, and empties
// ============================================================================

test('sanitiser: output is a FIXED POINT (re-sanitising is a no-op)', function (): void
{
    $payloads = [
        '<div><p>hi<script>alert(1)</p></div>',
        '<a href="javascript:alert(1)">x</a>',
        '<div style="width:expression(alert(1))">y</div>',
        '<h1>Title</h1><p>Body <a href="https://ok.example">l</a></p>',
        '<img src="data:image/png;base64,AAAA" alt="a">',
    ];

    foreach ($payloads as $payload)
    {
        $once  = g2ml_sanitiseUserHTML($payload);
        $twice = g2ml_sanitiseUserHTML($once);
        assert_same($once, $twice, 'Sanitising the sanitiser output must not change it: ' . $payload);
    }
});

test('sanitiser: null and empty input return an empty string', function (): void
{
    assert_same('', g2ml_sanitiseUserHTML(null), 'null -> empty');
    assert_same('', g2ml_sanitiseUserHTML(''), 'empty -> empty');
});

test('sanitiser: oversized HTML is capped, still inert', function (): void
{
    $big = str_repeat('<p>x</p>', 40000) . '<script>alert(1)</script>';
    $out = g2ml_sanitiseUserHTML($big);
    assert_true(strlen($out) <= G2ML_CUSTOM_HTML_MAX_BYTES * 2, 'Output is bounded');
    g2ml_san_assert_not_contains_ci('<script', $out, 'Even a capped huge input stays script-free');
});

// ============================================================================
// 🎨 CSS sanitiser
// ============================================================================

test('css: @import, expression, url(javascript:), and </style> breakout are neutralised', function (): void
{
    $in  = '@import url(https://evil.example/x.css); '
        . 'body{background:url(javascript:alert(1))} '
        . '.a{width:expression(alert(1))} '
        . '.c{content:"</style><script>alert(1)</script>"}';
    $out = g2ml_sanitiseUserCSS($in);

    g2ml_san_assert_not_contains_ci('@import', $out, 'No @import');
    g2ml_san_assert_not_contains_ci('expression(', $out, 'No expression(');
    g2ml_san_assert_not_contains_ci('javascript:', $out, 'No javascript:');
    // The single most important CSS property: it can never break out of <style>.
    assert_false(str_contains($out, '<'), 'No "<" may survive — a </style> breakout is impossible');
});

test('css: url(https) and url(data:image/png) are allowed', function (): void
{
    $out = g2ml_sanitiseUserCSS('.a{background:url(https://ok.example/i.png)} .b{background:url(data:image/png;base64,AAAA)}');
    assert_contains("url('https://ok.example/i.png')", $out, 'https url allowed');
    assert_contains('data:image/png;base64,AAAA', $out, 'data:image/png url allowed');
});

test('css: url(data:image/svg) is rejected', function (): void
{
    $out = g2ml_sanitiseUserCSS('.a{background:url(data:image/svg+xml,<svg onload=alert(1)>)}');
    g2ml_san_assert_not_contains_ci('data:image/svg', $out, 'A data:image/svg CSS url must be dropped');
    assert_false(str_contains($out, '<'), 'No "<" survives');
});

test('css: -moz-binding and behavior: are neutralised', function (): void
{
    $out = g2ml_sanitiseUserCSS('.a{-moz-binding:url(https://evil.example/x.xml#y)} .b{behavior:url(x.htc)}');
    g2ml_san_assert_not_contains_ci('-moz-binding', $out, 'No -moz-binding');
    g2ml_san_assert_not_contains_ci('behavior:', $out, 'No behavior:');
});

test('css: null and empty return empty', function (): void
{
    assert_same('', g2ml_sanitiseUserCSS(null), 'null -> empty');
    assert_same('', g2ml_sanitiseUserCSS(''), 'empty -> empty');
});

// ============================================================================
// 🛡️ The strict CSP backstop
// ============================================================================

test('csp: the custom-HTML CSP has script-src none and the required directives', function (): void
{
    $csp = g2ml_linkspageCustomHtmlCSP();
    assert_contains("script-src 'none'", $csp, 'script-src none is the backstop');
    assert_contains("object-src 'none'", $csp, 'object-src none');
    assert_contains("base-uri 'none'", $csp, 'base-uri none');
    assert_contains("frame-ancestors 'self'", $csp, 'frame-ancestors self');
    assert_contains("form-action 'self'", $csp, 'form-action self');
    assert_contains("default-src 'self'", $csp, 'default-src self');
    assert_contains("style-src 'self' 'unsafe-inline'", $csp, 'style-src');
    assert_contains("img-src 'self' https: data:", $csp, 'img-src');
});
