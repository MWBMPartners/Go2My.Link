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
 * 🧪 Check — legal-page links lead somewhere real (#284)
 * ============================================================================
 *
 * The sweep of 2026-10-05 found a "Back to Legal" button pointing at /legal
 * (a 404 until the hub page was added), two links to anchors that did not
 * exist (#dnt, #cookie-preferences), and a hide switch that compared a yes/no
 * setting with the text '1' and so could never work.
 *
 * This check reads the main site's page files and fails if:
 *   - a link to /legal or /legal/<page> has no page file behind it;
 *   - a link to /legal/<page>#<anchor> names an id that page does not have;
 *   - a legal page compares legal.hide_review_placeholders with '1'.
 *
 * WHAT THIS CHECK CANNOT DO
 *
 * It reads links written literally as href="/legal...". A link built up in
 * code from separate pieces is not seen.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * ============================================================================
 */

declare(strict_types=1);

/**
 * Every href="/legal..." in the main site's page files and shared includes,
 * as [file, target] pairs.
 *
 * @return array<int, array{0: string, 1: string}>
 */
function _g2mlLegalLinksTestLinks(): array
{
    $repository = dirname(__DIR__, 2);
    $folders    = array(
        $repository . '/web/Go2My.Link/public_html/pages',
        $repository . '/web/_includes',
    );
    $links = array();

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

            $source  = (string) file_get_contents($path);
            $matches = array();
            preg_match_all('#href="(/legal[^"]*)"#', $source, $matches);

            foreach ($matches[1] as $target)
            {
                $links[] = array(substr($path, strlen($repository) + 1), $target);
            }
        }
    }

    return $links;
}

test('legal links: every /legal link has a page file, and every #anchor exists on its page (#284)', function (): void
{
    $pagesFolder = dirname(__DIR__, 2) . '/web/Go2My.Link/public_html/pages';
    $problems    = array();

    foreach (_g2mlLegalLinksTestLinks() as $link)
    {
        $target = $link[1];
        $anchor = '';
        $hash   = strpos($target, '#');

        if ($hash !== false)
        {
            $anchor = substr($target, $hash + 1);
            $target = substr($target, 0, $hash);
        }

        $pageFile = $pagesFolder . rtrim($target, '/') . '/index.php';

        if (!is_file($pageFile))
        {
            $problems[] = $link[0] . ' links to ' . $link[1] . ', which has no page';
            continue;
        }

        if ($anchor !== '' && strpos((string) file_get_contents($pageFile), 'id="' . $anchor . '"') === false)
        {
            $problems[] = $link[0] . ' links to ' . $link[1] . ', but that page has no id="' . $anchor . '"';
        }
    }

    assert_same(0, count($problems), implode(' | ', $problems));
});

test('legal links: no legal page compares the hide switch with the text \'1\' (#284)', function (): void
{
    $pagesFolder = dirname(__DIR__, 2) . '/web/Go2My.Link/public_html/pages/legal';
    $problems    = array();

    foreach (glob($pagesFolder . '/*/index.php') as $pageFile)
    {
        $source = (string) file_get_contents($pageFile);

        if (preg_match("/hide_review_placeholders'[^;]*===\\s*'1'/", $source) === 1)
        {
            $problems[] = basename(dirname($pageFile));
        }
    }

    assert_same(0, count($problems), 'These legal pages compare the switch with \'1\': ' . implode(', ', $problems));
});
