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
 * 🧪 Check — the site polish rule, the parts a machine can check (#288)
 * ============================================================================
 *
 * The standing polish check (.claude/memory/working-rules.md, rule 14) asks
 * that nothing a visitor sees looks thrown together. This test runs with the
 * whole unit suite (before every commit, and in CI on pull requests and on
 * pushes to alpha, beta, release-candidate and main) and fails on the parts
 * that can be read from the files:
 *   - a placeholder or preview hosting address, or "lorem ipsum", in shipped
 *     code, .htaccess files included;
 *   - a main-site or dashboard page that sets no title or no description of
 *     its own (commented-out lines do not count);
 *   - console logging, debugger statements or alert() in first-party
 *     JavaScript: .js files and inline <script> blocks in PHP and HTML;
 *   - a development file (notes, dumps, logs, archives, a .git folder)
 *     inside any web root;
 *   - a copyright year, or range of years, written as fixed numbers after a
 *     © sign (as ©, &copy; or &#169;).
 *
 * Other machine-checkable parts of the rule have their own tests:
 *   - page titles reaching the browser: page_before_header_test.php;
 *   - search and sharing tags, favicons, robots.txt and sitemaps:
 *     site_head_and_sitemap_test.php;
 *   - error pages and "no permission" screens on the dashboard:
 *     dashboard_assets_test.php, org_access_denied_test.php;
 *   - a working state on forms: form_feedback_test.php;
 *   - contrast, focus rings and heading levels:
 *     accessibility_contrast_focus_test.php, linkspage_templates_test.php;
 *   - no unused large library, image weight, no design source files in a
 *     web root, working library fallbacks: library_fallbacks_test.php,
 *     logo_and_web_root_weight_test.php;
 *   - no developer comments in page source, no reachable page fragments:
 *     page_source_clean_test.php, pages_folder_blocked_test.php;
 *   - links and buttons that lead somewhere real (and the code behind them):
 *     folder_depth_paths_test.php, legal_links_test.php,
 *     no_php_in_urls_test.php;
 *   - form values escaped once: form_values_escaped_once_test.php.
 *
 * WHAT THIS CHECK CANNOT DO
 *
 * It reads files; it does not open pages in a browser. Layout at different
 * screen sizes, contrast, console errors and dead buttons still need the
 * browser run the rule describes.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * ============================================================================
 */

declare(strict_types=1);

/**
 * Every first-party file under web/ with one of the given extensions
 * (third-party folders and the brand kit left out).
 *
 * @param  array<int, string> $extensions  Without the dot, lower-case
 * @return array<int, string>
 */
function _g2mlSitePolishTestFiles(array $extensions): array
{
    $webRoot  = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'web';
    $files    = array();
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($webRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo)
    {
        $path      = $fileInfo->getPathname();
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (!in_array($extension, $extensions, true))
        {
            continue;
        }

        $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($webRoot) + 1));

        if (preg_match('#(^|/)(_libraries|vendor|BrandKit)/#', $relative) === 1)
        {
            continue;
        }

        if (substr($relative, -7) === '.min.js')
        {
            continue;
        }

        $files[] = $path;
    }

    sort($files);

    return $files;
}

/**
 * PHP source with its comments removed, so a commented-out line does not
 * count as the page setting something.
 *
 * @param  string $source
 * @return string
 */
function _g2mlSitePolishTestWithoutComments(string $source): string
{
    $kept = '';

    foreach (token_get_all($source) as $token)
    {
        if (is_array($token))
        {
            if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT)
            {
                continue;
            }

            $kept .= $token[1];
        }
        else
        {
            $kept .= $token;
        }
    }

    return $kept;
}

/**
 * The JavaScript lines of a file, keyed by line number. A .js file is all
 * JavaScript. In a .php file only the HTML parts outside PHP code are read
 * (so a "<script" mentioned in a PHP comment or string does not count), and
 * in them, as in an .html file, only the lines inside <script>...</script>.
 *
 * @param  string $source
 * @param  string $type    'js', 'php' or 'html'
 * @return array<int, string>
 */
function _g2mlSitePolishTestJavaScriptLines(string $source, string $type): array
{
    $result = array();

    if ($type === 'js')
    {
        foreach (preg_split('/\R/', $source) as $index => $line)
        {
            $result[$index + 1] = $line;
        }

        return $result;
    }

    // The HTML parts, each with the line it starts on.
    $parts = array();

    if ($type === 'php')
    {
        foreach (token_get_all($source) as $token)
        {
            if (is_array($token) && $token[0] === T_INLINE_HTML)
            {
                $parts[] = array($token[2], $token[1]);
            }
        }
    }
    else
    {
        $parts[] = array(1, $source);
    }

    $inScript = false;

    foreach ($parts as $part)
    {
        foreach (preg_split('/\R/', $part[1]) as $offset => $line)
        {
            if (preg_match('/<script\b/i', $line) === 1)
            {
                $inScript = true;
            }

            if ($inScript === true)
            {
                $result[$part[0] + $offset] = $line;
            }

            if (preg_match('/<\/script>/i', $line) === 1)
            {
                $inScript = false;
            }
        }
    }

    return $result;
}

/**
 * File names that are development leftovers, not something to serve:
 * notes, dumps, logs, editor and system files, archives, test pages and
 * package manifests.
 *
 * @return string  A regular expression for a file's base name.
 */
function _g2mlSitePolishTestDevFilePattern(): string
{
    return '/^(readme|changelog|todo|notes|handoff)((\.[a-z]{2})?\.(md|txt|rst))?$|[_-]notes\.(md|txt)$|\.(bak|old|orig|swp|sql|log|sh|map|zip|tar|gz|tgz|7z|rar)$|^(phpinfo|test|info)\.php$|^\.env|^\.DS_Store$|^error_log$|^(composer|package)(-lock)?\.json$|^composer\.lock$/i';
}

/**
 * True when a line prints a copyright year, or a range of years, as fixed
 * numbers ("© 2026", "&copy;&nbsp;2026", "&#169; 2024–2026"). A year or
 * range that ends in a dash before PHP prints the current year passes.
 *
 * @param  string $line
 * @return bool
 */
function _g2mlSitePolishTestHasFixedYear(string $line): bool
{
    $dash    = '(&ndash;|&mdash;|&#8211;|&#8212;|–|—|-)';
    $pattern = '/(&copy;|&#169;|©)(\s|&nbsp;)*(20[0-9]{2}\s*' . $dash . '\s*)?20[0-9]{2}(?!\s*' . $dash . ')/u';

    return preg_match($pattern, $line) === 1;
}

test('site polish: no placeholder or preview hosting address in shipped code (#288)', function (): void
{
    $placeholders = array('vercel.app', 'netlify.app', 'herokuapp.com', 'ngrok.io', 'ngrok-free.app', 'glitch.me', 'repl.co', 'lorem ipsum');
    $problems     = array();

    foreach (_g2mlSitePolishTestFiles(array('php', 'js', 'css', 'html', 'json', 'xml', 'txt', 'yaml', 'htaccess')) as $file)
    {
        $source = strtolower((string) file_get_contents($file));

        foreach ($placeholders as $placeholder)
        {
            if (strpos($source, $placeholder) !== false)
            {
                $problems[] = substr($file, strlen(dirname(__DIR__, 2)) + 1) . ' contains "' . $placeholder . '"';
            }
        }
    }

    assert_same(0, count($problems), implode(' | ', $problems));
});

test('site polish: every main-site and dashboard page sets its own title and description (#288)', function (): void
{
    $folders = array(
        dirname(__DIR__, 2) . '/web/Go2My.Link/public_html/pages',
        dirname(__DIR__, 2) . '/web/Go2My.Link/_admin/public_html/pages',
    );
    $problems = array();

    foreach ($folders as $folder)
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo)
        {
            $path = $fileInfo->getPathname();

            if (substr($path, -4) !== '.php')
            {
                continue;
            }

            $source   = _g2mlSitePolishTestWithoutComments((string) file_get_contents($path));
            $hasTitle = preg_match('/\$pageTitle(Full)?\s*=(?!=)/', $source) === 1;
            $hasDesc  = preg_match('/\$pageDesc\s*=(?!=)/', $source) === 1;

            if (!$hasTitle || !$hasDesc)
            {
                $problems[] = substr($path, strlen(dirname(__DIR__, 2)) + 1);
            }
        }
    }

    assert_same(0, count($problems), 'Pages with no title or no description of their own: ' . implode(', ', $problems));
});

test('site polish: no console logging, debugger or alert() in first-party JavaScript (#288)', function (): void
{
    $problems = array();

    foreach (_g2mlSitePolishTestFiles(array('js', 'php', 'html')) as $file)
    {
        $type = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        foreach (_g2mlSitePolishTestJavaScriptLines((string) file_get_contents($file), $type) as $lineNumber => $line)
        {
            $trimmed = ltrim($line);

            if (strncmp($trimmed, '//', 2) === 0 || strncmp($trimmed, '*', 1) === 0)
            {
                continue;
            }

            if (preg_match('/\bconsole\.(log|debug|info|warn|trace)\s*\(|\bdebugger\s*;|\balert\s*\(/', $line) === 1)
            {
                $problems[] = substr($file, strlen(dirname(__DIR__, 2)) + 1) . ':' . $lineNumber;
            }
        }
    }

    assert_same(0, count($problems), 'Debug output left in: ' . implode(', ', $problems));
});

test('site polish: no development files inside a web root (#288)', function (): void
{
    $roots = array(
        'web/Go2My.Link/public_html', 'web/Go2My.Link/public_html_landing', 'web/Go2My.Link/public_html_redir', 'web/Go2My.Link/_admin/public_html',
        'web/G2My.Link/public_html', 'web/G2My.Link/public_html_landing', 'web/G2My.Link/public_html_redir',
        'web/Lnks.page/public_html', 'web/Lnks.page/public_html_landing', 'web/Lnks.page/public_html_redir',
    );
    $pattern  = _g2mlSitePolishTestDevFilePattern();
    $problems = array();

    foreach ($roots as $root)
    {
        $folder   = dirname(__DIR__, 2) . '/' . $root;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo)
        {
            $insideGit = (strpos(str_replace(DIRECTORY_SEPARATOR, '/', $fileInfo->getPathname()), '/.git/') !== false);

            if ($insideGit || preg_match($pattern, $fileInfo->getFilename()) === 1)
            {
                $problems[] = substr($fileInfo->getPathname(), strlen(dirname(__DIR__, 2)) + 1);
            }
        }
    }

    assert_same(0, count($problems), 'Development files a visitor could fetch: ' . implode(', ', $problems));
});

test('site polish: no copyright year written as a fixed number in visible text (#288)', function (): void
{
    $problems = array();

    foreach (_g2mlSitePolishTestFiles(array('php', 'html')) as $file)
    {
        $lineNumber = 0;

        foreach (preg_split('/\R/', (string) file_get_contents($file)) as $line)
        {
            $lineNumber = $lineNumber + 1;

            if (_g2mlSitePolishTestHasFixedYear($line))
            {
                $problems[] = substr($file, strlen(dirname(__DIR__, 2)) + 1) . ':' . $lineNumber;
            }
        }
    }

    assert_same(0, count($problems), 'Fixed copyright years (use date(\'Y\')): ' . implode(', ', $problems));
});

test('site polish: the copyright and comment helpers catch what they should (#288)', function (): void
{
    assert_true(_g2mlSitePolishTestHasFixedYear('&copy; 2026 Go2My.Link'));
    assert_true(_g2mlSitePolishTestHasFixedYear('© 2024–2026'));
    assert_true(_g2mlSitePolishTestHasFixedYear('&copy; 2024&ndash;2026'));
    assert_true(_g2mlSitePolishTestHasFixedYear('&copy;&nbsp;2026'));
    assert_true(_g2mlSitePolishTestHasFixedYear('&#169; 2026'));
    assert_false(_g2mlSitePolishTestHasFixedYear("&copy; <?php echo date('Y'); ?>"));
    assert_false(_g2mlSitePolishTestHasFixedYear("&copy; 2024&ndash;<?php echo date('Y'); ?>"));

    $stripped = _g2mlSitePolishTestWithoutComments("<?php\n// \$pageTitle = 'x';\n/* \$pageDesc = 'y'; */\n\$other = 1;\n");
    assert_false(strpos($stripped, '$pageTitle') !== false, 'A commented-out title must not count');
    assert_false(strpos($stripped, '$pageDesc') !== false, 'A commented-out description must not count');
});

test('site polish: the JavaScript-line and development-file helpers behave as described (#288)', function (): void
{
    $phpComment = "<?php\n/**\n * Strips <script> tags.\n */\nfunction f(): string\n{\n    return 'javascript:alert(1)';\n}\n";
    assert_same(array(), _g2mlSitePolishTestJavaScriptLines($phpComment, 'php'), 'A <script> mentioned in PHP code is not JavaScript');

    $inline = "<?php \$x = 1; ?>\n<p>Hi</p>\n<script>\nconsole.log('x');\n</script>\n<p>After</p>\n";
    $lines  = _g2mlSitePolishTestJavaScriptLines($inline, 'php');
    assert_true(isset($lines[4]) && strpos($lines[4], 'console.log') !== false, 'Inline script lines keep their real line numbers');
    assert_false(isset($lines[6]), 'Lines after </script> are not JavaScript');

    $pattern = _g2mlSitePolishTestDevFilePattern();
    foreach (array('README.md', 'README.en.md', 'NOTES.md', 'notes.txt', 'DEV_NOTES.md', 'dev-notes.md', 'HANDOFF.md', 'README', '.DS_Store', 'error_log', 'dump.sql', 'site.zip', 'phpinfo.php', 'package.json', '.env.local') as $name)
    {
        assert_true(preg_match($pattern, $name) === 1, $name . ' should count as a development file');
    }
    foreach (array('index.php', 'notes.php', 'notes.js', 'changelog.php', 'style.css', 'favicon.ico', 'robots.txt', 'sitemap.xml', '.htaccess', '.gitkeep') as $name)
    {
        assert_false(preg_match($pattern, $name) === 1, $name . ' should not count as a development file');
    }

    assert_false(_g2mlSitePolishTestHasFixedYear("&copy; 2024&mdash;<?php echo date('Y'); ?>"), 'An em dash before date(\'Y\') is allowed');
});
