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
 * 🧪 Check — search and sharing basics (#282)
 * ============================================================================
 *
 * The sweep of 2026-10-05 found no favicon on go2my.link or the dashboard, no
 * canonical link, no share image or Twitter card, every page marked
 * "index, follow" (including sign-in and error pages), and no robots.txt or
 * sitemap on go2my.link. This check fails if any of that comes back:
 *   - header.php prints the canonical link, og:url for the page, og:image,
 *     og:site_name and twitter:card, and reads the robots value from
 *     $pageRobots; run for each way a page is reached, it names the page
 *     actually shown and prints a canonical only on pages that may be
 *     indexed;
 *   - the private and error pages set $pageRobots to noindex, and robots.txt
 *     does not block them (a search engine only sees "noindex" on a page it
 *     may fetch);
 *   - every favicon, touch icon and share image file the head names exists
 *     in each web root that uses header.php, the touch icon is 180×180 with
 *     no transparent pixels, and the share image is 1200×630;
 *   - each sitemap.xml validates against web/_schemas/external/sitemap-0.9.xsd,
 *     that schema rejects a badly formed entry, every go2my.link address in
 *     the sitemap has a page behind it, and robots.txt points at it.
 *
 * WHAT THIS CHECK CANNOT DO
 *
 * It reads files, and runs header.php on its own in a child process with
 * stand-ins for the rest of the site; it does not render whole pages through
 * Apache, so it cannot see a tag that a page removes at runtime. The child
 * process needs PHP's shell_exec(), which some hosts switch off.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * ============================================================================
 */

declare(strict_types=1);

/**
 * Absolute path of a file in the repository.
 *
 * @param  string $relative
 * @return string
 */
function _g2mlSiteHeadTestPath(string $relative): string
{
    return dirname(__DIR__, 2) . '/' . $relative;
}

test('site head: header.php prints canonical, og:url, og:image, og:site_name, twitter:card and a per-page robots value (#282)', function (): void
{
    $header = (string) file_get_contents(_g2mlSiteHeadTestPath('web/_includes/header.php'));

    $required = array(
        '<link rel="canonical"',
        '<meta property="og:url" content="<?php echo htmlspecialchars($pageURL',
        '<meta property="og:image"',
        '<meta property="og:site_name"',
        '<meta name="twitter:card"',
        '<meta name="robots" content="<?php echo htmlspecialchars($pageRobots',
    );

    foreach ($required as $fragment)
    {
        assert_true(strpos($header, $fragment) !== false, 'header.php is missing: ' . $fragment);
    }
});

/**
 * The main-site pages that must stay out of search results.
 *
 * @return array<int, string>
 */
function _g2mlSiteHeadTestNoindexPages(): array
{
    return array(
        'login', 'register', 'forgot-password', 'reset-password', 'verify-email',
        'invite', 'logout', 'info', 'errors/400', 'errors/403', 'errors/404', 'errors/500',
    );
}

/**
 * Run web/_includes/header.php in a clean child process, as a front
 * controller would after running a page, and return the og:url and the
 * canonical address it printed ('' when it printed none).
 *
 * @param  array<string, mixed> $setup  domain, component, uri, status, and
 *                                      optionally resolved, robots, urlPath
 * @return array{ogURL: string, canonical: string}|null  Null if the child failed
 */
function _g2mlSiteHeadTestRenderHead(array $setup): ?array
{
    $script  = '<?php' . "\n" . 'declare(strict_types=1);' . "\n";
    $script .= 'define(\'G2ML_COMPONENT_DOMAIN\', ' . var_export($setup['domain'], true) . ');' . "\n";
    $script .= 'define(\'G2ML_COMPONENT\', ' . var_export($setup['component'], true) . ');' . "\n";
    $script .= 'function g2ml_getComponent(): string' . "\n" . '{' . "\n" . '    return G2ML_COMPONENT;' . "\n" . '}' . "\n";
    $script .= 'function skipToContent(): string' . "\n" . '{' . "\n" . '    return \'\';' . "\n" . '}' . "\n";
    $script .= '$_SERVER[\'REQUEST_URI\'] = ' . var_export($setup['uri'], true) . ';' . "\n";

    if (isset($setup['resolved']))
    {
        $script .= '$resolved = ' . var_export($setup['resolved'], true) . ';' . "\n";
    }

    if (isset($setup['robots']))
    {
        $script .= '$pageRobots = ' . var_export($setup['robots'], true) . ';' . "\n";
    }

    if (isset($setup['urlPath']))
    {
        $script .= '$pageURLPath = ' . var_export($setup['urlPath'], true) . ';' . "\n";
    }

    $script .= 'http_response_code(' . (int) $setup['status'] . ');' . "\n";
    $script .= 'ob_start();' . "\n";
    $script .= 'require ' . var_export(_g2mlSiteHeadTestPath('web/_includes/header.php'), true) . ';' . "\n";
    $script .= 'echo ob_get_clean();' . "\n";

    $tempFile = tempnam(sys_get_temp_dir(), 'g2ml_site_head_');

    if ($tempFile === false)
    {
        return null;
    }

    file_put_contents($tempFile, $script);
    $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tempFile) . ' 2>/dev/null');
    unlink($tempFile);

    if (!is_string($output) || strpos($output, '<meta property="og:url"') === false)
    {
        return null;
    }

    $ogURL     = array();
    $canonical = array();
    preg_match('/<meta property="og:url" content="([^"]*)"/', $output, $ogURL);
    preg_match('/<link rel="canonical" href="([^"]*)"/', $output, $canonical);

    $result = array('ogURL' => '', 'canonical' => '');

    if (isset($ogURL[1]))
    {
        $result['ogURL'] = html_entity_decode($ogURL[1], ENT_QUOTES, 'UTF-8');
    }

    if (isset($canonical[1]))
    {
        $result['canonical'] = html_entity_decode($canonical[1], ENT_QUOTES, 'UTF-8');
    }

    return $result;
}

test('site head: canonical and og:url name the page actually shown, and only indexable pages print a canonical (#282)', function (): void
{
    $main  = array('domain' => 'go2my.link', 'component' => 'A', 'status' => 200);
    $cases = array(
        'the home page at /' => array_merge($main, array(
            'uri' => '/', 'resolved' => array('file' => 'home.php', 'segments' => array()),
            'expectOG' => 'https://go2my.link/', 'expectCanonical' => 'https://go2my.link/',
        )),
        'the home page at /home' => array_merge($main, array(
            'uri' => '/home', 'resolved' => array('file' => 'home.php', 'segments' => array('home')),
            'expectOG' => 'https://go2my.link/', 'expectCanonical' => 'https://go2my.link/',
        )),
        'About through /index.php?route=about' => array_merge($main, array(
            'uri' => '/index.php?route=about', 'resolved' => array('file' => 'about/index.php', 'segments' => array('about')),
            'expectOG' => 'https://go2my.link/about', 'expectCanonical' => 'https://go2my.link/about',
        )),
        'a noindex page (login)' => array_merge($main, array(
            'uri' => '/login', 'resolved' => array('file' => 'login/index.php', 'segments' => array('login')),
            'robots' => 'noindex, nofollow',
            'expectOG' => 'https://go2my.link/login', 'expectCanonical' => '',
        )),
        'an info result page that names its own path' => array_merge($main, array(
            'uri' => '/info/abc', 'resolved' => array('file' => 'info/index.php', 'segments' => array('info')),
            'robots' => 'noindex, nofollow', 'urlPath' => '/info/abc',
            'expectOG' => 'https://go2my.link/info/abc', 'expectCanonical' => '',
        )),
        'a page path without a leading slash is ignored' => array_merge($main, array(
            'uri' => '/about', 'resolved' => array('file' => 'about/index.php', 'segments' => array('about')),
            'urlPath' => 'evil.example/x',
            'expectOG' => 'https://go2my.link/about', 'expectCanonical' => 'https://go2my.link/about',
        )),
        'a page that was not found' => array(
            'domain' => 'go2my.link', 'component' => 'A', 'status' => 404,
            'uri' => '/nope', 'resolved' => array('file' => null, 'segments' => array()),
            'robots' => 'noindex, nofollow',
            'expectOG' => 'https://go2my.link/nope', 'expectCanonical' => '',
        ),
        'a 404 that does not itself say noindex (the built-in fallback)' => array(
            'domain' => 'go2my.link', 'component' => 'A', 'status' => 404,
            'uri' => '/gone', 'resolved' => array('file' => null, 'segments' => array()),
            'expectOG' => 'https://go2my.link/gone', 'expectCanonical' => '',
        ),
        'a dashboard page (always noindex)' => array(
            'domain' => 'admin.go2my.link', 'component' => 'Admin', 'status' => 200,
            'uri' => '/links', 'resolved' => array('file' => 'links/index.php', 'segments' => array('links')),
            'expectOG' => 'https://admin.go2my.link/links', 'expectCanonical' => '',
        ),
    );

    foreach ($cases as $label => $case)
    {
        $head = _g2mlSiteHeadTestRenderHead($case);

        assert_true($head !== null, $label . ': header.php did not run in the child process');
        assert_same($case['expectOG'], $head['ogURL'], $label . ': og:url');
        assert_same($case['expectCanonical'], $head['canonical'], $label . ': canonical');
    }
});

test('site head: the info page names /info/CODE as its own address (#282)', function (): void
{
    $source = (string) file_get_contents(_g2mlSiteHeadTestPath('web/Go2My.Link/public_html/pages/info/index.php'));

    assert_true(strpos($source, "\$pageURLPath = '/info/' . \$shortCode;") !== false, 'pages/info/index.php should set $pageURLPath to /info/CODE');
});

test('site head: private and error pages are marked noindex (#282)', function (): void
{
    foreach (_g2mlSiteHeadTestNoindexPages() as $page)
    {
        $source = (string) file_get_contents(_g2mlSiteHeadTestPath('web/Go2My.Link/public_html/pages/' . $page . '/index.php'));
        assert_true(strpos($source, "\$pageRobots = 'noindex, nofollow';") !== false, $page . ' should set $pageRobots to noindex');
    }
});

test('site head: robots.txt does not block the pages that say noindex (#282)', function (): void
{
    $robots   = (string) file_get_contents(_g2mlSiteHeadTestPath('web/Go2My.Link/public_html/robots.txt'));
    $problems = array();

    foreach (preg_split('/\R/', $robots) as $line)
    {
        $matches = array();

        if (preg_match('#^\s*Disallow:\s*(/\S*)#i', $line, $matches) !== 1)
        {
            continue;
        }

        // Each page is tried as itself and with something after it, so a
        // rule such as "Disallow: /info/" that hides every /info/CODE result
        // page is caught too.
        foreach (_g2mlSiteHeadTestNoindexPages() as $page)
        {
            foreach (array('/' . $page, '/' . $page . '/abc') as $samplePath)
            {
                if (strncmp($samplePath, $matches[1], strlen($matches[1])) === 0)
                {
                    $problems[] = $matches[1] . ' blocks ' . $samplePath;
                }
            }
        }
    }

    assert_same(0, count($problems), 'robots.txt hides these "noindex" pages from search engines: ' . implode(', ', $problems));
});

test('site head: favicon, touch icon and share image files exist where the head points (#282)', function (): void
{
    $roots = array('web/Go2My.Link/public_html', 'web/Go2My.Link/_admin/public_html');

    foreach ($roots as $root)
    {
        foreach (array('favicon.ico', 'favicon.png', 'icons/apple-touch-icon.png') as $file)
        {
            assert_true(is_file(_g2mlSiteHeadTestPath($root . '/' . $file)), $root . '/' . $file . ' is missing');
        }
    }

    $touchIconPath = _g2mlSiteHeadTestPath('web/Go2My.Link/public_html/icons/apple-touch-icon.png');
    $touchIcon     = getimagesize($touchIconPath);
    assert_true($touchIcon !== false && $touchIcon[0] === 180 && $touchIcon[1] === 180, 'The touch icon must be 180×180');

    // iPhones and iPads fill transparent pixels of a home-screen icon with
    // black. A PNG has no transparency when its colour type (byte 25, in the
    // IHDR header) is 0 (grey) or 2 (colour) and it has no tRNS block.
    $touchIconBytes = (string) file_get_contents($touchIconPath);
    $colourType     = ord(substr($touchIconBytes, 25, 1));
    assert_true($colourType === 0 || $colourType === 2, 'The touch icon must have no alpha channel (PNG colour type ' . $colourType . ')');
    assert_true(strpos($touchIconBytes, 'tRNS') === false, 'The touch icon must have no transparent colour (tRNS)');
    assert_same(sha1($touchIconBytes), sha1_file(_g2mlSiteHeadTestPath('web/Go2My.Link/_admin/public_html/icons/apple-touch-icon.png')), 'The dashboard touch icon should be the same file');

    $shareImage = getimagesize(_g2mlSiteHeadTestPath('web/Go2My.Link/public_html/img/og-image.png'));
    assert_true($shareImage !== false && $shareImage[0] === 1200 && $shareImage[1] === 630, 'The share image must be 1200×630');

    assert_true(is_file(_g2mlSiteHeadTestPath('web/G2My.Link/public_html/favicon_default.ico')), 'g2my.link needs favicon_default.ico, or favicon.php serves the 262 KB logo');
});

test('site head: each sitemap is valid, every go2my.link address has a page, robots.txt points at it (#282)', function (): void
{
    $schema = _g2mlSiteHeadTestPath('web/_schemas/external/sitemap-0.9.xsd');
    $sitemaps = array(
        'web/Go2My.Link/public_html/sitemap.xml',
        'web/Lnks.page/public_html/sitemap.xml',
    );

    foreach ($sitemaps as $sitemap)
    {
        $document = new DOMDocument();
        assert_true($document->load(_g2mlSiteHeadTestPath($sitemap)), $sitemap . ' is not well-formed XML');

        $previous = libxml_use_internal_errors(true);
        $valid    = $document->schemaValidate($schema);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        assert_true($valid, $sitemap . ' does not match sitemap-0.9.xsd');
    }

    // The schema must refuse an entry the official schema refuses: elements
    // out of order, a date that is not a date, an address under 12 characters.
    $bad = new DOMDocument();
    $bad->loadXML('<?xml version="1.0" encoding="UTF-8"?>'
        . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
        . '<url><priority>0.5</priority><lastmod>last tuesday</lastmod><loc>x</loc></url>'
        . '</urlset>');
    $badEntries = array(
        '<url><loc>https://go2my.link/</loc><lastmod>last tuesday</lastmod></url>',
        '<url><loc>https://a.b</loc></url>',
        '<url><lastmod>2026-10-05</lastmod><loc>https://go2my.link/</loc></url>',
    );
    $previous = libxml_use_internal_errors(true);
    $refused  = ($bad->schemaValidate($schema) === false);

    foreach ($badEntries as $entry)
    {
        $document = new DOMDocument();
        $document->loadXML('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . $entry . '</urlset>');

        if ($document->schemaValidate($schema) !== false)
        {
            $refused = false;
        }
    }

    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    assert_true($refused, 'sitemap-0.9.xsd accepted an entry the official schema refuses');

    $pages    = _g2mlSiteHeadTestPath('web/Go2My.Link/public_html/pages');
    $root     = _g2mlSiteHeadTestPath('web/Go2My.Link/public_html');
    $sitemap  = (string) file_get_contents(_g2mlSiteHeadTestPath('web/Go2My.Link/public_html/sitemap.xml'));
    $matches  = array();
    $problems = array();
    preg_match_all('#<loc>https://go2my\.link([^<]*)</loc>#', $sitemap, $matches);

    foreach ($matches[1] as $path)
    {
        $route = trim($path, '/');
        $found = false;

        if ($route === '' && is_file($pages . '/home.php'))
        {
            $found = true;
        }
        elseif ($route !== '' && (is_file($pages . '/' . $route . '/index.php') || is_file($pages . '/' . $route . '.php')))
        {
            $found = true;
        }
        elseif ($route !== '' && is_file($root . '/' . $route . '/index.php'))
        {
            $found = true;
        }

        if (!$found)
        {
            $problems[] = $path;
        }
    }

    assert_true(count($matches[1]) > 0, 'The go2my.link sitemap lists no addresses');
    assert_same(0, count($problems), 'Sitemap addresses with no page: ' . implode(', ', $problems));

    $robots = (string) file_get_contents(_g2mlSiteHeadTestPath('web/Go2My.Link/public_html/robots.txt'));
    assert_true(strpos($robots, 'Sitemap: https://go2my.link/sitemap.xml') !== false, 'robots.txt must point at the sitemap');
});
