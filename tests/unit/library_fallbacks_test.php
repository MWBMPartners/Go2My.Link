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
 * 🧪 Check — no jQuery, and library fallbacks that can really load (#285)
 * ============================================================================
 *
 * Two problems are guarded here.
 *
 * 1. jQuery (87 KB) was loaded on every page and nothing used it. It is gone:
 *    from the shared header and footer, from the Content-Security-Policy of
 *    the main site and the dashboard, and from the shared library folder.
 *
 * 2. The "local copy if the CDN fails" fallbacks pointed at /_libraries/...,
 *    which .htaccess blocks on purpose and which sits outside every web root,
 *    so they could never work; Font Awesome had no fallback at all. The
 *    fallbacks now point at a /vendor/ folder inside each web root that needs
 *    one. This check fails if:
 *      - the header or footer points a fallback at /_libraries/ again, or a
 *        CDN stylesheet or script has no fallback;
 *      - a fallback file is missing from a web root, or differs by even one
 *        byte from the master copy in web/_libraries/;
 *      - a copy no longer matches the integrity hash printed next to the CDN
 *        address (that hash is how the browser proves the CDN file is the
 *        right one, so a copy with the same hash is the same file);
 *      - a page uses a Font Awesome style whose font file is not copied;
 *      - a fallback adds its copy at the end of <head> instead of in the
 *        failed link's place (after /css/style.css, Bootstrap would undo the
 *        site's own colours);
 *      - the Content-Security-Policy would stop the browser loading the copy.
 *
 * WHAT THIS CHECK CANNOT DO
 *
 * It reads files; it does not load a page in a browser, so it cannot show that
 * the browser really switches to the copy when a CDN is down.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      #285
 * ============================================================================
 */

declare(strict_types=1);

// ============================================================================
// 🧰 Helpers
// ============================================================================

/**
 * The repository root folder.
 *
 * @return string
 */
function _g2mlLibFallbackRoot(): string
{
    return dirname(__DIR__, 2);
}

/**
 * Read a file from the repository as text.
 *
 * @param  string $relative  Path from the repository root, with '/' separators.
 * @return string
 */
function _g2mlLibFallbackRead(string $relative): string
{
    $full = _g2mlLibFallbackRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

    assert_true(is_file($full), 'Expected file not found: ' . $relative);

    $text = file_get_contents($full);

    assert_true($text !== false, 'Could not read the file: ' . $relative);

    return $text;
}

/**
 * A PHP file's text with its PHP comments removed, so a note that explains
 * what used to be there (for example, that jQuery was removed) is not mistaken
 * for a use of it. Text that is printed to the browser is kept as it is.
 *
 * @param  string $source
 * @return string
 */
function _g2mlLibFallbackWithoutPhpComments(string $source): string
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

            $kept = $kept . $token[1];
        }
        else
        {
            $kept = $kept . $token;
        }
    }

    return $kept;
}

/**
 * A JavaScript file's text with its comments removed (a rough pass, good
 * enough to ask whether the code itself uses a name).
 *
 * @param  string $source
 * @return string
 */
function _g2mlLibFallbackWithoutJsComments(string $source): string
{
    $withoutBlocks = (string) preg_replace('~/\*.*?\*/~s', '', $source);

    return (string) preg_replace('~(?<![:\w])//[^\n]*~', '', $withoutBlocks);
}

/**
 * Every web root that must carry its own /vendor/ folder, with the library
 * files each one needs. The master copies live in web/_libraries/.
 *
 * @return array<string, array<int, string>>
 */
function _g2mlLibFallbackRoots(): array
{
    $pageFiles = array(
        'bootstrap/css/bootstrap.min.css',
        'fontawesome/css/all.min.css',
        'fontawesome/webfonts/fa-solid-900.woff2',
    );

    $sharedLayoutFiles = array(
        'bootstrap/css/bootstrap.min.css',
        'bootstrap/css/bootstrap.rtl.min.css',
        'bootstrap/js/bootstrap.bundle.min.js',
        'fontawesome/css/all.min.css',
        'fontawesome/webfonts/fa-solid-900.woff2',
    );

    return array(
        // The main site and the dashboard use the shared header and footer.
        'web/Go2My.Link/public_html'        => $sharedLayoutFiles,
        'web/Go2My.Link/_admin/public_html' => $sharedLayoutFiles,
        // g2my.link's own pages (403, 404, 500, expired, validating) load
        // Bootstrap's and Font Awesome's style sheets themselves.
        'web/G2My.Link/public_html'         => $pageFiles,
    );
}

/**
 * The attributes of every <link> and <script> tag in $html that names an
 * https address, as array(tag text, attribute map).
 *
 * @param  string $html
 * @return array<int, array{0: string, 1: array<string, string>}>
 */
function _g2mlLibFallbackCdnTags(string $html): array
{
    $found = array();
    $tags  = array();

    preg_match_all('/<(?:link|script)\b[^>]*>/is', $html, $tags);

    foreach ($tags[0] as $tag)
    {
        $attributes = array();
        $pairs      = array();

        preg_match_all('/([a-zA-Z-]+)="([^"]*)"/', $tag, $pairs, PREG_SET_ORDER);

        foreach ($pairs as $pair)
        {
            $attributes[strtolower($pair[1])] = $pair[2];
        }

        $address = '';

        if (isset($attributes['href']))
        {
            $address = $attributes['href'];
        }
        elseif (isset($attributes['src']))
        {
            $address = $attributes['src'];
        }

        if (strncmp($address, 'https://', 8) === 0)
        {
            $found[] = array($tag, $attributes);
        }
    }

    return $found;
}

/**
 * The value of one directive of a Content-Security-Policy header line in an
 * .htaccess file, as a list of its sources.
 *
 * @param  string $htaccess   The file's text.
 * @param  string $directive  For example "font-src".
 * @return array<int, string>
 */
function _g2mlLibFallbackCspSources(string $htaccess, string $directive): array
{
    $line = array();

    if (preg_match('/^\s*Header (?:set|setifempty) Content-Security-Policy "([^"]*)"/m', $htaccess, $line) !== 1)
    {
        return array();
    }

    foreach (explode(';', $line[1]) as $part)
    {
        $words = preg_split('/\s+/', trim($part));

        if (is_array($words) && count($words) > 0 && $words[0] === $directive)
        {
            return array_slice($words, 1);
        }
    }

    return array();
}

// ============================================================================
// 🗑️ jQuery is gone
// ============================================================================

/**
 * Check one onerror fallback handler: the copy it loads must exist in every
 * web root that serves the page, and it must be inserted where the failed
 * CDN link was. Added at the end of <head> instead, Bootstrap would load
 * after /css/style.css and undo the site's own colours (#285 review).
 *
 * @param  string             $handler  The onerror attribute's code.
 * @param  array<int, string> $roots    Web roots (from the repository root).
 * @param  string             $label    Where the handler is, for messages.
 * @return void
 */
function _g2mlLibFallbackCheckHandler(string $handler, array $roots, string $label): void
{
    $matches = array();

    assert_true(preg_match("~l\\.href='(/vendor/[^']+)'~", $handler, $matches) === 1, $label . ': cannot find the fallback address in ' . $handler);

    foreach ($roots as $root)
    {
        assert_true(is_file(_g2mlLibFallbackRoot() . '/' . $root . $matches[1]), $label . ': the fallback ' . $matches[1] . ' does not exist in ' . $root);
    }

    assert_contains('this.parentNode.insertBefore(l, this.nextSibling);', $handler, $label . ': the fallback must take the failed link\'s place, not go to the end of <head>');
    assert_false(strpos($handler, 'document.head.appendChild') !== false, $label . ': the fallback must not be added at the end of <head>');
}

test('jQuery: the shared header and footer do not load it, and its folder is gone (#285)', function (): void
{
    foreach (array('web/_includes/header.php', 'web/_includes/footer.php') as $template)
    {
        $source = _g2mlLibFallbackWithoutPhpComments(_g2mlLibFallbackRead($template));

        assert_false(stripos($source, 'jquery') !== false, $template . ' still loads or checks for jQuery');
    }

    assert_false(is_dir(_g2mlLibFallbackRoot() . '/web/_libraries/jquery'), 'web/_libraries/jquery should have been deleted');
});

test('jQuery: no site script uses it, and no Content-Security-Policy names its host (#285)', function (): void
{
    foreach (array('web/Go2My.Link/public_html/js', 'web/Go2My.Link/_admin/public_html/js') as $folder)
    {
        $files = glob(_g2mlLibFallbackRoot() . '/' . $folder . '/*.js');

        assert_true(is_array($files) && count($files) > 0, 'No scripts found in ' . $folder);

        foreach ($files as $file)
        {
            $text = _g2mlLibFallbackWithoutJsComments((string) file_get_contents($file));

            assert_false(preg_match('/\bjQuery\b|\$\(\s*[\'"#.]|\$\.(?:ajax|get|post|getJSON)\b/', $text) === 1, basename($file) . ' in ' . $folder . ' appears to use jQuery');
        }
    }

    foreach (glob(_g2mlLibFallbackRoot() . '/web/*/public_html*/.htaccess') as $htaccess)
    {
        assert_false(stripos((string) file_get_contents($htaccess), 'jquery') !== false, $htaccess . ' still names jQuery');
    }

    assert_false(stripos(_g2mlLibFallbackRead('web/Go2My.Link/_admin/public_html/.htaccess'), 'code.jquery.com') !== false, 'The dashboard policy still allows code.jquery.com');
});

// ============================================================================
// 🔁 Every CDN file has a fallback that points at a real, served folder
// ============================================================================

test('fallbacks: the shared header and footer never point a fallback at /_libraries/, and every CDN file has one (#285)', function (): void
{
    // The comments may say what the fallbacks used to point at; only what is printed counts.
    $header = _g2mlLibFallbackWithoutPhpComments(_g2mlLibFallbackRead('web/_includes/header.php'));
    $footer = _g2mlLibFallbackWithoutPhpComments(_g2mlLibFallbackRead('web/_includes/footer.php'));

    assert_false(strpos($header, '/_libraries/') !== false, 'header.php points a fallback at /_libraries/, which .htaccess blocks');
    assert_false(strpos($footer, '/_libraries/') !== false, 'footer.php points a fallback at /_libraries/, which .htaccess blocks');

    // Every CDN <link> must carry an onerror fallback that loads /vendor/...
    $cdnLinks = 0;

    foreach (_g2mlLibFallbackCdnTags($header) as $found)
    {
        $attributes = $found[1];

        assert_true(isset($attributes['onerror']), 'A CDN tag in header.php has no onerror fallback: ' . $attributes['href']);
        assert_contains("l.href='/vendor/", $attributes['onerror'], 'The fallback does not load a copy from /vendor/: ' . $attributes['href']);
        _g2mlLibFallbackCheckHandler($attributes['onerror'], array('web/Go2My.Link/public_html', 'web/Go2My.Link/_admin/public_html'), 'header.php');

        $cdnLinks = $cdnLinks + 1;
    }

    // Bootstrap CSS, Font Awesome CSS and (right-to-left only) Bootstrap RTL CSS.
    assert_same(3, $cdnLinks, 'Expected three CDN stylesheets in header.php');

    // The Bootstrap script's fallback is written by the script that follows it.
    assert_contains("document.write('<script src=\"/vendor/bootstrap/js/bootstrap.bundle.min.js\">", $footer);
    assert_contains("if (typeof bootstrap === 'undefined')", $footer);
});

test('fallbacks: g2my.link\'s own pages give each CDN style sheet a fallback in /vendor/ (#285)', function (): void
{
    foreach (array('403.php', '404.php', '500.php', 'expired.php', 'validating.php') as $page)
    {
        $html  = _g2mlLibFallbackRead('web/G2My.Link/public_html/' . $page);
        $links = _g2mlLibFallbackCdnTags($html);

        assert_same(2, count($links), $page . ' should load exactly Bootstrap CSS and Font Awesome CSS from CDNs');

        foreach ($links as $found)
        {
            $attributes = $found[1];

            assert_true(isset($attributes['onerror']), $page . ' has a CDN style sheet with no fallback: ' . $attributes['href']);
            assert_contains("l.href='/vendor/", $attributes['onerror'], $page . ' fallback does not load from /vendor/');
            _g2mlLibFallbackCheckHandler($attributes['onerror'], array('web/G2My.Link/public_html'), $page);
        }
    }
});

foreach (_g2mlLibFallbackRoots() as $g2mlLibFallbackRootPath => $g2mlLibFallbackFiles)
{
    test('fallbacks: ' . $g2mlLibFallbackRootPath . '/vendor holds every file it needs, identical to the master copy (#285)', function () use ($g2mlLibFallbackRootPath, $g2mlLibFallbackFiles): void
    {
        foreach ($g2mlLibFallbackFiles as $libraryFile)
        {
            $master = _g2mlLibFallbackRoot() . '/web/_libraries/' . $libraryFile;
            $copy   = _g2mlLibFallbackRoot() . '/' . $g2mlLibFallbackRootPath . '/vendor/' . $libraryFile;

            assert_true(is_file($master), 'The master copy is missing: web/_libraries/' . $libraryFile);
            assert_true(is_file($copy), 'The fallback copy is missing: ' . $g2mlLibFallbackRootPath . '/vendor/' . $libraryFile);

            assert_true(
                file_get_contents($master) === file_get_contents($copy),
                $g2mlLibFallbackRootPath . '/vendor/' . $libraryFile . ' differs from web/_libraries/' . $libraryFile . '. Change the master, then copy it over every web root.'
            );
        }
    });

    test('fallbacks: ' . $g2mlLibFallbackRootPath . '/vendor holds nothing beyond what its pages load (#285)', function () use ($g2mlLibFallbackRootPath, $g2mlLibFallbackFiles): void
    {
        $vendorRoot = _g2mlLibFallbackRoot() . '/' . $g2mlLibFallbackRootPath . '/vendor';
        $iterator   = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($vendorRoot, FilesystemIterator::SKIP_DOTS));
        $present    = array();

        foreach ($iterator as $fileInfo)
        {
            if ($fileInfo->isFile())
            {
                $present[] = substr(str_replace(DIRECTORY_SEPARATOR, '/', $fileInfo->getPathname()), strlen($vendorRoot) + 1);
            }
        }

        sort($present);

        $expected = $g2mlLibFallbackFiles;
        sort($expected);

        assert_same($expected, $present, 'The files in ' . $g2mlLibFallbackRootPath . '/vendor are not the list this check expects');
    });
}

// ============================================================================
// 🔏 A copy must be the same file the CDN serves
// ============================================================================

test('fallbacks: each copy matches the integrity hash printed beside its CDN address (#285)', function (): void
{
    $pages = array(
        'web/_includes/header.php',
        'web/_includes/footer.php',
        'web/G2My.Link/public_html/404.php',
    );

    // Which copy belongs to which CDN address.
    $copyForAddress = array(
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css'        => 'bootstrap/css/bootstrap.min.css',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css'    => 'bootstrap/css/bootstrap.rtl.min.css',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'   => 'bootstrap/js/bootstrap.bundle.min.js',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css'      => 'fontawesome/css/all.min.css',
    );

    $checked = array();

    foreach ($pages as $page)
    {
        foreach (_g2mlLibFallbackCdnTags(_g2mlLibFallbackRead($page)) as $found)
        {
            $attributes = $found[1];
            $address    = '';

            if (isset($attributes['href']))
            {
                $address = $attributes['href'];
            }
            elseif (isset($attributes['src']))
            {
                $address = $attributes['src'];
            }

            if (!isset($copyForAddress[$address]))
            {
                continue;
            }

            assert_true(isset($attributes['integrity']), $page . ' names ' . $address . ' with no integrity hash');

            $parts = explode('-', $attributes['integrity'], 2);

            assert_true(in_array($parts[0], array('sha256', 'sha384', 'sha512'), true), 'Unexpected hash type in ' . $page);

            $localFile = _g2mlLibFallbackRoot() . '/web/_libraries/' . $copyForAddress[$address];
            $hash      = base64_encode(hash_file($parts[0], $localFile, true));

            assert_same($parts[1], $hash, 'web/_libraries/' . $copyForAddress[$address] . ' is not the file the CDN serves (hash from ' . $page . ')');

            $checked[$address] = true;
        }
    }

    assert_same(count($copyForAddress), count($checked), 'Not every CDN address was matched to a local copy');
});

// ============================================================================
// 🔤 Font Awesome: the font file for every style a page uses is copied
// ============================================================================

test('fallbacks: every Font Awesome style the site uses has its font file copied (#285)', function (): void
{
    // Only the "solid" font is copied, because pages use only "fas". If a page
    // starts using the regular or brands style, copy that font file as well
    // (web/_libraries/fontawesome/webfonts/), add it to the list at the top of
    // this file, and then this check passes again.
    $styleFont = array(
        'regular' => 'fa-regular-400.woff2',
        'brands'  => 'fa-brands-400.woff2',
    );

    $needed  = array();
    $webRoot = _g2mlLibFallbackRoot() . '/web';
    $files   = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($webRoot, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $fileInfo)
    {
        $path = str_replace(DIRECTORY_SEPARATOR, '/', $fileInfo->getPathname());

        if (strpos($path, '/_libraries/') !== false || strpos($path, '/vendor/') !== false || strpos($path, '/assets/') !== false
            || strpos($path, '/_backups/') !== false || strpos($path, '/_uploads/') !== false)
        {
            continue;
        }

        $extension = strtolower($fileInfo->getExtension());

        if ($extension !== 'php' && $extension !== 'js')
        {
            continue;
        }

        $text = (string) file_get_contents($path);

        if (preg_match('/\bfar fa-|\bfa-regular\b/', $text) === 1)
        {
            $needed['regular'] = $path;
        }

        if (preg_match('/\bfab fa-|\bfa-brands\b/', $text) === 1)
        {
            $needed['brands'] = $path;
        }
    }

    foreach ($needed as $style => $firstFile)
    {
        foreach (_g2mlLibFallbackRoots() as $rootPath => $copied)
        {
            assert_true(
                in_array('fontawesome/webfonts/' . $styleFont[$style], $copied, true),
                'A Font Awesome "' . $style . '" icon is used (for example in ' . $firstFile . '), but its font file is not copied to ' . $rootPath . '/vendor'
            );
        }
    }

    // The copied style sheet must name the solid font file in the folder next to it.
    $css = _g2mlLibFallbackRead('web/Go2My.Link/public_html/vendor/fontawesome/css/all.min.css');

    assert_contains('url(../webfonts/fa-solid-900.woff2)', $css);
});

// ============================================================================
// 🛡️ The Content-Security-Policy lets the browser load the copies
// ============================================================================

test('fallbacks: the main site, the dashboard and g2my.link each allow their own scripts, styles and fonts (#285)', function (): void
{
    $files = array(
        'web/Go2My.Link/public_html/.htaccess'        => array('script-src', 'style-src', 'font-src'),
        'web/Go2My.Link/_admin/public_html/.htaccess' => array('script-src', 'style-src', 'font-src'),
        // g2my.link loads no script from /vendor/, only styles and the font file.
        'web/G2My.Link/public_html/.htaccess'         => array('style-src', 'font-src'),
    );

    foreach ($files as $file => $directives)
    {
        $htaccess = _g2mlLibFallbackRead($file);

        foreach ($directives as $directive)
        {
            $sources = _g2mlLibFallbackCspSources($htaccess, $directive);

            assert_true(in_array("'self'", $sources, true), $file . ' has no \'self\' in ' . $directive . ', so the copies in /vendor/ would be blocked');
        }
    }
});

test('fallbacks: .htaccess still blocks /_libraries/ (it is not the fallback folder) (#285)', function (): void
{
    foreach (array('web/Go2My.Link/public_html/.htaccess', 'web/Go2My.Link/_admin/public_html/.htaccess', 'web/G2My.Link/public_html/.htaccess') as $file)
    {
        assert_contains('RewriteRule ^_libraries(/|$) - [F,L]', _g2mlLibFallbackRead($file), $file . ' no longer blocks /_libraries/');
    }
});

test('fallbacks: "vendor" cannot be chosen as a short code, because g2my.link now has a /vendor/ folder (#285)', function (): void
{
    $source = _g2mlLibFallbackRead('web/Go2My.Link/_functions/shorturl_create.php');

    assert_contains("'vendor',", $source);
});
