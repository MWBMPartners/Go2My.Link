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
 * 🧪 Check — a properly sized logo, and nothing heavy or unfinished in a web root (#285)
 * ============================================================================
 *
 * The logo was a 350 KB SVG file that was really a PNG photo wrapped in SVG
 * tags (with a 262 KB PNG behind it), shown 36 pixels high in the navigation
 * on every page, with copies in the dashboard and g2my.link web roots. It is
 * now a PNG made at the size it is shown (160 pixels wide, plus 320 for sharp
 * screens, and 640 for the larger coming-soon page), kept once, on go2my.link.
 *
 * The big originals stay in web/assets/BrandKit/ (the brand kit), which is not
 * a web root: they are the masters the small files are made from.
 *
 * This check fails if:
 *   - a sized logo file is missing, is not a PNG of the stated size, or grows
 *     past its byte limit;
 *   - the old logo.svg or logo.png comes back in the main site, dashboard or
 *     g2my.link web root;
 *   - the navigation, footer or coming-soon pages go back to the old file
 *     names, or lose a sensible width and height, or point at a file that does
 *     not exist;
 *   - any image in a web root is over 200 KB (the old logo files were 262 KB
 *     and 350 KB);
 *   - a design source file (a file named "...-source...", or a .psd, .ai,
 *     .sketch, .fig or .xcf file) sits in a web root; the Lnks.page favicon
 *     source was one, and is now in the brand kit.
 *
 * WHAT THIS CHECK CANNOT DO
 *
 * It reads files and image headers. It does not judge how the logo looks.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      #285
 * ============================================================================
 */

declare(strict_types=1);

/**
 * The repository root.
 *
 * @return string
 */
function _g2mlLogoTestRoot(): string
{
    return dirname(__DIR__, 2);
}

/**
 * Read a file from the repository as text.
 *
 * @param  string $relative
 * @return string
 */
function _g2mlLogoTestRead(string $relative): string
{
    $full = _g2mlLogoTestRoot() . '/' . $relative;

    assert_true(is_file($full), 'Expected file not found: ' . $relative);

    return (string) file_get_contents($full);
}

/**
 * Every web root folder: the public folders of the three sites and the dashboard.
 *
 * @return array<int, string>  Absolute paths.
 */
function _g2mlLogoTestWebRoots(): array
{
    $roots = array();

    foreach (glob(_g2mlLogoTestRoot() . '/web/*/public_html*', GLOB_ONLYDIR) as $folder)
    {
        $roots[] = $folder;
    }

    $roots[] = _g2mlLogoTestRoot() . '/web/Go2My.Link/_admin/public_html';

    return $roots;
}

/**
 * Every file under the web roots.
 *
 * @return array<int, string>  Absolute paths.
 */
function _g2mlLogoTestWebRootFiles(): array
{
    $files = array();

    foreach (_g2mlLogoTestWebRoots() as $root)
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $fileInfo)
        {
            if ($fileInfo->isFile())
            {
                $files[] = str_replace(DIRECTORY_SEPARATOR, '/', $fileInfo->getPathname());
            }
        }
    }

    sort($files);

    return $files;
}

// ============================================================================
// 🖼️ The sized logo files
// ============================================================================

test('logo: the sized PNG files exist, have the stated size and stay small (#285)', function (): void
{
    // file => array(width, height, byte limit)
    $sizes = array(
        'logo-160.png' => array(160, 36, 20000),
        'logo-320.png' => array(320, 72, 60000),
        'logo-640.png' => array(640, 144, 150000),
    );

    foreach ($sizes as $name => $expected)
    {
        $path = _g2mlLogoTestRoot() . '/web/Go2My.Link/public_html/img/' . $name;

        assert_true(is_file($path), 'Missing logo file: img/' . $name);

        $info = getimagesize($path);

        assert_true(is_array($info), $name . ' is not an image');
        assert_same(IMAGETYPE_PNG, $info[2], $name . ' is not a PNG');
        assert_same($expected[0], $info[0], $name . ' has the wrong width');
        assert_same($expected[1], $info[1], $name . ' has the wrong height');
        assert_true(filesize($path) <= $expected[2], $name . ' is ' . filesize($path) . ' bytes, over its limit of ' . $expected[2]);
    }
});

test('logo: the old 350 KB logo.svg and 262 KB logo.png are gone from the main site, dashboard and g2my.link (#285)', function (): void
{
    foreach (array('web/Go2My.Link/public_html', 'web/Go2My.Link/_admin/public_html', 'web/G2My.Link/public_html') as $root)
    {
        foreach (array('logo.svg', 'logo.png') as $name)
        {
            assert_false(is_file(_g2mlLogoTestRoot() . '/' . $root . '/img/' . $name), $root . '/img/' . $name . ' is back');
        }
    }

    // The masters the small files are made from are kept in the brand kit.
    assert_true(is_file(_g2mlLogoTestRoot() . '/web/assets/BrandKit/logos/png/Go2My.link-Logo-ORIGINAL.png'), 'The logo master is missing from the brand kit');
});

// ============================================================================
// 🧭 Where the logo is used
// ============================================================================

test('logo: the navigation, footer and coming-soon pages use the sized files, with real sizes (#285)', function (): void
{
    $pages = array(
        'web/_includes/nav.php'                          => array(160, 36),
        'web/_includes/footer.php'                       => array(142, 32),
        'web/Go2My.Link/public_html_landing/index.php'   => array(320, 72),
        'web/G2My.Link/public_html_landing/index.php'    => array(120, 27),
    );

    foreach ($pages as $page => $shown)
    {
        $source = _g2mlLogoTestRead($page);

        assert_false(strpos($source, 'img/logo.svg') !== false, $page . ' still points at the old logo.svg');
        assert_false(strpos($source, 'img/logo.png') !== false, $page . ' still points at the old logo.png');
        assert_false(strpos($source, '<picture>') !== false, $page . ' still wraps the logo in <picture>, which only chose the old SVG');

        // An <img> tag may hold a PHP tag (the alt text does), and the ">" that closes
        // that PHP tag must not be taken as the end of the <img> tag.
        $tags     = array();
        $logoTags = array();

        preg_match_all('/<img\b(?:[^>]|(?<=\?)>)*>/s', $source, $tags);

        foreach ($tags[0] as $candidate)
        {
            if (strpos($candidate, 'img/logo-') !== false)
            {
                $logoTags[] = $candidate;
            }
        }

        assert_same(1, count($logoTags), $page . ' should have exactly one logo <img>');

        $attributes = array();

        preg_match_all('/([a-z-]+)="([^"]*)"/', $logoTags[0], $attributes, PREG_SET_ORDER);

        $map = array();

        foreach ($attributes as $pair)
        {
            $map[$pair[1]] = $pair[2];
        }

        assert_same((string) $shown[0], $map['width'], $page . ' logo width');
        assert_same((string) $shown[1], $map['height'], $page . ' logo height');
        assert_true(isset($map['srcset']) && strpos($map['srcset'], ' 2x') !== false, $page . ' logo has no 2x file for sharp screens');
        assert_true(isset($map['alt']), $page . ' logo has no alt text');
    }
});

test('logo: every logo file the templates name exists on go2my.link (#285)', function (): void
{
    $names = array();

    foreach (array('web/_includes/nav.php', 'web/_includes/footer.php', 'web/Go2My.Link/public_html_landing/index.php', 'web/G2My.Link/public_html_landing/index.php') as $page)
    {
        $found = array();

        preg_match_all('~https://go2my\.link/img/(logo-\d+\.png)~', _g2mlLogoTestRead($page), $found);

        foreach ($found[1] as $name)
        {
            $names[$name] = true;
        }
    }

    assert_true(count($names) >= 3, 'Expected the pages to name the 160, 320 and 640 pixel logos');

    foreach (array_keys($names) as $name)
    {
        assert_true(is_file(_g2mlLogoTestRoot() . '/web/Go2My.Link/public_html/img/' . $name), 'A page names ' . $name . ', which does not exist');
    }
});

test('logo: the API documentation pages use the small favicon, not a logo file (#285)', function (): void
{
    foreach (array('web/Go2My.Link/public_html/api/docs/index.php', 'web/Go2My.Link/public_html/api/docs/swagger/index.php') as $page)
    {
        $source = _g2mlLogoTestRead($page);

        assert_false(strpos($source, '/img/logo') !== false, $page . ' still names a logo file');
        assert_contains('href="/favicon.png"', $source);
    }
});

// ============================================================================
// ⚖️ Nothing heavy or unfinished in a web root
// ============================================================================

test('web roots: no image is over 200 KB (#285)', function (): void
{
    $heavy = array();

    foreach (_g2mlLogoTestWebRootFiles() as $file)
    {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if (in_array($extension, array('png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico'), true) && filesize($file) > 200000)
        {
            $heavy[] = substr($file, strlen(_g2mlLogoTestRoot()) + 1) . ' (' . filesize($file) . ' bytes)';
        }
    }

    assert_same(array(), $heavy, 'These images are over 200 KB. Make a smaller one for the web root and keep the master in web/assets/BrandKit/:' . PHP_EOL . implode(PHP_EOL, $heavy));
});

test('web roots: no design source file sits in a web root, and the Lnks.page favicon source is in the brand kit (#285)', function (): void
{
    $sources = array();

    foreach (_g2mlLogoTestWebRootFiles() as $file)
    {
        $name      = strtolower(basename($file));
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        $isSourceName = preg_match('/[-_.]source[-_.]/', $name) === 1;
        $isDesignFile = in_array($extension, array('psd', 'ai', 'sketch', 'fig', 'xcf', 'afdesign'), true);

        if ($isSourceName || $isDesignFile)
        {
            $sources[] = substr($file, strlen(_g2mlLogoTestRoot()) + 1);
        }
    }

    assert_same(array(), $sources, 'Design source files belong in web/assets/BrandKit/, not in a web root:' . PHP_EOL . implode(PHP_EOL, $sources));

    assert_false(is_file(_g2mlLogoTestRoot() . '/web/Lnks.page/public_html/img/favicon-source.svg'), 'favicon-source.svg is back in the Lnks.page web root');
    assert_true(is_file(_g2mlLogoTestRoot() . '/web/assets/BrandKit/icons/favicon/Lnks.page-Favicon-SOURCE.svg'), 'The Lnks.page favicon source is missing from the brand kit');
});
