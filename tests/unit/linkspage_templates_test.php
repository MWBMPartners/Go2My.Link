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
 * 🧪 Unit tests — the five LinksPage templates and the lnks.page pages (#287)
 * ============================================================================
 *
 * Reads the real template text from web/_sql/seeds/004_linkspage_templates.sql,
 * renders each template through the real renderer, and checks the things that
 * went wrong in the accessibility audit:
 *
 *   - exactly one <main> and one <h1> on every template;
 *   - the default avatar is square, decorative (alt="") and not the site logo;
 *   - link titles, descriptions and social links have layout rules;
 *   - long words wrap, and the Professional template has no minimum width
 *     that is wider than a 320 px phone;
 *   - every text and background colour pair reaches WCAG 2.1 AA (4.5:1);
 *   - the lnks.page home, 404, 403 and 500 pages have no white-on-#1E88E5
 *     button.
 *
 * The colour checks read the colours out of the CSS that is actually served,
 * so changing a colour in a template to one that is too faint fails here.
 *
 * Not checked here: how a browser lays the page out (for example that nothing
 * scrolls sideways at 320 px). That needs a real browser, which this test
 * does not use.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.0.0 — Launch Hardening (#287)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/security.php';
require_once dirname(__DIR__, 2) . '/web/Lnks.page/_functions/linkspage_resolver.php';
require_once dirname(__DIR__, 2) . '/web/Lnks.page/_functions/linkspage_renderer.php';

// ============================================================================
// 🧰 Helpers (prefixed lpTpl so they cannot clash with another test file)
// ============================================================================

/**
 * Read the five system templates out of the seed file.
 *
 * The seed's HTML and CSS must not contain a single quote or a backslash,
 * because the pattern below ends each string at the next single quote.
 *
 * @return array<string, array{html: string, css: string}>  Keyed by template slug.
 */
function lpTplLoadTemplates(): array
{
    $seedPath = dirname(__DIR__, 2) . '/web/_sql/seeds/004_linkspage_templates.sql';
    $seedText = file_get_contents($seedPath);

    if ($seedText === false)
    {
        return [];
    }

    $pattern = "/\(\s*'([a-z]+)',\s*'([^']*)',\s*'([^']*)',\s*'([^']*)',\s*'([^']*)',\s*1,\s*1,\s*\d\s*\)/s";

    if (preg_match_all($pattern, $seedText, $matches, PREG_SET_ORDER) === false)
    {
        return [];
    }

    $templates = [];

    foreach ($matches as $match)
    {
        $templates[$match[1]] = ['html' => $match[4], 'css' => $match[5]];
    }

    return $templates;
}

/**
 * A page model for rendering one template.
 *
 * @param  array{html: string, css: string} $template
 * @param  array<string, mixed>             $pageOverrides  Changes to the page row.
 * @param  array<int, array<string, mixed>>|null $items     Replaces the default items.
 * @return array
 */
function lpTplModel(array $template, array $pageOverrides = [], ?array $items = null): array
{
    $page = [
        'pageTitle'        => 'Jane Doe',
        'pageDescription'  => 'Photographer and digital creator.',
        'avatarPath'       => null,
        'themeColour'      => '#1E88E5',
        'backgroundColour' => '#FFFFFF',
        'fontFamily'       => null,
        'showSocialIcons'  => 1,
        'socialLinks'      => json_encode(['github' => 'https://example.com/g', 'twitter' => 'https://example.com/t']),
    ];

    foreach ($pageOverrides as $key => $value)
    {
        $page[$key] = $value;
    }

    if ($items === null)
    {
        $items = [
            ['itemTitle' => 'My portfolio', 'itemURL' => 'https://example.com/a', 'itemDescription' => 'Photos from the last ten years', 'itemIcon' => null, 'faviconCacheURL' => null, 'requiresAgeGate' => 0],
            ['itemTitle' => 'Short one', 'itemURL' => 'https://example.com/b', 'itemDescription' => null, 'itemIcon' => null, 'faviconCacheURL' => null, 'requiresAgeGate' => 0],
        ];
    }

    return [
        'page'     => $page,
        'template' => ['templateHTML' => $template['html'], 'templateCSS' => $template['css']],
        'items'    => $items,
    ];
}

/**
 * The text of the page's <style> block.
 *
 * @param  string $html  A rendered page.
 * @return string
 */
function lpTplStyleText(string $html): string
{
    $start = strpos($html, '<style>');
    $end   = strpos($html, '</style>');

    if ($start === false || $end === false)
    {
        return '';
    }

    return substr($html, $start + 7, $end - ($start + 7));
}

/**
 * Remove CSS comments, then @media blocks (one level of rules inside), leaving
 * the plain rules.
 *
 * @param  string $css
 * @return string
 */
function lpTplWithoutMedia(string $css): string
{
    $withoutComments = preg_replace('~/\*.*?\*/~s', '', $css);

    if ($withoutComments === null)
    {
        return $css;
    }

    $stripped = preg_replace('/@media[^{]*\{(?:[^{}]*\{[^{}]*\})*\s*\}/', '', $withoutComments);

    if ($stripped === null)
    {
        return $withoutComments;
    }

    return $stripped;
}

/**
 * Split the inside of a CSS rule into property => value.
 *
 * @param  string $ruleBody  The text between the braces.
 * @return array<string, string>
 */
function lpTplDeclarations(string $ruleBody): array
{
    $declarations = [];

    foreach (explode(';', $ruleBody) as $declaration)
    {
        $parts = explode(':', $declaration, 2);

        if (count($parts) !== 2)
        {
            continue;
        }

        $declarations[strtolower(trim($parts[0]))] = trim($parts[1]);
    }

    return $declarations;
}

/**
 * The declarations of every plain rule whose selector list contains the given
 * selector, merged in order (a later declaration replaces an earlier one).
 *
 * @param  string $css       CSS text (an @media block, if any, is ignored).
 * @param  string $selector  One selector, exactly as written, e.g. '.lp-bold .lp-link-item'.
 * @return array<string, string>  Property => value.
 */
function lpTplRule(string $css, string $selector): array
{
    $declarations = [];

    if (preg_match_all('/([^{}@]+)\{([^{}]*)\}/', lpTplWithoutMedia($css), $rules, PREG_SET_ORDER) === false)
    {
        return $declarations;
    }

    foreach ($rules as $rule)
    {
        $selectors = array_map('trim', explode(',', $rule[1]));

        if (!in_array($selector, $selectors, true))
        {
            continue;
        }

        foreach (lpTplDeclarations($rule[2]) as $property => $value)
        {
            $declarations[$property] = $value;
        }
    }

    return $declarations;
}

/**
 * Turn a CSS colour (#rgb, #rrggbb, rgb() or rgba()) into red, green, blue,
 * blending any see-through colour onto the given backdrop.
 *
 * @param  string                       $value
 * @param  array{0: int, 1: int, 2: int} $backdrop  What is behind a see-through colour.
 * @return array{0: int, 1: int, 2: int}|null  Null when the value is not a colour this helper reads.
 */
function lpTplColour(string $value, array $backdrop): ?array
{
    $value = strtolower(trim($value));

    if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $value) === 1)
    {
        return g2ml_linkspageHexToRgb($value);
    }

    if (preg_match('/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*([0-9.]+)\s*)?\)$/', $value, $parts) === 1)
    {
        $opacity = 1.0;

        if (isset($parts[4]) && $parts[4] !== '')
        {
            $opacity = (float) $parts[4];
        }

        $blended = [];

        foreach ([1, 2, 3] as $position)
        {
            $blended[] = (int) round(((int) $parts[$position] * $opacity) + ($backdrop[$position - 1] * (1 - $opacity)));
        }

        return [$blended[0], $blended[1], $blended[2]];
    }

    return null;
}

/**
 * The colour pairs to check for each template: [text rule, background].
 * The text colour is the `color` of the first rule; the background is a
 * literal colour (starts with '#') or the `background` of the named rule.
 *
 * @return array<string, array<int, array{0: string, 1: string}>>
 */
function lpTplColourPairs(): array
{
    return [
        'default' => [
            ['.lp-default .lp-bio', '#fff'],
            ['.lp-default .lp-link-item', '.lp-default .lp-link-item'],
            ['.lp-default .lp-link-desc', '.lp-default .lp-link-item'],
            ['.lp-default .lp-social-icon', '#fff'],
            ['.lp-default .lp-branding', '#fff'],
        ],
        'modern' => [
            ['.lp-modern', '.lp-modern .lp-header'],
            ['.lp-modern .lp-link-item', '.lp-modern .lp-link-item'],
            ['.lp-modern .lp-link-item', '.lp-modern .lp-link-item:hover'],
            ['.lp-modern .lp-social-icon', '.lp-modern .lp-social-icon'],
            ['.lp-modern .lp-branding', '.lp-modern .lp-branding'],
        ],
        'minimal' => [
            ['.lp-minimal .lp-bio', '#fff'],
            ['.lp-minimal .lp-link-item', '#fff'],
            ['.lp-minimal .lp-link-desc', '#fff'],
            ['.lp-minimal .lp-social-icon', '#fff'],
            ['.lp-minimal .lp-branding', '#fff'],
        ],
        'bold' => [
            ['.lp-bold .lp-bio', '#fff'],
            ['.lp-bold .lp-link-item', '.lp-bold .lp-link-item'],
            ['.lp-bold .lp-social-icon', '#fff'],
            ['.lp-bold .lp-branding', '#fff'],
        ],
        'professional' => [
            ['.lp-professional .lp-bio', '.lp-professional .lp-sidebar'],
            ['.lp-professional .lp-social-icon', '.lp-professional .lp-sidebar'],
            ['.lp-professional .lp-link-item', '.lp-professional .lp-link-item'],
            ['.lp-professional .lp-link-desc', '.lp-professional .lp-link-item'],
            ['.lp-professional .lp-branding', '#fff'],
        ],
    ];
}

/**
 * Work out the contrast ratio for every colour pair of every template, from
 * the CSS the renderer really prints (with the default theme colour).
 *
 * A see-through background is measured over pure white, which is the worst
 * case: the page is never lighter than that, and a darker page only helps
 * white text.
 *
 * @return array<int, array{template: string, text: string, background: string, textColour: string, backgroundColour: string, ratio: float}>
 */
function lpTplContrastTable(): array
{
    $templates = lpTplLoadTemplates();
    $white     = [255, 255, 255];
    $rows      = [];

    foreach (lpTplColourPairs() as $slug => $pairs)
    {
        if (!isset($templates[$slug]))
        {
            continue;
        }

        $css = lpTplStyleText(g2ml_renderLinksPage(lpTplModel($templates[$slug])));

        foreach ($pairs as $pair)
        {
            $textRule  = lpTplRule($css, $pair[0]);
            $textValue = '';

            if (isset($textRule['color']))
            {
                $textValue = $textRule['color'];
            }

            $backgroundValue = $pair[1];

            if (!str_starts_with($pair[1], '#'))
            {
                $backgroundRule  = lpTplRule($css, $pair[1]);
                $backgroundValue = '';

                if (isset($backgroundRule['background']))
                {
                    $backgroundValue = $backgroundRule['background'];
                }
            }

            $textRgb       = lpTplColour($textValue, $white);
            $backgroundRgb = lpTplColour($backgroundValue, $white);
            $ratio         = 0.0;

            if ($textRgb !== null && $backgroundRgb !== null)
            {
                $textRgb = lpTplColour($textValue, $backgroundRgb);
                $ratio   = g2ml_linkspageContrastRatio($textRgb, $backgroundRgb);
            }

            $rows[] = [
                'template'         => $slug,
                'text'             => $pair[0],
                'background'       => $pair[1],
                'textColour'       => $textValue,
                'backgroundColour' => $backgroundValue,
                'ratio'            => $ratio,
            ];
        }
    }

    return $rows;
}

// ============================================================================
// 🧱 Structure: the seed itself
// ============================================================================

test('templates: the seed holds exactly the five system templates', function (): void
{
    $templates = lpTplLoadTemplates();
    $slugs     = array_keys($templates);
    sort($slugs);

    assert_same(['bold', 'default', 'minimal', 'modern', 'professional'], $slugs, 'The seed must hold the five known templates, and the test parser must find all of them');
});

test('templates: re-running the seed refreshes the stored HTML and CSS', function (): void
{
    $seedText = (string) file_get_contents(dirname(__DIR__, 2) . '/web/_sql/seeds/004_linkspage_templates.sql');
    $tail     = substr($seedText, (int) strrpos($seedText, 'ON DUPLICATE KEY UPDATE'));

    assert_contains('`templateHTML` = VALUES(`templateHTML`)', $tail, 'Without this, a changed template never reaches a database that already has the rows');
    assert_contains('`templateCSS`  = VALUES(`templateCSS`)', $tail, 'Without this, a changed template never reaches a database that already has the rows');
});

test('templates: no template names the page as the avatar alt text any more', function (): void
{
    foreach (lpTplLoadTemplates() as $slug => $template)
    {
        assert_false(str_contains($template['html'], 'alt="{{name}}"'), $slug . ': the avatar alt text repeats the heading beside it');
        assert_contains('alt="{{avatarAlt}}"', $template['html'], $slug . ': the avatar must take its alt text from {{avatarAlt}}');
    }
});

// ============================================================================
// 🏛️ Landmarks and headings (audit L1)
// ============================================================================

test('templates: every template renders exactly one <main> and one <h1>', function (): void
{
    foreach (lpTplLoadTemplates() as $slug => $template)
    {
        $html = g2ml_renderLinksPage(lpTplModel($template));

        assert_same(1, substr_count($html, '<main'), $slug . ': there must be exactly one <main> start tag');
        assert_same(1, substr_count($html, '</main>'), $slug . ': there must be exactly one </main> end tag');
        assert_same(1, substr_count($html, '<h1'), $slug . ': there must be exactly one <h1>');
    }
});

test('templates: the single <main> is still there with no links, no social links and an empty name', function (): void
{
    $overrides = ['pageTitle' => '', 'pageDescription' => null, 'showSocialIcons' => 0, 'socialLinks' => null];

    foreach (lpTplLoadTemplates() as $slug => $template)
    {
        $html = g2ml_renderLinksPage(lpTplModel($template, $overrides, []));

        assert_same(1, substr_count($html, '<main'), $slug . ': an empty page must still have one <main>');
    }
});

// ============================================================================
// 🖼️ Default avatar (audit L2, L3)
// ============================================================================

test('templates: with no avatar every template shows a square, decorative, inline picture, not the logo', function (): void
{
    foreach (lpTplLoadTemplates() as $slug => $template)
    {
        $html = g2ml_renderLinksPage(lpTplModel($template));

        assert_false(str_contains($html, '/img/logo.png'), $slug . ': the white site logo must not be used as the default avatar');
        assert_same(1, preg_match('/<img class="lp-avatar" src="data:image\/svg\+xml;base64,([A-Za-z0-9+\/=]+)" alt="">/', $html, $found), $slug . ': the default avatar must be an inline picture with an empty alt text');

        $svg = (string) base64_decode($found[1], true);

        assert_same(1, preg_match('/viewBox="0 0 (\d+) (\d+)"/', $svg, $box), $slug . ': the picture must declare its size');
        assert_same($box[1], $box[2], $slug . ': the default avatar picture must be square');
        assert_contains('>J<', $svg, $slug . ': the picture shows the first letter of "Jane Doe"');
    }
});

test('templates: a real avatar keeps a short alt text that is not the page name', function (): void
{
    foreach (lpTplLoadTemplates() as $slug => $template)
    {
        $html = g2ml_renderLinksPage(lpTplModel($template, ['avatarPath' => 'https://example.com/me.png']));

        assert_contains('src="https://example.com/me.png" alt="Profile picture"', $html, $slug . ': a real avatar needs the "Profile picture" alt text');
        assert_false(str_contains($html, 'alt="Jane Doe"'), $slug . ': the alt text must not repeat the heading');
    }
});

// ============================================================================
// 📐 Layout rules (audit L4, L5, L8, L9)
// ============================================================================

test('templates: every template gets rules for link titles, descriptions, social links and long words', function (): void
{
    foreach (lpTplLoadTemplates() as $slug => $template)
    {
        $css = lpTplStyleText(g2ml_renderLinksPage(lpTplModel($template)));

        $container = lpTplRule($css, '.lp-container');
        $title     = lpTplRule($css, '.lp-link-title');
        $desc      = lpTplRule($css, '.lp-link-desc');
        $social    = lpTplRule($css, '.lp-social');
        $socialOne = lpTplRule($css, '.lp-social-icon');

        assert_same('anywhere', $container['overflow-wrap'] ?? '', $slug . ': long words must be allowed to break anywhere');
        assert_same('block', $title['display'] ?? '', $slug . ': the link title must be its own line');
        assert_same('block', $desc['display'] ?? '', $slug . ': the link description must be its own line');
        assert_same('flex', $social['display'] ?? '', $slug . ': social links must sit in a row');
        assert_same('wrap', $social['flex-wrap'] ?? '', $slug . ': the social row must wrap on a narrow screen');
        assert_same('inline-block', $socialOne['display'] ?? '', $slug . ': each social link needs room around it');
    }
});

test('templates: the layout rules come before the template rules, so a template can override them', function (): void
{
    foreach (lpTplLoadTemplates() as $slug => $template)
    {
        $css = lpTplStyleText(g2ml_renderLinksPage(lpTplModel($template)));

        $basePosition     = strpos($css, '.lp-container { overflow-wrap: anywhere; }');
        $templatePosition = strpos($css, '.lp-' . $slug . ' {');

        assert_true($basePosition !== false && $templatePosition !== false, $slug . ': both the shared rules and the template rules must be present');
        assert_true($templatePosition > $basePosition, $slug . ': the shared rules must be printed first');
    }
});

test('templates: no template sets a display on the link box that would undo the icon-and-text layout', function (): void
{
    foreach (lpTplLoadTemplates() as $slug => $template)
    {
        $rule = lpTplRule($template['css'], '.lp-' . $slug . ' .lp-link-item');

        assert_false(isset($rule['display']), $slug . ': the link box layout comes from the shared rules');
    }
});

test('professional: the text column has no minimum width on a screen up to 640 px wide, which covers a 320 px phone', function (): void
{
    $template = lpTplLoadTemplates()['professional'];

    assert_same(1, preg_match('/@media \(max-width: 640px\) \{(.*)\}\s*$/s', $template['css'], $media), 'The narrow-screen block must exist and end the CSS');

    $narrow = lpTplRule($media[1], '.lp-professional .lp-main');
    $wide   = lpTplRule($template['css'], '.lp-professional .lp-main');

    assert_same('0', $narrow['min-width'] ?? '', 'On a narrow screen the text column must be allowed to shrink to nothing');
    assert_same('300px', $wide['min-width'] ?? '', 'The 300px minimum is only for wide screens, where it makes the columns wrap');
});

// ============================================================================
// 🎨 Modern template (audit L6, L11)
// ============================================================================

test('modern: the body margin is removed so no white frame shows around the gradient', function (): void
{
    $template = lpTplLoadTemplates()['modern'];
    $css      = lpTplStyleText(g2ml_renderLinksPage(lpTplModel($template)));
    $body     = lpTplRule($css, 'body');
    $page     = lpTplRule($css, '.lp-modern');

    assert_same('0', $body['margin'] ?? '', 'The browser margin around the page must be removed');
    assert_same('border-box', $page['box-sizing'] ?? '', 'The padding must not push the full-height page past one screen');
});

test('modern: the gradient is on the one <main>, and the default link icon takes the link text colour', function (): void
{
    $template = lpTplLoadTemplates()['modern'];
    $html     = g2ml_renderLinksPage(lpTplModel($template));

    assert_contains('<main class="lp-container lp-modern" style="background: linear-gradient(135deg, #FFFFFF 0%, #1E88E5 100%);">', $html, 'The gradient must be on the <main>');
    assert_contains('<svg class="lp-link-icon"', $html, 'The default link icon must be inline, so it can use the text colour');
    assert_contains('stroke="currentColor"', $html, 'The default link icon must be drawn in the text colour');
    assert_false(str_contains($html, '<img class="lp-link-icon"'), 'The old picture-based link icon (always drawn black) must be gone');
});

// ============================================================================
// 🌗 Contrast (audit finding 7 and L6, L11)
// ============================================================================

test('contrast: every text and background pair in every template reaches 4.5:1', function (): void
{
    $rows = lpTplContrastTable();

    assert_same(24, count($rows), 'All 24 listed colour pairs must be measured');

    foreach ($rows as $row)
    {
        assert_true($row['textColour'] !== '' && $row['backgroundColour'] !== '', $row['template'] . ': could not read the colours for ' . $row['text'] . ' on ' . $row['background']);
        assert_true($row['ratio'] >= 4.5, $row['template'] . ': ' . $row['text'] . ' (' . $row['textColour'] . ') on ' . $row['background'] . ' (' . $row['backgroundColour'] . ') is ' . round($row['ratio'], 2) . ':1, below 4.5:1');
    }
});

test('contrast: the Minimal bio is no longer #888 (3.54:1)', function (): void
{
    $css = lpTplStyleText(g2ml_renderLinksPage(lpTplModel(lpTplLoadTemplates()['minimal'])));
    $bio = lpTplRule($css, '.lp-minimal .lp-bio');

    assert_same('#666', $bio['color'] ?? '', 'The Minimal bio must use the darker #666');
});

test('contrast: Bold text on the default theme colour is black, not white', function (): void
{
    $css  = lpTplStyleText(g2ml_renderLinksPage(lpTplModel(lpTplLoadTemplates()['bold'])));
    $rule = lpTplRule($css, '.lp-bold .lp-link-item');

    assert_same('#1E88E5', $rule['background'] ?? '', 'The button still shows the owner\'s theme colour');
    assert_same('#000000', $rule['color'] ?? '', 'White on #1E88E5 is only 3.68:1; black is 5.71:1');
});

test('contrast: no rule in any template puts faint text on #1E88E5', function (): void
{
    foreach (lpTplLoadTemplates() as $slug => $template)
    {
        $css = lpTplStyleText(g2ml_renderLinksPage(lpTplModel($template)));

        preg_match_all('/([^{}@]+)\{([^{}]*)\}/', lpTplWithoutMedia($css), $rules, PREG_SET_ORDER);

        foreach ($rules as $rule)
        {
            $declarations = lpTplDeclarations($rule[2]);

            if (strtolower($declarations['background'] ?? '') !== '#1e88e5')
            {
                continue;
            }

            $textRgb = lpTplColour($declarations['color'] ?? '#ffffff', [255, 255, 255]);

            assert_true($textRgb !== null && g2ml_linkspageContrastRatio($textRgb, [30, 136, 229]) >= 4.5, $slug . ': ' . trim($rule[1]) . ' has text that is too faint on #1E88E5');
        }
    }
});

test('contrast: Bold button text stays at 4.5:1 or better for a range of owner theme colours', function (): void
{
    $template = lpTplLoadTemplates()['bold'];
    $themes   = ['#1E88E5', '#FFFFFF', '#000000', '#FFEB3B', '#0D47A1', '#FF0000', '#00FF00', '#0000FF', '#777777', '#767676', '#fff', '#000', '#f80', '#9C27B0', '#4CAF50', '#FF9800'];

    foreach ($themes as $theme)
    {
        $css   = lpTplStyleText(g2ml_renderLinksPage(lpTplModel($template, ['themeColour' => $theme])));
        $rule  = lpTplRule($css, '.lp-bold .lp-link-item');
        $text  = lpTplColour($rule['color'] ?? '', [255, 255, 255]);
        $fill  = lpTplColour($rule['background'] ?? '', [255, 255, 255]);

        assert_true($text !== null && $fill !== null, $theme . ': could not read the Bold button colours');
        assert_true(g2ml_linkspageContrastRatio($text, $fill) >= 4.5, $theme . ': Bold text ' . ($rule['color'] ?? '') . ' on ' . ($rule['background'] ?? '') . ' is below 4.5:1');
    }
});

// ============================================================================
// 🏠 The lnks.page home, 404, 403 and 500 pages
// ============================================================================

/**
 * The plain (not dark-mode) CSS inside a lnks.page PHP page's <style> block.
 *
 * @param  string $fileName  A file in web/Lnks.page/public_html.
 * @return string
 */
function lpTplPageCss(string $fileName): string
{
    $text = (string) file_get_contents(dirname(__DIR__, 2) . '/web/Lnks.page/public_html/' . $fileName);
    $from = strpos($text, '<style>');
    $to   = strpos($text, '</style>');

    if ($from === false || $to === false)
    {
        return '';
    }

    return lpTplWithoutMedia(substr($text, $from + 7, $to - ($from + 7)));
}

test('lnks.page pages: no page uses #1E88E5 as a colour any more', function (): void
{
    foreach (['index.php', '403.php', '404.php', '500.php'] as $fileName)
    {
        $text      = (string) file_get_contents(dirname(__DIR__, 2) . '/web/Lnks.page/public_html/' . $fileName);
        $noComment = (string) preg_replace('~/\*.*?\*/~s', '', $text);

        assert_false(stripos($noComment, '#1E88E5') !== false, $fileName . ': white on #1E88E5 is only 3.68:1');
    }
});

test('lnks.page pages: buttons, subtitle and message reach WCAG AA, and the big error number reaches 3:1', function (): void
{
    $checks = [
        // file, text rule, background rule or colour, minimum ratio
        ['index.php', '.lp-home-cta', '.lp-home-cta', 4.5],
        ['index.php', '.lp-home-cta', '.lp-home-cta:hover', 4.5],
        ['index.php', '.lp-home-subtitle', 'body', 4.5],
        ['index.php', '.lp-home-message', 'body', 4.5],
        ['404.php', '.lp-404-cta', '.lp-404-cta', 4.5],
        ['404.php', '.lp-404-cta', '.lp-404-cta:hover', 4.5],
        ['404.php', '.lp-404-message', 'body', 4.5],
        ['404.php', '.lp-404-code', 'body', 3.0],
        ['403.php', '.lp-error-cta', '.lp-error-cta', 4.5],
        ['403.php', '.lp-error-cta', '.lp-error-cta:hover', 4.5],
        ['403.php', '.lp-error-message', 'body', 4.5],
        ['403.php', '.lp-error-code', 'body', 3.0],
        ['500.php', '.lp-error-cta', '.lp-error-cta', 4.5],
        ['500.php', '.lp-error-cta', '.lp-error-cta:hover', 4.5],
        ['500.php', '.lp-error-message', 'body', 4.5],
        ['500.php', '.lp-error-code', 'body', 3.0],
    ];

    foreach ($checks as $check)
    {
        $css            = lpTplPageCss($check[0]);
        $textRule       = lpTplRule($css, $check[1]);
        $backgroundRule = lpTplRule($css, $check[2]);

        $textRgb       = lpTplColour($textRule['color'] ?? '', [255, 255, 255]);
        $backgroundRgb = lpTplColour($backgroundRule['background'] ?? '', [255, 255, 255]);

        assert_true($textRgb !== null && $backgroundRgb !== null, $check[0] . ': could not read the colours for ' . $check[1] . ' on ' . $check[2]);
        assert_true(g2ml_linkspageContrastRatio($textRgb, $backgroundRgb) >= $check[3], $check[0] . ': ' . $check[1] . ' on ' . $check[2] . ' is below ' . $check[3] . ':1');
    }
});

// ============================================================================
// 🌍 The new translation row
// ============================================================================

test('seed 073: adds the avatar alt text as an INSERT IGNORE, en-GB, verified row', function (): void
{
    $seedText = (string) file_get_contents(dirname(__DIR__, 2) . '/web/_sql/seeds/073_linkspage_layout_translations.sql');

    assert_contains('INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)', $seedText, 'The seed must be INSERT IGNORE so it is safe to re-run');
    assert_contains("('en-GB', 'linkspage.avatar_alt', 'Profile picture',", $seedText, 'The row the renderer asks for must be seeded');
    assert_same(1, preg_match("/'Profile picture', '[^']+', 1\);\s*$/", $seedText), 'The row must be marked verified');
});
