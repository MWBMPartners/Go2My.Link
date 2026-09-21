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
 * 🧪 Unit tests — LinksPage management pure validators (Component C.2, #48)
 *                  + template picker pure helpers (Component C.3, #47)
 * ============================================================================
 *
 * Pure, DB-free tests for the validators in web/_functions/linkspage_manage.php:
 *   - g2ml_linkspageManageIsValidSlug()
 *   - g2ml_linkspageManageValidateHexColour()
 *   - g2ml_linkspageManageValidateFontFamily()
 *   - g2ml_linkspageManageValidateSocialLinks()
 *
 * These are DELIBERATE mirrors of the public renderer's own validators
 * (web/Lnks.page/_functions/linkspage_resolver.php /
 * linkspage_renderer.php — see #45's tests/unit/linkspage_render_test.php).
 * Both files are loaded here TOGETHER so this suite can directly cross-check
 * parity: any value the management layer accepts must also be accepted (and
 * behave identically) in the public renderer, and vice versa.
 *
 * web/_functions/security.php is loaded FIRST (mirroring
 * linkspage_render_test.php) so g2ml_linkspageManageValidateSocialLinks()
 * exercises the REAL g2ml_sanitiseURL() integration, not a fallback.
 *
 * Also DB-free, and also added here (since linkspage_renderer.php is already
 * loaded above), the C.3 (#47) template picker helpers:
 *   - g2ml_linkspageManageBuildTemplatePreviewSampleModel() — the FIXED,
 *     non-user sample model used for a picker card's live-render thumbnail.
 *   - g2ml_linkspageManageRenderTemplateCardThumbnail() — live-render
 *     (via the REAL g2ml_renderLinksPage()), static-thumbnail, and
 *     placeholder fallback paths, plus escaping of the fallback path's
 *     stored templateThumbnail value.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.2.0 — Phase 8 (#48; picker helpers #47)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/security.php';
require_once dirname(__DIR__, 2) . '/web/Lnks.page/_functions/linkspage_resolver.php';
require_once dirname(__DIR__, 2) . '/web/Lnks.page/_functions/linkspage_renderer.php';

// linkspage_manage.php's direct-access guard + dbSelect()/dbSelectOne() calls
// (in functions this suite does not exercise) mean it is safe to load here
// without a database connection — only the pure validator functions below
// are invoked, and PHP does not execute a function body until it is called.
require_once dirname(__DIR__, 2) . '/web/_functions/linkspage_manage.php';

// ============================================================================
// 🔤 g2ml_linkspageManageIsValidSlug — parity with the public resolver
// ============================================================================

test('manage slug: alphanumeric with hyphens and underscores is valid', function (): void
{
    assert_true(g2ml_linkspageManageIsValidSlug('jane-doe_92'), 'A normal slug shape must be accepted');
});

test('manage slug: empty string is invalid', function (): void
{
    assert_false(g2ml_linkspageManageIsValidSlug(''), 'An empty slug must be rejected');
});

test('manage slug: a slash (path traversal shape) is invalid', function (): void
{
    assert_false(g2ml_linkspageManageIsValidSlug('../etc/passwd'), 'A slug containing slashes must be rejected');
});

test('manage slug: a slug containing spaces is invalid', function (): void
{
    assert_false(g2ml_linkspageManageIsValidSlug('jane doe'), 'A slug containing spaces must be rejected');
});

test('manage slug: exactly 100 characters is valid, 101 is not', function (): void
{
    $hundred    = str_repeat('a', 100);
    $hundredOne = str_repeat('a', 101);

    assert_true(g2ml_linkspageManageIsValidSlug($hundred), 'A 100-character slug must be accepted (matches VARCHAR(100))');
    assert_false(g2ml_linkspageManageIsValidSlug($hundredOne), 'A 101-character slug must be rejected');
});

test('manage slug: parity — every value accepted here is accepted by the public resolver, and vice versa', function (): void
{
    $samples = ['jane-doe_92', '', '../etc/passwd', 'jane doe', str_repeat('a', 100), str_repeat('a', 101), 'A1_b-2'];

    foreach ($samples as $sample)
    {
        assert_same(
            g2ml_linkspageIsValidSlug($sample),
            g2ml_linkspageManageIsValidSlug($sample),
            'Slug validation must agree between the manage layer and the public resolver for: ' . $sample
        );
    }
});

// ============================================================================
// 🚫 Reserved slugs — #218: some slugs can never be reached on the real
//     server (see G2ML_LINKSPAGE_RESERVED_SLUGS's docblock in
//     linkspage_manage.php), so both validators must refuse them.
//
// #218 review round 1 fixed a real hole in this suite: both files USED TO
// define the exact same constant name, G2ML_LINKSPAGE_RESERVED_SLUGS,
// each behind `if (!defined(...))`. Because this file loads the resolver
// FIRST (line 51 above) and linkspage_manage.php SECOND (line 58), the
// resolver's `define()` always ran first and won here — this file's own
// copy never executed, and BOTH functions below were secretly reading the
// SAME single array the whole time. Every test that looped over
// "the reserved list" was really just checking that array against itself
// twice, which could never catch the two files' lists drifting apart —
// even though catching exactly that drift was the whole point of a
// "parity" test. (In PRODUCTION the load order is the other way around —
// page_init.php requires linkspage_manage.php before this component's own
// index.php requires the resolver — so the constant that "won" here was
// not even the one that wins in production. That mismatch was the bug.)
//
// The fix: the resolver now defines its OWN, separately-named constant,
// G2ML_LINKSPAGE_RESOLVER_RESERVED_SLUGS, so both arrays genuinely exist at
// once regardless of load order, and the test just below directly compares
// their CONTENTS — the actual parity guarantee this feature needs.
// ============================================================================

test('manage slug: the management-layer and resolver reserved-word lists have IDENTICAL contents', function (): void
{
    // This is the test #218 review round 1 asked for: it does not matter
    // which order the two constants happen to be defined in, or which file
    // loads first — this compares the two ARRAYS directly, so a maintainer
    // who adds a word to only one of them fails this test immediately,
    // instead of the two loops below silently checking one shared array
    // against itself.
    $manageList  = G2ML_LINKSPAGE_RESERVED_SLUGS;
    $resolverList = G2ML_LINKSPAGE_RESOLVER_RESERVED_SLUGS;

    sort($manageList);
    sort($resolverList);

    assert_same(
        $manageList,
        $resolverList,
        'linkspage_manage.php\'s G2ML_LINKSPAGE_RESERVED_SLUGS and linkspage_resolver.php\'s G2ML_LINKSPAGE_RESOLVER_RESERVED_SLUGS must contain exactly the same words, or a slug refused in one place can still be saved (or resolved) in the other'
    );
});

test('manage slug: every word in the management layer\'s OWN reserved list is rejected by the management-layer validator', function (): void
{
    // Deliberately loops over G2ML_LINKSPAGE_RESERVED_SLUGS (this file's
    // own constant), not the resolver's, so this test still means something
    // even if the two lists were ever allowed to differ.
    foreach (G2ML_LINKSPAGE_RESERVED_SLUGS as $reservedSlug)
    {
        assert_false(
            g2ml_linkspageManageIsValidSlug($reservedSlug),
            'The reserved slug "' . $reservedSlug . '" must be rejected by the management layer as a reserved word — some of these words are already unreachable, others are reserved ahead of time, but every word on this list is refused (see the constant\'s own docblock for which is which)'
        );
    }
});

test('manage slug: every word in the resolver\'s OWN reserved list is rejected by the public resolver\'s validator', function (): void
{
    // Deliberately loops over G2ML_LINKSPAGE_RESOLVER_RESERVED_SLUGS (the
    // resolver's own constant), not the management layer's, for the same
    // reason as the test above.
    foreach (G2ML_LINKSPAGE_RESOLVER_RESERVED_SLUGS as $reservedSlug)
    {
        assert_false(
            g2ml_linkspageIsValidSlug($reservedSlug),
            'The reserved slug "' . $reservedSlug . '" must be rejected by the public resolver as a reserved word — some of these words are already unreachable, others are reserved ahead of time, but every word on this list is refused (see the constant\'s own docblock for which is which)'
        );
    }
});

test('manage slug: a reserved word is rejected case-insensitively (mixed case, upper case)', function (): void
{
    assert_false(g2ml_linkspageManageIsValidSlug('Index'), 'Mixed-case "Index" must still be rejected by the management layer — it is just as unreachable as "index"');
    assert_false(g2ml_linkspageManageIsValidSlug('ADMIN'), 'Upper-case "ADMIN" must still be rejected by the management layer');
    assert_false(g2ml_linkspageIsValidSlug('Index'), 'Mixed-case "Index" must still be rejected by the public resolver');
    assert_false(g2ml_linkspageIsValidSlug('ADMIN'), 'Upper-case "ADMIN" must still be rejected by the public resolver');
});

test('manage slug: an ordinary, non-reserved slug is still accepted by both validators', function (): void
{
    assert_true(g2ml_linkspageManageIsValidSlug('jane-doe_92'), 'An ordinary slug must not be caught by the reserved-word check');
    assert_true(g2ml_linkspageIsValidSlug('jane-doe_92'), 'An ordinary slug must not be caught by the reserved-word check in the public resolver either');
});

test('manage slug: reserved-word rejection stays in parity between the management layer and the public resolver', function (): void
{
    // array_unique() because, now that the two lists live under different
    // constant names, merging them could otherwise list a shared word
    // twice — harmless for this loop, but unique keeps the intent clear.
    $samples = array_unique(array_merge(
        G2ML_LINKSPAGE_RESERVED_SLUGS,
        G2ML_LINKSPAGE_RESOLVER_RESERVED_SLUGS,
        ['Index', 'ADMIN', 'jane-doe_92']
    ));

    foreach ($samples as $sample)
    {
        assert_same(
            g2ml_linkspageIsValidSlug($sample),
            g2ml_linkspageManageIsValidSlug($sample),
            'Reserved-word slug validation must agree between the manage layer and the public resolver for: ' . $sample
        );
    }
});

// ============================================================================
// 🚫 Leading underscore — #218 review round 1: web/Lnks.page/public_html/
//     .htaccess 403-blocks _includes, _functions, _libraries, _uploads,
//     _backups, _sql and _schemas outright, BEFORE the slug-routing rule
//     ever runs, but the slug charset regex allows a leading underscore
//     through and none of those seven words was in either reserved-word
//     list. A page saved under one of them looked successful and then
//     could never be viewed — the visitor got this component's OWN branded
//     "forbidden" page instead of the LinksPage they expected (.htaccess
//     routes a 403 to `ErrorDocument 403 /index.php?http_error=403`, which
//     index.php then serves as the branded page), not a bare server error
//     and not the LinksPage. Both validators now refuse ANY leading
//     underscore, rather than naming each blocked folder individually, so
//     a folder added to .htaccess later is covered automatically.
// ============================================================================

test('manage slug: a leading underscore is rejected by both validators, even for a folder name not literally on the reserved list', function (): void
{
    $leadingUnderscoreSamples = ['_uploads', '_sql', '_schemas', '_backups', '_includes', '_functions', '_libraries', '_anything'];

    foreach ($leadingUnderscoreSamples as $sample)
    {
        assert_false(
            g2ml_linkspageManageIsValidSlug($sample),
            'The management layer must reject the leading-underscore slug "' . $sample . '" — web/Lnks.page/public_html/.htaccess 403-blocks any real folder starting with an underscore'
        );
        assert_false(
            g2ml_linkspageIsValidSlug($sample),
            'The public resolver must reject the leading-underscore slug "' . $sample . '" for the same reason'
        );
    }
});

test('manage slug: an underscore that is NOT leading is still accepted by both validators', function (): void
{
    // The bug this closes is specifically a LEADING underscore (it makes
    // the slug look like one of the 403-blocked folder names). An
    // underscore elsewhere in the slug is an ordinary, legitimate part of a
    // handle and must not be caught by this check.
    assert_true(g2ml_linkspageManageIsValidSlug('jane_doe'), 'A non-leading underscore must still be accepted by the management layer');
    assert_true(g2ml_linkspageIsValidSlug('jane_doe'), 'A non-leading underscore must still be accepted by the public resolver');
});

test('manage field validation: creating a page with a leading-underscore slug reports the specific "reserved" message', function (): void
{
    $validation = _g2ml_linkspageManageValidateFields([
        'slug'      => '_uploads',
        'pageTitle' => 'Should Not Save',
    ]);

    assert_false($validation['ok'], 'A leading-underscore slug must fail field validation');
    assert_same('That slug is reserved. Please choose a different one.', $validation['error'], 'The error message must clearly explain the slug is reserved, not just malformed — a leading underscore already satisfies the charset/length shape rule');
});

test('manage field validation: creating a page with a reserved slug reports the specific "reserved" message', function (): void
{
    $validation = _g2ml_linkspageManageValidateFields([
        'slug'      => 'admin',
        'pageTitle' => 'Should Not Save',
    ]);

    assert_false($validation['ok'], 'A reserved slug must fail field validation');
    assert_same('That slug is reserved. Please choose a different one.', $validation['error'], 'The error message must clearly explain the slug is reserved, not just malformed');
});

// ============================================================================
// 🎨 g2ml_linkspageManageValidateHexColour — parity with the public renderer
// ============================================================================

test('manage hexColour: a 6-digit hex colour is valid', function (): void
{
    assert_same('#1e88e5', g2ml_linkspageManageValidateHexColour('#1e88e5'), 'A well-formed 6-digit hex colour must be accepted');
});

test('manage hexColour: a 3-digit hex colour is valid', function (): void
{
    assert_same('#fff', g2ml_linkspageManageValidateHexColour('#fff'), 'A well-formed 3-digit hex colour must be accepted');
});

test('manage hexColour: missing the leading # is rejected', function (): void
{
    assert_false(g2ml_linkspageManageValidateHexColour('1e88e5'), 'A hex value without a leading # must be rejected');
});

test('manage hexColour: a CSS-injection payload is rejected', function (): void
{
    assert_false(g2ml_linkspageManageValidateHexColour('#fff; } body { display:none'), 'A CSS-breakout payload must be rejected outright');
});

test('manage hexColour: null is rejected', function (): void
{
    assert_false(g2ml_linkspageManageValidateHexColour(null), 'A null colour must be rejected');
});

test('manage hexColour: parity with the public renderer', function (): void
{
    $samples = ['#1e88e5', '#fff', '1e88e5', '#ZZZZZZ', '#fff; } body { display:none', null];

    foreach ($samples as $sample)
    {
        assert_same(
            g2ml_linkspageValidateHexColour($sample),
            g2ml_linkspageManageValidateHexColour($sample),
            'Hex colour validation must agree between the manage layer and the public renderer'
        );
    }
});

// ============================================================================
// 🔤 g2ml_linkspageManageValidateFontFamily — parity with the public renderer
// ============================================================================

test('manage fontFamily: a normal font stack is valid', function (): void
{
    assert_same('"Segoe UI", Roboto, sans-serif', g2ml_linkspageManageValidateFontFamily('"Segoe UI", Roboto, sans-serif'), 'A normal quoted font stack must be accepted');
});

test('manage fontFamily: a semicolon (rule-breakout attempt) is rejected', function (): void
{
    assert_false(g2ml_linkspageManageValidateFontFamily('Arial; } body { background:red'), 'A semicolon must be rejected — it could close the declaration');
});

test('manage fontFamily: a url() expression is rejected', function (): void
{
    assert_false(g2ml_linkspageManageValidateFontFamily('Arial, url(javascript:alert(1))'), 'Parentheses must be rejected — url(...) can never form');
});

test('manage fontFamily: an empty string is rejected', function (): void
{
    assert_false(g2ml_linkspageManageValidateFontFamily(''), 'An empty font family must be rejected');
});

test('manage fontFamily: over 100 characters is rejected', function (): void
{
    $tooLong = str_repeat('a', 101);
    assert_false(g2ml_linkspageManageValidateFontFamily($tooLong), 'A font family over 100 characters must be rejected');
});

test('manage fontFamily: parity with the public renderer', function (): void
{
    $samples = ['"Segoe UI", Roboto, sans-serif', 'Arial; } body { background:red', 'Arial, url(javascript:alert(1))', '', str_repeat('a', 101)];

    foreach ($samples as $sample)
    {
        assert_same(
            g2ml_linkspageValidateFontFamily($sample),
            g2ml_linkspageManageValidateFontFamily($sample),
            'Font-family validation must agree between the manage layer and the public renderer'
        );
    }
});

// ============================================================================
// 📢 g2ml_linkspageManageValidateSocialLinks — closed allowlist + URL scheme
// ============================================================================

test('manage socialLinks: an allowlisted network with a benign https URL is kept', function (): void
{
    $result = g2ml_linkspageManageValidateSocialLinks(['twitter' => 'https://twitter.com/example']);
    assert_same(['twitter' => 'https://twitter.com/example'], $result, 'A benign, allowlisted network URL must be kept as-is');
});

test('manage socialLinks: a non-allowlisted network key is silently dropped', function (): void
{
    $result = g2ml_linkspageManageValidateSocialLinks(['myspace' => 'https://myspace.com/example']);
    assert_same([], $result, 'A network key not on the allowlist must never be kept');
});

test('manage socialLinks: a javascript: URL is dropped, not saved', function (): void
{
    $result = g2ml_linkspageManageValidateSocialLinks(['twitter' => 'javascript:alert(1)']);
    assert_same([], $result, 'An unsafe scheme must be dropped rather than stored');
});

test('manage socialLinks: a blank value for an allowlisted key is dropped', function (): void
{
    $result = g2ml_linkspageManageValidateSocialLinks(['twitter' => '   ']);
    assert_same([], $result, 'A blank/whitespace-only value must be dropped');
});

test('manage socialLinks: multiple allowlisted networks are all kept independently', function (): void
{
    $result = g2ml_linkspageManageValidateSocialLinks([
        'twitter'   => 'https://twitter.com/example',
        'instagram' => 'https://instagram.com/example',
        'myspace'   => 'https://myspace.com/example',
        'facebook'  => 'javascript:alert(1)',
    ]);

    assert_same(
        ['twitter' => 'https://twitter.com/example', 'instagram' => 'https://instagram.com/example'],
        $result,
        'Only the safe, allowlisted entries must survive validation'
    );
});

test('manage socialLinks: an empty input array yields an empty result', function (): void
{
    assert_same([], g2ml_linkspageManageValidateSocialLinks([]), 'An empty submission must validate to an empty array');
});

// ============================================================================
// 🎨 g2ml_linkspageManageBuildTemplatePreviewSampleModel — C.3, #47
// ============================================================================

test('picker sample model: returns a fixed page/template/items shape', function (): void
{
    $templateRow = [
        'templateUID'  => 42,
        'templateName' => 'Sample',
        'templateHTML' => '<div>{{name}}</div>',
        'templateCSS'  => '',
    ];

    $model = g2ml_linkspageManageBuildTemplatePreviewSampleModel($templateRow);

    assert_true(isset($model['page']) && isset($model['template']) && isset($model['items']), 'The sample model must have page/template/items keys');
    assert_same('Jane Doe', $model['page']['pageTitle'], 'The sample page title must be the fixed sample value');
    assert_same(42, $model['page']['templateUID'], 'The sample model must carry the caller\'s templateUID through');
    assert_same($templateRow, $model['template'], 'The template row must be passed through unchanged');
    assert_true(count($model['items']) > 0, 'The sample model must include sample items');
});

test('picker sample model: the sample content is static — no key here is derived from anything but the template row', function (): void
{
    $templateRowOne = ['templateUID' => 1, 'templateHTML' => 'x'];
    $templateRowTwo = ['templateUID' => 2, 'templateHTML' => 'y'];

    $modelOne = g2ml_linkspageManageBuildTemplatePreviewSampleModel($templateRowOne);
    $modelTwo = g2ml_linkspageManageBuildTemplatePreviewSampleModel($templateRowTwo);

    assert_same($modelOne['page']['pageTitle'], $modelTwo['page']['pageTitle'], 'The sample page title must be identical regardless of template');
    assert_same($modelOne['page']['pageDescription'], $modelTwo['page']['pageDescription'], 'The sample bio must be identical regardless of template');
    assert_same($modelOne['items'], $modelTwo['items'], 'The sample items must be identical regardless of template');
});

// ============================================================================
// 🖼️ g2ml_linkspageManageRenderTemplateCardThumbnail — C.3, #47
// ============================================================================

test('picker thumbnail: a live-renderable template produces a sandboxed, escaped iframe srcdoc', function (): void
{
    $templateRow = [
        'templateUID'       => 1,
        'templateName'      => 'Default',
        'templateHTML'      => '<div class="lp-container"><h1>{{name}}</h1><div>{{links}}</div></div>',
        'templateCSS'       => '.lp-container { color: {{theme}}; }',
        'templateThumbnail' => null,
    ];

    $thumbnailHTML = g2ml_linkspageManageRenderTemplateCardThumbnail($templateRow);

    assert_contains('<iframe', $thumbnailHTML, 'A live-renderable template must produce an <iframe> thumbnail');
    assert_contains('srcdoc="', $thumbnailHTML, 'The thumbnail must embed the rendered document via srcdoc');
    assert_contains('sandbox=""', $thumbnailHTML, 'The thumbnail iframe must be fully sandboxed (no allow-scripts token)');
    assert_contains('aria-hidden="true"', $thumbnailHTML, 'The decorative thumbnail must be hidden from assistive tech (the card label carries the accessible name)');
    assert_contains('Jane Doe', $thumbnailHTML, 'The escaped sample name must appear inside the srcdoc attribute');
    assert_false(str_contains($thumbnailHTML, '<h1>Jane Doe</h1>'), 'The inner document must be attribute-escaped, not embedded as raw unescaped HTML');
});

test('picker thumbnail: falls back to templateThumbnail when templateHTML is blank', function (): void
{
    $templateRow = [
        'templateUID'       => 2,
        'templateName'      => 'Legacy',
        'templateHTML'      => '',
        'templateCSS'       => '',
        'templateThumbnail' => '/images/templates/legacy-thumb.png',
    ];

    $thumbnailHTML = g2ml_linkspageManageRenderTemplateCardThumbnail($templateRow);

    assert_contains('<img', $thumbnailHTML, 'A blank templateHTML with a stored thumbnail must fall back to a static <img>');
    assert_contains('/images/templates/legacy-thumb.png', $thumbnailHTML, 'The stored thumbnail path must be used');
    assert_false(str_contains($thumbnailHTML, '<iframe'), 'The static fallback must not attempt a live render');
});

test('picker thumbnail: a templateThumbnail value is HTML-escaped, never emitted raw', function (): void
{
    $templateRow = [
        'templateUID'       => 4,
        'templateName'      => 'Escaped',
        'templateHTML'      => '',
        'templateCSS'       => '',
        'templateThumbnail' => '"><script>alert(1)</script>',
    ];

    $thumbnailHTML = g2ml_linkspageManageRenderTemplateCardThumbnail($templateRow);

    assert_false(str_contains($thumbnailHTML, '<script>alert(1)</script>'), 'A raw script payload in templateThumbnail must never be emitted unescaped');
});

test('picker thumbnail: falls back to a placeholder when neither a live render nor a stored thumbnail is available', function (): void
{
    $templateRow = [
        'templateUID'       => 3,
        'templateName'      => 'Empty',
        'templateHTML'      => '',
        'templateCSS'       => '',
        'templateThumbnail' => null,
    ];

    $thumbnailHTML = g2ml_linkspageManageRenderTemplateCardThumbnail($templateRow);

    assert_contains('lp-picker-thumb-placeholder', $thumbnailHTML, 'With neither a live render nor a stored thumbnail, a placeholder must be shown');
    assert_false(str_contains($thumbnailHTML, '<iframe'), 'The placeholder fallback must not attempt a live render');
    assert_false(str_contains($thumbnailHTML, '<img'), 'The placeholder fallback must not reference a missing image');
});

// ============================================================================
// 🖼️ _g2ml_linkspageManageValidateFields — avatar address must be https
//    (#221/#273)
// ============================================================================
//
// Before #221, the public page's CSP blocked every avatar regardless of
// scheme, so this distinction did not matter. #221 allowed https: images
// through the CSP, which meant an avatar could finally be seen — but #273
// then found that this validator still silently ACCEPTED an http:// address,
// even though the help text on the create/edit forms already said "Must
// start with https://". An http:// avatar is unreliable on an https page: a
// modern browser quietly tries it over https:// instead and shows nothing
// if that server has no https, while an older browser is blocked outright
// by the CSP (it lists https: alone) — either way the server accepted an
// address with no error and no way for the creator to know whether it would
// actually show. These tests pin the fix: http:// is now refused here,
// https:// is accepted, and a blank address (avatar is optional) still is
// too.
// ============================================================================

test('manage fields: an http:// avatar address is refused, not silently accepted', function (): void
{
    $result = _g2ml_linkspageManageValidateFields([
        'slug'       => 'avatar-http-test',
        'pageTitle'  => 'Avatar HTTP Test',
        'avatarPath' => 'http://example.com/me.png',
    ]);

    assert_false($result['ok'], 'An http:// avatar address must be refused now that the CSP only allows https: images');
    assert_same('validation', $result['errorCode'], 'The rejection must be reported as a validation error');
    assert_contains('https', $result['error'], 'The error message must tell the creator https is required');
});

test('manage fields: an https:// avatar address is accepted', function (): void
{
    $result = _g2ml_linkspageManageValidateFields([
        'slug'       => 'avatar-https-test',
        'pageTitle'  => 'Avatar HTTPS Test',
        'avatarPath' => 'https://example.com/me.png',
    ]);

    assert_true($result['ok'], 'An https:// avatar address must be accepted: ' . ($result['error'] ?? ''));
    assert_same('https://example.com/me.png', $result['fields']['avatarPath'], 'The sanitised https avatar address must be kept unchanged');
});

test('manage fields: a blank avatar address is still accepted — the avatar is optional', function (): void
{
    $result = _g2ml_linkspageManageValidateFields([
        'slug'       => 'avatar-blank-test',
        'pageTitle'  => 'Avatar Blank Test',
        'avatarPath' => '',
    ]);

    assert_true($result['ok'], 'A blank avatar address must not be treated as a validation error: ' . ($result['error'] ?? ''));
    assert_same(null, $result['fields']['avatarPath'], 'A blank avatar address must be stored as null, not an empty string');
});
