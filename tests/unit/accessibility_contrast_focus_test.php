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
 * 🧪 Check — contrast, focus rings, the cookie banner and small fixes (#287)
 * ============================================================================
 *
 * The sweep of 2026-10-05 found:
 *   - the main navigation bar was light blue in both colour modes, so its
 *     white text measured only 2.4 to 2.65 to 1 (the minimum is 4.5 to 1);
 *   - in dark mode, filled and outlined buttons were 2.65 to 3.4 to 1;
 *   - the clear 3px keyboard focus ring only reached plain links, so buttons,
 *     menu links, text boxes and tick boxes kept Bootstrap's faint glow;
 *   - the cookie banner came after the footer in the tab order, covered a
 *     quarter of a phone screen, put a level 2 heading after the footer's
 *     level 6 headings and had a link called only "Learn more";
 *   - some alert boxes had no role, copy buttons kept their old name after
 *     the words on them changed, two pages used the same element ids, and
 *     links that open a new tab did not say so.
 *
 * This check reads the style sheet and the page files and fails if any of
 * that comes back:
 *   - contrast ratios are worked out from the colours actually written in
 *     style.css (not copied into this file), for every pair of text colour
 *     and background that the button and navigation rules produce;
 *   - one rule must give each kind of control the 3px focus ring, and
 *     nothing may switch the ring off again;
 *   - the cookie banner is printed by header.php, is a labelled region with
 *     no heading of its own, has a link whose name says what it opens, and
 *     every element cookie-consent.js looks for is still there;
 *   - every alert box has a role, every link that opens a new tab says so,
 *     copy buttons rename themselves, and the consent page reuses no id;
 *   - every new piece of wording is in seed 072 with the same English as the
 *     fallback written at the place that uses it.
 *
 * WHAT THIS CHECK CANNOT DO
 *
 * It reads files and runs the cookie banner in a separate process with
 * stand-in functions. It does not draw a page, so it cannot see how a rule
 * wins over Bootstrap's own, how wide anything wraps, or whether a ring is
 * really visible on screen. Those were measured in a real browser when the
 * change was made, but a browser is not part of this check. The contrast
 * check also uses Bootstrap's own page colours (#ffffff, #f8f9fa, #212529,
 * #2b3035), which are written into this file because Bootstrap is not read.
 * The banner process needs PHP's shell_exec(), which some hosts switch off.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.2.0 — Accessibility (#287)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_includes/accessibility.php';

// ============================================================================
// 🧰 Helpers
// ============================================================================

/**
 * Read a file from the repository as text.
 *
 * @param  string $relative  Path from the repository root.
 * @return string
 */
function _g2mlA11yTestRead(string $relative): string
{
    $path = dirname(__DIR__, 2) . '/' . $relative;

    assert_true(is_file($path), 'Expected file not found: ' . $relative);

    $text = file_get_contents($path);

    assert_true($text !== false, 'Could not read the file: ' . $relative);

    return $text;
}

/**
 * Collapse every run of spaces and line breaks to one space, so a check does
 * not depend on how the style sheet lines its values up.
 *
 * @param  string $text
 * @return string
 */
function _g2mlA11yTestFlat(string $text): string
{
    return trim((string) preg_replace('~\s+~', ' ', $text));
}

/**
 * The main site's style sheet with its comments taken out, so a word that
 * only appears in a comment cannot make a check pass.
 *
 * @return string
 */
function _g2mlA11yTestCss(): string
{
    $css = preg_replace('~/\*.*?\*/~s', '', _g2mlA11yTestRead('web/Go2My.Link/public_html/css/style.css'));

    assert_true(is_string($css), 'The style sheet could not be read');

    return $css;
}

/**
 * Every rule in the style sheet as [selector list, body] pairs. A rule inside
 * an @media block is included on its own.
 *
 * @param  string $css  Style sheet text with comments already removed.
 * @return array<int, array{0: array<int, string>, 1: string}>
 */
function _g2mlA11yTestRules(string $css): array
{
    $matches = array();
    preg_match_all('~([^{}]+)\{([^{}]*)\}~s', $css, $matches, PREG_SET_ORDER);

    $rules = array();

    foreach ($matches as $match)
    {
        $selectors = array();

        foreach (explode(',', $match[1]) as $selector)
        {
            $selectors[] = trim($selector);
        }

        $rules[] = array($selectors, $match[2]);
    }

    return $rules;
}

/**
 * The body of the first rule whose whole selector list is exactly this one
 * selector.
 *
 * @param  string $css       Style sheet text with comments already removed.
 * @param  string $selector  For example ".btn.btn-primary".
 * @return string
 */
function _g2mlA11yTestRuleBody(string $css, string $selector): string
{
    foreach (_g2mlA11yTestRules($css) as $rule)
    {
        if (count($rule[0]) === 1 && $rule[0][0] === $selector)
        {
            return $rule[1];
        }
    }

    assert_true(false, 'The style sheet has no rule for ' . $selector);

    return '';
}

/**
 * The inside of the light-mode or dark-mode colour block.
 *
 * @param  string $css   Style sheet text with comments already removed.
 * @param  string $mode  "light" or "dark".
 * @return string
 */
function _g2mlA11yTestColourBlock(string $css, string $mode): string
{
    if ($mode === 'light')
    {
        $found = preg_match('~:root,\s*\[data-bs-theme="light"\]\s*\{(.*?)\n\}~s', $css, $matches);
    }
    else
    {
        $found = preg_match('~\n\[data-bs-theme="dark"\]\s*\{(.*?)\n\}~s', $css, $matches);
    }

    assert_same(1, $found, 'The ' . $mode . '-mode colour block was not found');

    return $matches[1];
}

/**
 * The value of a colour variable in one block (the first declaration, which
 * is the plain colour; the hover and pressed ones are checked separately).
 *
 * @param  string $block  Text of one colour block.
 * @param  string $name   For example "--g2ml-on-brand".
 * @return string
 */
function _g2mlA11yTestVariable(string $block, string $name): string
{
    $found = preg_match('~' . preg_quote($name, '~') . ':\s*([^;]+);~', $block, $matches);

    assert_same(1, $found, 'The variable ' . $name . ' is not defined in that block');

    return trim($matches[1]);
}

/**
 * Turn "#rrggbb" or "rgba(r, g, b, a)" into [r, g, b, a].
 *
 * @param  string $value
 * @return array<int, float>
 */
function _g2mlA11yTestColour(string $value): array
{
    $value = trim($value);

    if (preg_match('~^#([0-9a-fA-F]{6})$~', $value, $matches) === 1)
    {
        return array(
            (float) hexdec(substr($matches[1], 0, 2)),
            (float) hexdec(substr($matches[1], 2, 2)),
            (float) hexdec(substr($matches[1], 4, 2)),
            1.0,
        );
    }

    if (preg_match('~^rgba\(\s*(\d+),\s*(\d+),\s*(\d+),\s*([0-9.]+)\s*\)$~', $value, $matches) === 1)
    {
        return array((float) $matches[1], (float) $matches[2], (float) $matches[3], (float) $matches[4]);
    }

    assert_true(false, 'Cannot read the colour: ' . $value);

    return array(0.0, 0.0, 0.0, 1.0);
}

/**
 * Lay a colour (which may be see-through) over a solid one.
 *
 * @param  array<int, float> $front
 * @param  array<int, float> $back
 * @return array<int, float>
 */
function _g2mlA11yTestLayer(array $front, array $back): array
{
    $alpha = $front[3];

    return array(
        ($front[0] * $alpha) + ($back[0] * (1 - $alpha)),
        ($front[1] * $alpha) + ($back[1] * (1 - $alpha)),
        ($front[2] * $alpha) + ($back[2] * (1 - $alpha)),
        1.0,
    );
}

/**
 * Mix two colours the way color-mix(in srgb, A pct%, B) does, rounding each
 * channel to a whole number as a browser does when it paints.
 *
 * @param  array<int, float> $first
 * @param  array<int, float> $second
 * @param  float             $percent  How much of the first colour (0 to 100).
 * @return array<int, float>
 */
function _g2mlA11yTestMix(array $first, array $second, float $percent): array
{
    $share = $percent / 100;

    return array(
        (float) round(($first[0] * $share) + ($second[0] * (1 - $share))),
        (float) round(($first[1] * $share) + ($second[1] * (1 - $share))),
        (float) round(($first[2] * $share) + ($second[2] * (1 - $share))),
        1.0,
    );
}

/**
 * WCAG relative luminance of a solid colour.
 *
 * @param  array<int, float> $colour
 * @return float
 */
function _g2mlA11yTestLuminance(array $colour): float
{
    $parts = array();

    for ($index = 0; $index < 3; $index++)
    {
        $channel = $colour[$index] / 255;

        if ($channel <= 0.03928)
        {
            $parts[$index] = $channel / 12.92;
        }
        else
        {
            $parts[$index] = pow(($channel + 0.055) / 1.055, 2.4);
        }
    }

    return (0.2126 * $parts[0]) + (0.7152 * $parts[1]) + (0.0722 * $parts[2]);
}

/**
 * WCAG contrast ratio of a colour (laid over the background first if it is
 * see-through) against a solid background.
 *
 * @param  array<int, float> $foreground
 * @param  array<int, float> $background
 * @return float
 */
function _g2mlA11yTestRatio(array $foreground, array $background): float
{
    $solid = _g2mlA11yTestLayer($foreground, $background);
    $first = _g2mlA11yTestLuminance($solid);
    $other = _g2mlA11yTestLuminance($background);

    if ($first < $other)
    {
        $swap  = $first;
        $first = $other;
        $other = $swap;
    }

    return ($first + 0.05) / ($other + 0.05);
}

/**
 * Fail unless a pair of colours reaches the minimum ratio.
 *
 * @param  string            $label       Plain description of the pair.
 * @param  array<int, float> $foreground
 * @param  array<int, float> $background
 * @param  float             $minimum     4.5 for text, 3.0 for icons, outlines and rings.
 * @return void
 */
function _g2mlA11yTestAssertContrast(string $label, array $foreground, array $background, float $minimum): void
{
    $ratio = _g2mlA11yTestRatio($foreground, $background);

    assert_true(
        $ratio >= $minimum,
        $label . ' measures ' . number_format($ratio, 2) . ' to 1, but needs ' . number_format($minimum, 1) . ' to 1'
    );
}

/**
 * The hover or pressed colour of the primary brand colour in one block, worked
 * out from the color-mix() written in the style sheet.
 *
 * @param  string $block  Text of one colour block.
 * @param  string $name   "--g2ml-brand-primary-hover" or "--g2ml-brand-primary-active".
 * @return array<int, float>
 */
function _g2mlA11yTestMixedPrimary(string $block, string $name): array
{
    $found = preg_match(
        '~' . preg_quote($name, '~') . ':\s*color-mix\(in srgb, var\(--g2ml-brand-primary\) (\d+)%, (white|black)\);~',
        $block,
        $matches
    );

    assert_same(1, $found, 'The color-mix() for ' . $name . ' was not found');

    $primary = _g2mlA11yTestColour(_g2mlA11yTestVariable($block, '--g2ml-brand-primary'));

    if ($matches[2] === 'white')
    {
        $other = array(255.0, 255.0, 255.0, 1.0);
    }
    else
    {
        $other = array(0.0, 0.0, 0.0, 1.0);
    }

    return _g2mlA11yTestMix($primary, $other, (float) $matches[1]);
}

/**
 * Every .php file under some folders of the repository (given relative to the
 * repository root), as paths relative to the repository root.
 *
 * @param  array<int, string> $folders
 * @param  array<int, string> $skipFragments  A file whose path contains any of these is left out.
 * @return array<int, string>
 */
function _g2mlA11yTestPhpFiles(array $folders, array $skipFragments): array
{
    $root  = dirname(__DIR__, 2);
    $files = array();

    foreach ($folders as $folder)
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root . '/' . $folder, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo)
        {
            $path = str_replace('\\', '/', $fileInfo->getPathname());

            if (substr($path, -4) !== '.php')
            {
                continue;
            }

            $relative = substr($path, strlen($root) + 1);
            $skip     = false;

            foreach ($skipFragments as $fragment)
            {
                if (strpos('/' . $relative, $fragment) !== false)
                {
                    $skip = true;
                }
            }

            if ($skip === false)
            {
                $files[] = $relative;
            }
        }
    }

    sort($files);

    return $files;
}

/**
 * The rows of seed 072 as key => English text.
 *
 * @return array<string, string>
 */
function _g2mlA11yTestSeedRows(): array
{
    $seed = _g2mlA11yTestRead('web/_sql/seeds/072_accessibility_translations.sql');
    $rows = array();

    $matches = array();
    preg_match_all("~\\('en-GB', '([^']+)', '((?:[^']|'')*)', '([^']*)', 1\\)~", $seed, $matches, PREG_SET_ORDER);

    foreach ($matches as $match)
    {
        $rows[$match[1]] = str_replace("''", "'", $match[2]);
    }

    return $rows;
}

/**
 * Run a short PHP script in a separate process and give back its output.
 *
 * @param  string $body  PHP code without the opening tag.
 * @return string        Output, or an empty string if the process did not run.
 */
function _g2mlA11yTestChild(string $body): string
{
    $tempFile = tempnam(sys_get_temp_dir(), 'g2ml_a11y_');

    if ($tempFile === false)
    {
        return '';
    }

    file_put_contents($tempFile, '<?php' . "\n" . 'declare(strict_types=1);' . "\n" . $body);

    $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tempFile) . ' 2>/dev/null');

    unlink($tempFile);

    if (!is_string($output))
    {
        return '';
    }

    return $output;
}

/**
 * Print the cookie banner file in a separate process and return what it
 * printed. The stand-in functions say that consent is needed.
 *
 * @param  bool $withTranslate  True to define __() so that it echoes "[T:key]".
 * @return string               The HTML, or an empty string if the process failed.
 */
function _g2mlA11yTestBannerHtml(bool $withTranslate): string
{
    $root = dirname(__DIR__, 2);

    $body  = 'define(\'G2ML_INCLUDES\', ' . var_export($root . '/web/_includes', true) . ');' . "\n";
    $body .= 'function getSetting($key, $default = null) { return $default; }' . "\n";
    $body .= 'function g2ml_hasValidConsent() { return false; }' . "\n";
    $body .= 'function g2ml_generateCSRFToken($name) { return \'token\'; }' . "\n";
    $body .= 'function g2ml_mainSiteURL($path) { return $path; }' . "\n";

    if ($withTranslate === true)
    {
        $body .= 'function __(string $key, array $replacements = []): string { return \'[T:\' . $key . \']\'; }' . "\n";
    }

    $body .= 'ob_start();' . "\n";
    $body .= 'require G2ML_INCLUDES . \'/cookie_banner.php\';' . "\n";
    $body .= 'echo json_encode([\'html\' => ob_get_clean()]);' . "\n";

    $decoded = json_decode(_g2mlA11yTestChild($body), true);

    if (!is_array($decoded))
    {
        return '';
    }

    return (string) $decoded['html'];
}

// ============================================================================
// 🧭 Navigation bar: one constant colour, readable in both modes
// ============================================================================

test('navigation bar: it has one constant colour in both modes, and everything on it passes 4.5 to 1 (#287)', function (): void
{
    $css   = _g2mlA11yTestCss();
    $light = _g2mlA11yTestColourBlock($css, 'light');
    $dark  = _g2mlA11yTestColourBlock($css, 'dark');

    // The three bar colours are written once, in the light block, and the dark
    // block must not repeat them: if it did, the bar could differ by mode again.
    foreach (array('--g2ml-nav-bg', '--g2ml-nav-fg', '--g2ml-nav-fg-soft') as $name)
    {
        _g2mlA11yTestVariable($light, $name);
        assert_false(strpos($dark, $name . ':') !== false, 'The dark block redefines ' . $name . ', so the bar could change with the colour mode');
    }

    $barRule = _g2mlA11yTestRuleBody($css, '.navbar.bg-primary');
    assert_contains('background-color: var(--g2ml-nav-bg) !important;', $barRule, 'The bar does not take its colour from --g2ml-nav-bg');

    $bar  = _g2mlA11yTestColour(_g2mlA11yTestVariable($light, '--g2ml-nav-bg'));
    $text = _g2mlA11yTestColour(_g2mlA11yTestVariable($light, '--g2ml-nav-fg'));
    $soft = _g2mlA11yTestColour(_g2mlA11yTestVariable($light, '--g2ml-nav-fg-soft'));

    _g2mlA11yTestAssertContrast('White text and icons on the bar', $text, $bar, 4.5);
    _g2mlA11yTestAssertContrast('The softer white of inactive links on the bar', $soft, $bar, 4.5);

    // Bootstrap's own "light" outline button is what Sign Up uses on the bar.
    _g2mlA11yTestAssertContrast('The Sign Up outline button on the bar', _g2mlA11yTestColour('#f8f9fa'), $bar, 4.5);

    // Menus that open from the bar are dark (Bootstrap's dark menu colours).
    _g2mlA11yTestAssertContrast('Menu text on the dark menu', _g2mlA11yTestColour('#dee2e6'), _g2mlA11yTestColour('#212529'), 4.5);
    _g2mlA11yTestAssertContrast('The current row of a menu', $text, $bar, 4.5);
});

test('navigation bar: the inactive link, toggler and menu rules use the bar colours (#287)', function (): void
{
    $css = _g2mlA11yTestCss();

    $inactive = _g2mlA11yTestRuleBody($css, '.navbar[data-bs-theme="dark"] .nav-link:not(.active)');
    assert_contains('var(--g2ml-nav-fg-soft)', $inactive);

    // The signed-in name is a dropdown toggle; it must get the same colour.
    assert_false(strpos($css, ':not(.dropdown-toggle)') !== false, 'The user-menu toggle is excluded from the readable nav-link colour again');

    $variables = _g2mlA11yTestFlat(_g2mlA11yTestRuleBody($css, 'nav.navbar[data-bs-theme="dark"]'));
    assert_contains('--bs-navbar-color: var(--g2ml-nav-fg-soft);', $variables);
    assert_contains('--bs-navbar-toggler-border-color: var(--g2ml-nav-fg-soft);', $variables);
    assert_contains('--g2ml-focus-color: var(--g2ml-nav-fg);', $variables, 'The ring on the bar is no longer white');

    $icon = _g2mlA11yTestRuleBody($css, 'nav.navbar[data-bs-theme="dark"] .navbar-toggler-icon');
    assert_contains('rgb%28255, 255, 255%29', $icon, 'The menu icon is no longer solid white');

    $menu = _g2mlA11yTestFlat(_g2mlA11yTestRuleBody($css, 'nav.navbar .dropdown-menu'));
    assert_contains('--bs-dropdown-link-active-bg: var(--g2ml-nav-bg);', $menu);
});

test('navigation bar: the markup keeps its fallback classes, and the logo has real sizes on a white chip (#287)', function (): void
{
    $nav = _g2mlA11yTestRead('web/_includes/nav.php');

    // bg-primary stays so the bar still has a colour if the style sheet fails to load.
    assert_contains('<nav class="navbar navbar-expand-lg bg-primary" data-bs-theme="dark"', $nav);
    assert_contains('<span class="d-inline-block bg-white rounded px-2 py-1">', $nav, 'The nav logo is no longer on a white chip');
    assert_false(strpos($nav, 'width="auto"') !== false, 'The nav logo has width="auto", which is not a valid value');

    $footer = _g2mlA11yTestRead('web/_includes/footer.php');
    assert_contains('bg-white rounded px-2 py-1', $footer, 'The footer logo is no longer on a white chip');
    assert_false(strpos($footer, 'width="auto"') !== false, 'The footer logo has width="auto", which is not a valid value');
});

// ============================================================================
// 🔘 Buttons: every state passes in both modes
// ============================================================================

test('buttons: in light mode every button state passes (#287)', function (): void
{
    $css   = _g2mlA11yTestCss();
    $block = _g2mlA11yTestColourBlock($css, 'light');

    $onBrand  = _g2mlA11yTestColour(_g2mlA11yTestVariable($block, '--g2ml-on-brand'));
    $primary  = _g2mlA11yTestColour(_g2mlA11yTestVariable($block, '--g2ml-brand-primary'));
    $hover    = _g2mlA11yTestMixedPrimary($block, '--g2ml-brand-primary-hover');
    $pressed  = _g2mlA11yTestMixedPrimary($block, '--g2ml-brand-primary-active');
    $inkOne   = _g2mlA11yTestColour(_g2mlA11yTestVariable($block, '--g2ml-ink-primary'));
    $inkTwo   = _g2mlA11yTestColour(_g2mlA11yTestVariable($block, '--g2ml-ink-secondary'));
    $focus    = _g2mlA11yTestColour(_g2mlA11yTestVariable($block, '--g2ml-focus-color'));

    // Bootstrap's page backgrounds in light mode: body, light panels, and the grey of tables and cards.
    $pages = array('#ffffff', '#f8f9fa', '#e9ecef');

    _g2mlA11yTestAssertContrast('Primary button text (normal)', $onBrand, $primary, 4.5);
    _g2mlA11yTestAssertContrast('Primary button text (hover)', $onBrand, $hover, 4.5);
    _g2mlA11yTestAssertContrast('Primary button text (pressed)', $onBrand, $pressed, 4.5);
    _g2mlA11yTestAssertContrast('Outline-primary hover text on its fill', $onBrand, $inkOne, 4.5);
    _g2mlA11yTestAssertContrast('Outline-secondary hover text on its fill', $onBrand, $inkTwo, 4.5);

    foreach ($pages as $page)
    {
        $background = _g2mlA11yTestColour($page);

        _g2mlA11yTestAssertContrast('Outline-primary text on ' . $page, $inkOne, $background, 4.5);
        _g2mlA11yTestAssertContrast('Outline-secondary text on ' . $page, $inkTwo, $background, 4.5);
        _g2mlA11yTestAssertContrast('Primary button edge on ' . $page, $primary, $background, 3.0);
        _g2mlA11yTestAssertContrast('Focus ring on ' . $page, $focus, $background, 3.0);
    }
});

test('buttons: in dark mode every button state passes (#287)', function (): void
{
    $css   = _g2mlA11yTestCss();
    $block = _g2mlA11yTestColourBlock($css, 'dark');

    $onBrand = _g2mlA11yTestColour(_g2mlA11yTestVariable($block, '--g2ml-on-brand'));
    $primary = _g2mlA11yTestColour(_g2mlA11yTestVariable($block, '--g2ml-brand-primary'));
    $hover   = _g2mlA11yTestMixedPrimary($block, '--g2ml-brand-primary-hover');
    $pressed = _g2mlA11yTestMixedPrimary($block, '--g2ml-brand-primary-active');
    $inkOne  = _g2mlA11yTestColour(_g2mlA11yTestVariable($block, '--g2ml-ink-primary'));
    $inkTwo  = _g2mlA11yTestColour(_g2mlA11yTestVariable($block, '--g2ml-ink-secondary'));
    $danger  = _g2mlA11yTestColour(_g2mlA11yTestVariable($block, '--g2ml-brand-danger'));
    $success = _g2mlA11yTestColour(_g2mlA11yTestVariable($block, '--g2ml-brand-success'));
    $focus   = _g2mlA11yTestColour(_g2mlA11yTestVariable($block, '--g2ml-focus-color'));

    _g2mlA11yTestAssertContrast('Primary button text (normal)', $onBrand, $primary, 4.5);
    _g2mlA11yTestAssertContrast('Primary button text (hover)', $onBrand, $hover, 4.5);
    _g2mlA11yTestAssertContrast('Primary button text (pressed)', $onBrand, $pressed, 4.5);

    foreach (array('Outline-primary' => $inkOne, 'Outline-secondary' => $inkTwo, 'Outline-danger' => $danger, 'Outline-success' => $success) as $name => $ink)
    {
        _g2mlA11yTestAssertContrast($name . ' hover text on its fill', $onBrand, $ink, 4.5);
    }

    // Bootstrap's page backgrounds in dark mode: body, and the slightly lighter panels.
    foreach (array('#212529', '#2b3035') as $page)
    {
        $background = _g2mlA11yTestColour($page);

        _g2mlA11yTestAssertContrast('Outline-primary text on ' . $page, $inkOne, $background, 4.5);
        _g2mlA11yTestAssertContrast('Outline-secondary text on ' . $page, $inkTwo, $background, 4.5);
        _g2mlA11yTestAssertContrast('Outline-danger text on ' . $page, $danger, $background, 4.5);
        _g2mlA11yTestAssertContrast('Outline-success text on ' . $page, $success, $background, 4.5);
        _g2mlA11yTestAssertContrast('Primary button edge on ' . $page, $primary, $background, 3.0);
        _g2mlA11yTestAssertContrast('Focus ring on ' . $page, $focus, $background, 3.0);
    }
});

test('buttons: each button rule sets the text colour for normal, hover, pressed and disabled (#287)', function (): void
{
    $css = _g2mlA11yTestCss();

    // Bootstrap's keyboard-focus colours copy its hover colours, so setting
    // the four colours here is what makes a focused button follow the brand.
    $selectors = array(
        '.btn.btn-primary',
        '.btn.btn-outline-primary',
        '.btn.btn-outline-secondary',
        '[data-bs-theme="dark"] .btn.btn-outline-danger',
        '[data-bs-theme="dark"] .btn.btn-outline-success',
    );

    foreach ($selectors as $selector)
    {
        $body = _g2mlA11yTestRuleBody($css, $selector);

        foreach (array('--bs-btn-color:', '--bs-btn-hover-color:', '--bs-btn-active-color:', '--bs-btn-disabled-color:') as $property)
        {
            assert_contains($property, $body, $selector . ' does not set ' . $property);
        }
    }

    // The filled button must not fall back to Bootstrap's white text in dark mode.
    assert_contains('--bs-btn-color: var(--g2ml-on-brand);', _g2mlA11yTestFlat(_g2mlA11yTestRuleBody($css, '.btn.btn-primary')));
});

// ============================================================================
// 🔦 Focus ring: every kind of control gets it, and nothing turns it off
// ============================================================================

test('focus ring: one rule gives buttons, menu links, text boxes and tick boxes the 3px ring (#287)', function (): void
{
    $css   = _g2mlA11yTestCss();
    $found = false;

    $needed = array(
        '.btn:focus-visible',
        '.btn-close:focus-visible',
        '.nav-link:focus-visible',
        '.navbar-toggler:focus-visible',
        '.form-control:focus-visible',
        '.form-select:focus-visible',
        '.form-check-input:focus-visible',
        '.form-range:focus-visible',
        '.dropdown-item:focus-visible',
    );

    foreach (_g2mlA11yTestRules($css) as $rule)
    {
        if (strpos($rule[1], 'outline: 3px solid var(--g2ml-focus-color);') === false)
        {
            continue;
        }

        $listed = array();

        foreach ($rule[0] as $selector)
        {
            // The leading "html " makes each rule outweigh Bootstrap's own focus rules.
            if (substr($selector, 0, 5) === 'html ')
            {
                $listed[] = substr($selector, 5);
            }
        }

        $missing = array_diff($needed, $listed);

        if (count($missing) === 0)
        {
            $found = true;

            // Bootstrap draws its own faint glow with box-shadow; two rings would stack.
            assert_contains('box-shadow: none;', $rule[1], 'The ring rule leaves Bootstrap\'s faint glow switched on');
        }
    }

    assert_true($found, 'No rule gives every one of these controls the 3px ring: ' . implode(', ', $needed));

    // The plain rule for links and everything else stays.
    $plain = _g2mlA11yTestRuleBody($css, '*:focus-visible');
    assert_contains('outline: 3px solid var(--g2ml-focus-color);', $plain);
});

test('focus ring: nothing switches the outline off on a focused control (#287)', function (): void
{
    foreach (_g2mlA11yTestRules(_g2mlA11yTestCss()) as $rule)
    {
        $focused = false;

        foreach ($rule[0] as $selector)
        {
            if (strpos($selector, ':focus') !== false)
            {
                $focused = true;
            }
        }

        if ($focused === false)
        {
            continue;
        }

        $switchedOff = preg_match('~outline(-width)?:\s*(none|0)\s*(;|$)~', $rule[1]) === 1;

        assert_false($switchedOff, 'A focus rule turns the outline off: ' . implode(', ', $rule[0]));
    }
});

test('focus ring: the ring colours reach 3 to 1 on the bar and on both page backgrounds (#287)', function (): void
{
    $css   = _g2mlA11yTestCss();
    $light = _g2mlA11yTestColourBlock($css, 'light');
    $bar   = _g2mlA11yTestColour(_g2mlA11yTestVariable($light, '--g2ml-nav-bg'));
    $white = _g2mlA11yTestColour(_g2mlA11yTestVariable($light, '--g2ml-nav-fg'));

    _g2mlA11yTestAssertContrast('The white ring on the navigation bar', $white, $bar, 3.0);

    // The skip link is the same blue as the bar, so its ring is white too.
    $skip = _g2mlA11yTestRuleBody($css, '.skip-to-content:focus-visible');
    assert_contains('outline-color: var(--g2ml-skip-color);', $skip);
    _g2mlA11yTestAssertContrast('The white ring on the skip link', _g2mlA11yTestColour(_g2mlA11yTestVariable($light, '--g2ml-skip-color')), _g2mlA11yTestColour(_g2mlA11yTestVariable($light, '--g2ml-skip-bg')), 3.0);
});

// ============================================================================
// 🦶 Footer headings
// ============================================================================

test('footer: the two column titles are level 2 headings drawn at the old size, with no level 6 left (#287)', function (): void
{
    $footer = _g2mlA11yTestRead('web/_includes/footer.php');

    assert_false(strpos($footer, '<h6') !== false, 'The footer still has an h6, which skips heading levels');
    assert_same(2, substr_count($footer, '<h2 class="h6">'), 'The footer should have two h2 titles drawn with the h6 class');
});

// ============================================================================
// 🍪 Cookie banner
// ============================================================================

test('cookie banner: header.php prints it straight after the skip link, and footer.php no longer does (#287)', function (): void
{
    $header = _g2mlA11yTestRead('web/_includes/header.php');
    $footer = _g2mlA11yTestRead('web/_includes/footer.php');

    $skipPosition   = strpos($header, 'echo skipToContent();');
    $bannerPosition = strpos($header, "'cookie_banner.php'");

    assert_true($skipPosition !== false, 'header.php no longer prints the skip link');
    assert_true($bannerPosition !== false, 'header.php does not include the cookie banner');
    assert_true($bannerPosition > $skipPosition, 'The cookie banner is printed before the skip link');

    assert_same(0, preg_match('~require(_once)?[^;]*cookie_banner\\.php~', $footer), 'footer.php prints the banner again');
});

test('cookie banner: it is a labelled region with no heading of its own, below the dialog backdrop (#287)', function (): void
{
    $html = _g2mlA11yTestBannerHtml(false);

    assert_true($html !== '', 'The banner process did not return any HTML');

    $dialogPosition = strpos($html, 'id="g2ml-cookie-modal"');
    assert_true($dialogPosition !== false, 'The preferences dialog is missing from the banner file');

    $bannerOnly = substr($html, 0, (int) $dialogPosition);

    assert_same(0, preg_match('~<h[1-6]\b~i', $bannerOnly), 'The banner has a heading element, which would come before the page\'s own heading');
    assert_contains('role="region"', $bannerOnly);
    assert_contains('aria-labelledby="cookie-banner-title"', $bannerOnly);
    assert_contains('id="cookie-banner-title"', $bannerOnly, 'The element that names the banner is missing');
    assert_contains('id="cookie-banner-desc"', $bannerOnly);

    // Bootstrap's dialog backdrop is 1050 and the dialog is 1055.
    assert_same(1, preg_match('~z-index:\s*(\d+)~', $bannerOnly, $matches), 'The banner has no z-index');
    assert_true((int) $matches[1] < 1050, 'The banner sits above the preferences dialog backdrop');
    assert_true((int) $matches[1] > 1030, 'The banner must sit above anything Bootstrap pins with fixed-top (z-index 1030)');

    // The dialog's own title is a level 2 heading, and the dialog is separate from the page outline.
    assert_contains('<h2 class="modal-title h5" id="cookie-modal-title">', $html);
});

test('cookie banner: the link says what it opens, and the words are translated (#287)', function (): void
{
    $plain = _g2mlA11yTestBannerHtml(false);

    assert_same(1, preg_match('~<a href="/legal/cookies"[^>]*>(.*?)</a>~s', $plain, $matches), 'The cookie policy link was not found');

    $name = trim((string) preg_replace('~\s+~', ' ', strip_tags($matches[1])));
    assert_same('Learn more about our cookie policy', $name, 'The link\'s name does not say what it opens');
    assert_contains('<span class="visually-hidden"> about our cookie policy</span>', $matches[1], 'The extra words are not hidden');

    $translated = _g2mlA11yTestBannerHtml(true);

    assert_contains('[T:cookie.learn_more]', $translated);
    assert_contains('[T:cookie.learn_more_suffix]', $translated, 'The hidden words do not go through __()');
});

test('cookie banner: every element cookie-consent.js looks for is still there (#287)', function (): void
{
    $script = _g2mlA11yTestRead('web/Go2My.Link/public_html/js/cookie-consent.js');
    $html   = _g2mlA11yTestBannerHtml(false);

    assert_true($html !== '', 'The banner process did not return any HTML');

    $found = preg_match_all("~getElementById\\('([^']+)'\\)~", $script, $matches);
    assert_true($found > 0, 'cookie-consent.js does not look for any element');

    // main-content belongs to the page, not to the banner.
    foreach (array_unique($matches[1]) as $id)
    {
        if ($id === 'main-content')
        {
            continue;
        }

        assert_contains('id="' . $id . '"', $html, 'cookie-consent.js looks for #' . $id . ', which the banner no longer prints');
    }
});

test('cookie banner: its height is shared with the page so keyboard focus is not hidden behind it (#287)', function (): void
{
    $script = _g2mlA11yTestRead('web/Go2My.Link/public_html/js/cookie-consent.js');
    $css    = _g2mlA11yTestCss();

    assert_contains("'--g2ml-cookie-banner-height'", $script);
    assert_contains("removeProperty('--g2ml-cookie-banner-height')", $script, 'The space is not given back when the banner is dismissed');

    assert_contains('scroll-padding-bottom: var(--g2ml-cookie-banner-height, 0px);', $css);
    assert_contains('padding-bottom: var(--g2ml-cookie-banner-height, 0px);', $css);
    assert_contains('max-height: 40vh;', _g2mlA11yTestRuleBody($css, '#g2ml-cookie-banner'), 'The banner may grow taller than 40% of the window');
});

// ============================================================================
// 🔔 Alert boxes
// ============================================================================

test('alert boxes: every one in the site has a role (#287)', function (): void
{
    $files    = _g2mlA11yTestPhpFiles(array('web'), array('/_libraries/', '/email_templates/', '/assets/'));
    $problems = array();

    foreach ($files as $file)
    {
        $source  = (string) file_get_contents(dirname(__DIR__, 2) . '/' . $file);
        $matches = array();

        // The tag may hold a PHP block, and the end of that block must not end the tag early.
        preg_match_all('~<(?:div|p|span|section|aside)\b((?:\?>|[^>])*)>~', $source, $matches, PREG_SET_ORDER);

        foreach ($matches as $match)
        {
            if (preg_match('~\bclass="([^"]*)"~', $match[1], $classMatch) !== 1)
            {
                continue;
            }

            $classes = preg_split('~\s+~', trim($classMatch[1]));

            if (is_array($classes) && in_array('alert', $classes, true) && strpos($match[1], 'role=') === false)
            {
                $problems[] = $file . ': ' . substr(_g2mlA11yTestFlat($match[0]), 0, 80);
            }
        }
    }

    assert_same(0, count($problems), 'These alert boxes have no role: ' . implode(' | ', $problems));
});

// ============================================================================
// 🔗 Links that open a new tab
// ============================================================================

test('new-tab notice: the helper adds visually hidden words, and only to links that open a new tab (#287)', function (): void
{
    assert_same('<span class="visually-hidden"> (opens in a new tab)</span>', newTabNotice());
    assert_same('<span id="note" class="visually-hidden"> (opens in a new tab)</span>', newTabNotice('note'));

    $notice = newTabNotice();
    $input  = 'See <a href="/a" target="_blank" rel="noopener">One</a> and <a rel="x" href="/b">Two</a> and <a rel="x" target="_blank" href="/c">Three</a>.';
    $wanted = 'See <a href="/a" target="_blank" rel="noopener">One' . $notice . '</a> and <a rel="x" href="/b">Two</a> and <a rel="x" target="_blank" href="/c">Three' . $notice . '</a>.';

    assert_same($wanted, newTabNoticeInLinks($input));
    assert_same('No links here.', newTabNoticeInLinks('No links here.'));
});

test('new-tab notice: when the translation comes back as its own key, the English is used instead (#287)', function (): void
{
    $root = dirname(__DIR__, 2);

    $body  = 'function __(string $key, array $replacements = []): string { return $key; }' . "\n";
    $body .= 'require ' . var_export($root . '/web/_includes/accessibility.php', true) . ';' . "\n";
    $body .= 'echo newTabNotice();' . "\n";

    assert_same('<span class="visually-hidden"> (opens in a new tab)</span>', _g2mlA11yTestChild($body));

    $body  = 'function __(string $key, array $replacements = []): string { return \'[T:\' . $key . \']\'; }' . "\n";
    $body .= 'require ' . var_export($root . '/web/_includes/accessibility.php', true) . ';' . "\n";
    $body .= 'echo newTabNotice();' . "\n";

    assert_contains('[T:common.opens_new_tab]', _g2mlA11yTestChild($body), 'The notice does not go through __()');
});

test('new-tab notice: every link that opens a new tab, outside LinksPages, says so (#287)', function (): void
{
    $files    = _g2mlA11yTestPhpFiles(
        array('web/_includes', 'web/Go2My.Link/public_html/pages', 'web/Go2My.Link/_admin/public_html/pages'),
        array('/web/_includes/accessibility.php', '/email_templates/')
    );
    $problems = array();
    $checked  = 0;

    foreach ($files as $file)
    {
        $source  = (string) file_get_contents(dirname(__DIR__, 2) . '/' . $file);
        $matches = array();

        // The opening tag may hold a PHP block, and the end of that block must not end the tag early.
        preg_match_all('~<a\b((?:\?>|[^>])*?\btarget="_blank"(?:\?>|[^>])*)>(.*?)</a>~is', $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match)
        {
            $checked++;

            $opening = $match[1][0];
            $inside  = $match[2][0];

            if (strpos($inside, 'newTabNotice(') !== false)
            {
                continue;
            }

            // A link whose text is filled in by JavaScript points at a hidden note beside it.
            if (preg_match('~aria-describedby="([^"]+)"~', $opening, $described) === 1 && strpos($source, "newTabNotice('" . $described[1] . "')") !== false)
            {
                continue;
            }

            // A sentence held in a PHP string goes through the helper that adds the words to every such link.
            $lineStart  = (int) strrpos(substr($source, 0, $match[0][1]), "\n");
            $linePrefix = substr($source, $lineStart, $match[0][1] - $lineStart);

            if (strpos($source, 'echo newTabNoticeInLinks(') !== false && strpos($linePrefix, "= '") !== false)
            {
                continue;
            }

            $problems[] = $file . ': ' . substr(_g2mlA11yTestFlat($match[0][0]), 0, 80);
        }
    }

    // Eleven links in plain HTML and one inside a PHP string were there when this check was written.
    assert_true($checked >= 12, 'Only ' . $checked . ' target="_blank" links were found, so this check may be looking in the wrong place');
    assert_same(0, count($problems), 'These links open a new tab without saying so: ' . implode(' | ', $problems));
});

// ============================================================================
// 📋 Copy buttons, repeated ids and long addresses
// ============================================================================

test('copy buttons: the accessible name changes along with the words on the button (#287)', function (): void
{
    $app = _g2mlA11yTestRead('web/Go2My.Link/public_html/js/app.js');

    assert_contains("copyBtn.setAttribute('aria-label', copiedText);", $app, 'The home page copy button keeps its old name after copying');
    assert_contains("copyBtn.setAttribute('aria-label', originalLabel);", $app, 'The home page copy button does not get its name back');

    $pages = array(
        'web/Go2My.Link/public_html/pages/home.php',
        'web/Go2My.Link/_admin/public_html/pages/api-keys/index.php',
        'web/Go2My.Link/_admin/public_html/pages/links/create/index.php',
    );

    foreach ($pages as $page)
    {
        $source = _g2mlA11yTestRead($page);

        assert_contains('data-copied-text="', $source, $page . ' has no translated "Copied!" for its copy button');
        assert_contains("__('common.copied')", $source, $page . ' does not translate "Copied!"');
    }

    // The two dashboard buttons rename themselves in their own click code.
    foreach (array_slice($pages, 1) as $page)
    {
        assert_contains("b.setAttribute('aria-label',t)", _g2mlA11yTestRead($page), $page . ' does not rename its copy button');
    }
});

test('consent page: it reuses no id from the cookie banner, and every aria-describedby points at an id it has (#287)', function (): void
{
    $banner  = _g2mlA11yTestRead('web/_includes/cookie_banner.php');
    $consent = _g2mlA11yTestRead('web/Go2My.Link/_admin/public_html/pages/privacy/consent/index.php');

    preg_match_all('~\bid="([^"]+)"~', $banner, $bannerIds);
    assert_true(count($bannerIds[1]) > 0, 'No ids were found in the cookie banner');

    foreach (array_unique($bannerIds[1]) as $id)
    {
        assert_false(strpos($consent, 'id="' . $id . '"') !== false, 'The consent page uses id="' . $id . '", which the cookie banner also uses');
    }

    preg_match_all('~aria-describedby="([^"]+)"~', $consent, $described);

    foreach (array_unique($described[1]) as $id)
    {
        assert_contains('id="' . $id . '"', $consent, 'aria-describedby points at #' . $id . ', which the consent page does not have');
    }
});

test('long addresses: the LinksPage editor lets a long address wrap instead of widening the page (#287)', function (): void
{
    $source = _g2mlA11yTestRead('web/Go2My.Link/_admin/public_html/pages/linkspage/edit/index.php');

    $wrapper = strpos($source, '<div class="text-break">');
    $address = strpos($source, "\$itemRow['itemURL']");

    assert_true($wrapper !== false, 'The link list has no text-break wrapper');
    assert_true($address !== false, 'The link list no longer prints the address');
    assert_true($wrapper < $address && ($address - $wrapper) < 2500, 'The address is not inside the text-break wrapper');

    assert_contains('<p class="text-body-secondary text-break">', $source, 'The public address line does not wrap');
});

// ============================================================================
// 🌍 Seed 072: every new piece of wording, with the same English as the fallback
// ============================================================================

test('seed 072: it holds exactly the three new keys, INSERT IGNORE, en-GB and verified (#287)', function (): void
{
    $seed = _g2mlA11yTestRead('web/_sql/seeds/072_accessibility_translations.sql');
    $rows = _g2mlA11yTestSeedRows();

    $keys = array_keys($rows);
    sort($keys);

    assert_same(
        array('common.copied', 'common.opens_new_tab', 'cookie.learn_more_suffix'),
        $keys,
        'Seed 072 does not hold exactly the three new keys'
    );

    assert_same(3, count($rows));
    assert_contains('INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)', $seed);
    assert_same(0, preg_match('~^\s*INSERT\s+INTO~mi', $seed), 'The seed has a plain INSERT, which would fail on a second run');

    // No other seed may define the same keys.
    foreach (glob(dirname(__DIR__, 2) . '/web/_sql/seeds/*.sql') as $other)
    {
        if (basename($other) === '072_accessibility_translations.sql')
        {
            continue;
        }

        $text = (string) file_get_contents($other);

        foreach (array_keys($rows) as $key)
        {
            assert_false(strpos($text, "'en-GB', '" . $key . "'") !== false, basename($other) . ' also defines ' . $key);
        }
    }
});

test('seed 072: the English matches the fallback written where each key is used (#287)', function (): void
{
    $rows = _g2mlA11yTestSeedRows();

    $accessibility = _g2mlA11yTestRead('web/_includes/accessibility.php');
    assert_contains("\$notice = '" . $rows['common.opens_new_tab'] . "';", $accessibility, 'The helper\'s English differs from the seed');
    assert_contains("__('common.opens_new_tab')", $accessibility);

    $banner = _g2mlA11yTestRead('web/_includes/cookie_banner.php');
    assert_contains("__('cookie.learn_more_suffix')", $banner);
    assert_contains("echo '" . $rows['cookie.learn_more_suffix'] . "';", $banner, 'The banner\'s English differs from the seed');

    $copyPages = array(
        'web/Go2My.Link/public_html/pages/home.php',
        'web/Go2My.Link/_admin/public_html/pages/api-keys/index.php',
        'web/Go2My.Link/_admin/public_html/pages/links/create/index.php',
    );

    foreach ($copyPages as $page)
    {
        assert_contains("echo '" . $rows['common.copied'] . "';", _g2mlA11yTestRead($page), $page . ' has different English for the copied message');
    }

    assert_contains("copiedText = '" . $rows['common.copied'] . "';", _g2mlA11yTestRead('web/Go2My.Link/public_html/js/app.js'), 'app.js has different English for the copied message');
});
