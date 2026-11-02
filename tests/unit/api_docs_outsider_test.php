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
 * 🧪 Check — the public API description reads right to an outsider, and the
 *            API documentation page's Content-Security-Policy stays narrow (#285)
 * ============================================================================
 *
 * 1. The OpenAPI file (web/Go2My.Link/public_html/api/openapi.yaml) is the
 *    public description of the API, read by people outside the company. It
 *    used to name the source files behind the API, "Component A", database
 *    table names, internal setting names, the hosting company and issue
 *    numbers. This check fails if any of those come back.
 *
 * 2. The Redoc documentation page draws a small "Redocly" logo that it loads
 *    from https://cdn.redoc.ly. The page's own Content-Security-Policy blocked
 *    it, which wrote an error to the browser console on every view. That
 *    policy now allows exactly that one host, for images only, and only for
 *    this page. This check fails if the host is dropped, if any other outside
 *    host appears in the policy, or if the allowance leaks into the site-wide
 *    policy or into the Swagger page's policy.
 *
 * WHAT THIS CHECK CANNOT DO
 *
 * It reads text. It does not check that the YAML is a valid OpenAPI document
 * (PHP here has no YAML reader; the file is checked with the OpenAPI
 * specification validator by hand, see the pull request), and it cannot judge
 * whether wording is clear, only that the named internal terms are absent.
 * It does not load the page in a browser.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      #285
 * ============================================================================
 */

declare(strict_types=1);

/**
 * Read a file from the repository.
 *
 * @param  string $relative
 * @return string
 */
function _g2mlApiDocsTestRead(string $relative): string
{
    $full = dirname(__DIR__, 2) . '/' . $relative;

    assert_true(is_file($full), 'Expected file not found: ' . $relative);

    return (string) file_get_contents($full);
}

/**
 * The sources listed for one directive of the Content-Security-Policy line in
 * an .htaccess file.
 *
 * @param  string $htaccess
 * @param  string $directive
 * @return array<int, string>
 */
function _g2mlApiDocsTestCsp(string $htaccess, string $directive): array
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

test('API description: it names no source file, "Component A", table, hosting company or issue number (#285)', function (): void
{
    $yaml = _g2mlApiDocsTestRead('web/Go2My.Link/public_html/api/openapi.yaml');

    // term => what it would give away
    $internal = array(
        '~\bweb/~'                       => 'a path inside the source tree',
        '~\b_functions\b|\b_includes\b~' => 'a source folder',
        '~\.php\b~'                      => 'a PHP file name',
        '~\bComponent [A-D]\b~'          => 'an internal component name',
        '~\btbl[A-Z]\w*~'                => 'a database table name',
        '~Dreamhost~i'                   => 'the hosting company',
        '~\bhash_equals\b~'              => 'a PHP function',
        '~\bPHP\b~'                      => 'the implementation language',
        '~\b(?:api|cuercode)\.[a-z_]+_[a-z_]+\b~' => 'an internal setting name',
        '~\(#[0-9]{2,4}\)~'              => 'an issue number',
        '~\brateLimitOverride\b|\burlUID\b|\blogAction\b|\blogStatus\b~' => 'an internal column name',
    );

    $problems = array();

    foreach (explode("\n", $yaml) as $number => $line)
    {
        foreach ($internal as $pattern => $meaning)
        {
            if (preg_match($pattern, $line) === 1)
            {
                $problems[] = 'line ' . ($number + 1) . ' gives away ' . $meaning . ': ' . trim($line);
            }
        }
    }

    assert_same(array(), $problems, 'openapi.yaml should read right to an outside reader:' . PHP_EOL . implode(PHP_EOL, $problems));
});

test('API description: the production server entry is named by its address only (#285)', function (): void
{
    $yaml = _g2mlApiDocsTestRead('web/Go2My.Link/public_html/api/openapi.yaml');

    assert_contains('description: Production (go2my.link)', $yaml);
});

test('Redoc page: its policy allows exactly one outside host, cdn.redoc.ly, for images only (#285)', function (): void
{
    $htaccess = _g2mlApiDocsTestRead('web/Go2My.Link/public_html/api/docs/.htaccess');

    assert_same(array("'self'", 'data:', 'https://cdn.redoc.ly'), _g2mlApiDocsTestCsp($htaccess, 'img-src'), 'The Redoc page\'s img-src is not exactly self, data: and cdn.redoc.ly');

    // Everything else stays as narrow as before: no outside host anywhere else.
    foreach (array('default-src', 'script-src', 'style-src', 'font-src', 'worker-src', 'child-src', 'connect-src', 'object-src', 'base-uri', 'frame-ancestors', 'form-action') as $directive)
    {
        $sources = _g2mlApiDocsTestCsp($htaccess, $directive);

        assert_true(count($sources) > 0, 'The Redoc page policy has no ' . $directive);

        foreach ($sources as $source)
        {
            assert_false(strpos($source, '://') !== false || strpos($source, '.') !== false, 'The Redoc page policy allows an outside host in ' . $directive . ': ' . $source);
        }
    }

    assert_same(array("'self'"), _g2mlApiDocsTestCsp($htaccess, 'script-src'), 'script-src must stay \'self\' only');
});

test('Redoc page: the allowance does not leak into the site-wide policy, the dashboard, or the Swagger page (#285)', function (): void
{
    foreach (array(
        'web/Go2My.Link/public_html/.htaccess',
        'web/Go2My.Link/_admin/public_html/.htaccess',
        'web/G2My.Link/public_html/.htaccess',
        'web/Lnks.page/public_html/.htaccess',
        'web/Go2My.Link/public_html/api/docs/swagger/.htaccess',
    ) as $file)
    {
        $csp = array();

        $text = _g2mlApiDocsTestRead($file);

        preg_match_all('/^\s*Header (?:set|setifempty) Content-Security-Policy "[^"]*"/m', $text, $csp);

        foreach ($csp[0] as $line)
        {
            assert_false(strpos($line, 'redoc.ly') !== false, $file . ' allows redoc.ly');
        }
    }
});

test('Redoc page: the docblock does not claim "no external host" while the policy names one (#285)', function (): void
{
    $page = _g2mlApiDocsTestRead('web/Go2My.Link/public_html/api/docs/index.php');

    assert_false(strpos($page, 'works fully') !== false, 'The page still claims it works with no third-party call');
    assert_contains('cdn.redoc.ly', $page, 'The page docblock should name the one outside host');
});
