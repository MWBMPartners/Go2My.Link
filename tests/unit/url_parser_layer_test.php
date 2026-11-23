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
 * 🧪 Tests — the stricter link check (#308, F1)
 * ============================================================================
 *
 * g2ml_destinationProblem() (web/_functions/security.php) refuses addresses a
 * browser reads differently or cannot open. It has two layers:
 *
 *   - Three plain-PHP rules that run on every PHP version: a host that ends in
 *     a number must be a normal dotted IPv4 address, a port is digits only,
 *     and every `xn--` label must be real punycode with allowed letters.
 *   - On PHP 8.5 and newer, the browser-style parser (Uri\WhatWg\Url) as an
 *     extra layer that can only refuse.
 *
 * The tests run on every PHP version. The parser choice is injected through
 * $GLOBALS['g2ml_url_parser_mode_override']. That seam can only force
 * 'legacy', so a test can never make a version accept more. Where PHP has no
 * browser-style parser (8.4), the tests of the extra layer print SKIPPED.
 *
 * The two large address sets are made from a fixed random seed, so every run
 * and every PHP version sees the same addresses. They are smaller than the
 * sets used when the design was measured (150,000 and 60,000) to keep the
 * test fast.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      #308
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/../web/_functions/security.php';

/**
 * Run g2ml_destinationProblem() with the parser forced to 'legacy'.
 *
 * @param  string $url  A destination address
 * @return string|null  The reason, or null
 */
function g2mlParserLayerTest_legacyProblem(string $url): ?string
{
    $GLOBALS['g2ml_url_parser_mode_override'] = 'legacy';

    try
    {
        return g2ml_destinationProblem($url);
    }
    finally
    {
        unset($GLOBALS['g2ml_url_parser_mode_override']);
    }
}

/**
 * Say whether this PHP has the browser-style parser.
 *
 * @return bool  True on PHP 8.5 and newer
 */
function g2mlParserLayerTest_hasBrowserParser(): bool
{
    return class_exists('Uri\\WhatWg\\Url', false);
}

/**
 * Give a stable list of hostile input pieces for the generated address set.
 *
 * @return array<int, string>  The pieces
 */
function g2mlParserLayerTest_hostilePieces(): array
{
    return [
        'a', 'b.com', 'evil.com', 'g2my.link', '@', ':', '/', '\\', '?', '#', ';',
        '%40', '%2F', '%2e', '.', '[', ']', '::1', '80', '%00', "\t", ' ', '%', '&',
        '=', '0x7f', '127.0.0.1', 'xn--', '..', '//', 'É', '%C3%A9', '|', '{', '<', '123',
    ];
}

// ----------------------------------------------------------------------------
// Test 1: the parser mode.
// ----------------------------------------------------------------------------

test('url parser layer: the mode is uri-ext exactly when the class exists, and the override can only force legacy', function (): void
{
    unset($GLOBALS['g2ml_url_parser_mode_override']);

    $expected = 'legacy';

    if (g2mlParserLayerTest_hasBrowserParser())
    {
        $expected = 'uri-ext';
    }

    assert_same($expected, g2ml_urlParserMode(), 'The mode follows the class');
    echo 'PHP ' . PHP_VERSION . ': mode is ' . $expected . PHP_EOL;

    $GLOBALS['g2ml_url_parser_mode_override'] = 'legacy';
    assert_same('legacy', g2ml_urlParserMode(), 'The legacy override is honoured');

    $GLOBALS['g2ml_url_parser_mode_override'] = 'uri-ext';
    assert_same($expected, g2ml_urlParserMode(), 'uri-ext cannot be forced on');

    $GLOBALS['g2ml_url_parser_mode_override'] = 'x';
    assert_same($expected, g2ml_urlParserMode(), 'Any other value is ignored');

    unset($GLOBALS['g2ml_url_parser_mode_override']);
});

// ----------------------------------------------------------------------------
// Test 2: the three plain-PHP rules, on every version.
// ----------------------------------------------------------------------------

test('url parser layer: the three plain-PHP rules refuse the listed addresses on every version', function (): void
{
    $refused = [
        'http://1572395022/'                      => 'number_host',
        'http://0x7f.1/'                          => 'number_host',
        'http://127.1/'                           => 'number_host',
        'http://0/'                               => 'number_host',
        'https://example.123/'                    => 'number_host',
        'https://foo.0x10/'                       => 'number_host',
        'http://example.com:0x50/'                => 'odd_port',
        'http://example.com:80a/'                 => 'odd_port',
        'http://xn--./'                           => 'bad_punycode',
        'http://xn--xn--./'                       => 'bad_punycode',
        'https://xn--zz.com/'                     => 'bad_punycode',
        'https://xn--123.com/'                    => 'bad_punycode',
        // Real punycode, but the letters are a mixed-alphabet look-alike.
        'https://xn--googlecom-075n.xn--tda.de/'  => 'bad_punycode',
        'https://xn--pple-43d.xn--tda.de/'        => 'bad_punycode',
        // Real punycode of an emoji, which a typed host would also refuse.
        'https://xn--i-7iq.ws/'                   => 'bad_punycode',
        // Already refused by the older cleaner.
        'http://xn--/'                            => 'not_valid',
    ];

    foreach ($refused as $url => $reason)
    {
        assert_same($reason, g2mlParserLayerTest_legacyProblem($url), 'Legacy mode, ' . $url);
        assert_same($reason, g2ml_destinationProblem($url), 'Normal mode, ' . $url);
    }

    $accepted = [
        'https://1e100.net/',
        'https://123.example.com/',
        'https://93.184.215.14/',
        'http://127.0.0.1/',
        'https://example.com.:8080/',
        'http://example.com:/',
        'https://xn--bcher-kva.de/',
        'https://xn--e1afmkfd.xn--p1ai/',
        'https://xn--r8jz45g.xn--zckzah/',
        'https://[2606:2800:0220:0001:0248:1893:25c8:1946]/',
        'http://[2001:db8::1]:8080/',
        'HTTPS://EXAMPLE.COM/',
        'https://example.com./',
        'https://example.com/path?a[]=1&q={x}|y',
        'https://en.wikipedia.org/wiki/Foo_(bar)',
        'https://en.wikipedia.org/wiki/Array[0]?x[]=1',
        'https://user:pass@example.com:443/x',
    ];

    foreach ($accepted as $url)
    {
        assert_same(null, g2mlParserLayerTest_legacyProblem($url), 'Legacy mode accepts ' . $url);
        assert_same(null, g2ml_destinationProblem($url), 'Normal mode accepts ' . $url);
    }

    echo 'PHP ' . PHP_VERSION . ': plain-PHP rules ran on ' . (count($refused) + count($accepted)) . ' addresses' . PHP_EOL;
});

// ----------------------------------------------------------------------------
// Test 3: the browser-style parser agrees on the host.
// ----------------------------------------------------------------------------

test('url parser layer: the browser-style parser agrees on accepted addresses and refuses unreadable ones', function (): void
{
    $agree = [
        'https://example.com/',
        'HTTPS://EXAMPLE.COM/',
        'https://example.com./',
        'https://example.com.:8080/',
        'https://xn--bcher-kva.de/',
        'http://[2001:db8::1]:8080/',
        'https://[2606:2800:0220:0001:0248:1893:25c8:1946]/',
        'https://93.184.215.14/',
        'https://example.com/path?a[]=1&q={x}|y',
    ];

    if (!g2mlParserLayerTest_hasBrowserParser())
    {
        foreach ($agree as $url)
        {
            assert_true(g2ml_browserSeesSameHost($url), 'Without the parser the check has nothing to add: ' . $url);
        }

        assert_true(g2ml_browserSeesSameHost('http://xn--a.com/'), 'Without the parser even an unreadable host passes this layer');
        echo 'SKIPPED: no browser-style parser (Uri\\WhatWg\\Url), so the extra layer did not run on PHP ' . PHP_VERSION . PHP_EOL;
        return;
    }

    foreach ($agree as $url)
    {
        assert_true(g2ml_browserSeesSameHost($url), 'The browser-style parser agrees on ' . $url);
    }

    assert_false(g2ml_browserSeesSameHost('http://xn--a.com/'), 'The browser-style parser cannot read xn--a.com');

    // With the mode forced to legacy, the layer does nothing.
    $GLOBALS['g2ml_url_parser_mode_override'] = 'legacy';
    assert_true(g2ml_browserSeesSameHost('http://xn--a.com/'), 'Forced legacy skips the layer');
    unset($GLOBALS['g2ml_url_parser_mode_override']);

    echo 'PHP ' . PHP_VERSION . ': extra layer ran (browser-style parser present)' . PHP_EOL;
});

// ----------------------------------------------------------------------------
// Test 4: never more permissive than the plain-PHP baseline.
// ----------------------------------------------------------------------------

test('url parser layer: the normal mode never accepts what forced-legacy mode refuses', function (): void
{
    $inputs = [
        'https://example.com/', 'http://1572395022/', 'http://0x7f.1/', 'https://example.123/',
        'http://example.com:0x50/', 'http://xn--./', 'https://xn--bcher-kva.de/',
        'https://xn--googlecom-075n.xn--tda.de/', 'http://[2001:db8::1]:8080/',
        'https://example.com./', 'HTTP://EXAMPLE.COM/', 'http://xn--a.com/', 'http://a.com:99999/',
        'http://example.com:65536/', 'https://a_b.example.com/', 'http://example.com\\@evil.com/',
        'http://exa%6Dple.com/', 'http://127.0.0.1/', 'http://[::1]/', 'http://[::ffff:127.0.0.1]/',
        'https://example.com/%zz', 'http://a..b/', 'http://-a.com/', 'http://a.com:/x',
        'http://0300.0250.0.1/', 'http://1.2.3/', 'http://1.2.3.4.5/', 'http://.example.com/',
        'https://xn--e1afmkfd.xn--p1ai/', 'https://xn--123/',
    ];

    $hasParser = g2mlParserLayerTest_hasBrowserParser();
    $differing = 0;

    foreach ($inputs as $url)
    {
        $legacy = g2mlParserLayerTest_legacyProblem($url);
        $normal = g2ml_destinationProblem($url);

        if ($normal === null)
        {
            assert_same(null, $legacy, 'Normal accepts, so legacy must accept: ' . $url);
        }

        if (!$hasParser)
        {
            assert_same($legacy, $normal, 'Without the parser the two modes are identical: ' . $url);
            continue;
        }

        if ($legacy !== $normal)
        {
            $differing++;
            $parserClass = 'Uri\\WhatWg\\Url';
            assert_same(null, $parserClass::parse((string) g2ml_sanitiseURL($url)), 'A difference needs an unreadable address: ' . $url);
        }
    }

    if ($hasParser)
    {
        echo 'PHP ' . PHP_VERSION . ': extra layer ran; it refused ' . $differing . ' of ' . count($inputs) . ' extra addresses' . PHP_EOL;
    }
    else
    {
        echo 'SKIPPED: no browser-style parser, so the two modes are identical on PHP ' . PHP_VERSION . PHP_EOL;
    }
});

// ----------------------------------------------------------------------------
// Hostile set (fixed seed).
// ----------------------------------------------------------------------------

test('url parser layer: a seeded hostile set is never accepted by the normal mode when legacy refuses', function (): void
{
    $pieces    = g2mlParserLayerTest_hostilePieces();
    $pieceMax  = count($pieces) - 1;
    $hasParser = g2mlParserLayerTest_hasBrowserParser();

    mt_srand(4242);

    $generated   = 20000;
    $cleaned     = 0;
    $byRules     = 0;
    $byParser    = 0;
    $hostDiffers = 0;
    $broken      = 0;

    for ($count = 0; $count < $generated; $count++)
    {
        $tail   = '';
        $length = mt_rand(1, 7);

        for ($index = 0; $index < $length; $index++)
        {
            $tail .= $pieces[mt_rand(0, $pieceMax)];
        }

        $scheme = 'https://';

        if (mt_rand(0, 1) === 1)
        {
            $scheme = 'http://';
        }

        $url       = $scheme . $tail;
        $sanitised = g2ml_sanitiseURL($url);

        if ($sanitised === false)
        {
            continue;
        }

        $cleaned++;

        $legacy = g2mlParserLayerTest_legacyProblem($sanitised);
        $normal = g2ml_destinationProblem($sanitised);

        if ($legacy !== null)
        {
            $byRules++;

            if ($normal === null)
            {
                $broken++;
            }

            continue;
        }

        if ($normal !== null)
        {
            $byParser++;
            continue;
        }

        if ($hasParser && !g2ml_browserSeesSameHost($sanitised))
        {
            $hostDiffers++;
        }
    }

    assert_same(0, $broken, 'Nothing the plain-PHP rules refuse may be accepted by the normal mode');
    assert_same(0, $hostDiffers, 'For accepted addresses the browser-style parser never reads a different host');
    assert_true($cleaned > 500, 'The generated set must hold a useful number of cleaned addresses');
    assert_true($byRules > 0, 'The plain-PHP rules must refuse some of the set');

    if ($hasParser)
    {
        echo 'PHP ' . PHP_VERSION . ': hostile set generated=' . $generated . ' cleaned=' . $cleaned . ' refusedByRules=' . $byRules . ' refusedOnlyByBrowserParser=' . $byParser . ' browserReadsDifferentHost=' . $hostDiffers . PHP_EOL;
    }
    else
    {
        assert_same(0, $byParser, 'Without the parser nothing is refused by it');
        echo 'PHP ' . PHP_VERSION . ': hostile set generated=' . $generated . ' cleaned=' . $cleaned . ' refusedByRules=' . $byRules . ' (SKIPPED: no browser-style parser)' . PHP_EOL;
    }
});

// ----------------------------------------------------------------------------
// Realistic set (fixed seed).
// ----------------------------------------------------------------------------

test('url parser layer: realistic links are never refused by either layer', function (): void
{
    $hosts = [
        'example.com', 'www.example.com', 'EXAMPLE.COM', 'Www.Example.Co.Uk', 'example.com.', 'shop.example.co.uk',
        'a-b-c.example.org', 'sub_domain.example.com', 'x.com', 't.co', 'bit.ly', '1e100.net', '3com.com',
        '123.example.com', 'my.123example.com', 'xn--bcher-kva.de', 'xn--e1afmkfd.xn--p1ai', 'xn--r8jz45g.xn--zckzah',
        'xn--80ak6aa92e.com', 'docs.google.com', 'en.wikipedia.org', 'www.amazon.co.uk', 'github.com',
        'raw.githubusercontent.com', 'a.b.c.d.e.f.example.com', '93.184.215.14', '1.1.1.1', '8.8.8.8',
        '[2606:2800:220:1:248:1893:25c8:1946]', '[2606:2800:0220:0001:0248:1893:25c8:1946]', '[2001:4860:4860::8888]',
        'localhost', 'intranet', 'example.museum', 'example.io', 'example.app', 'my-site-123.net', 'a.co',
        'example.c0m', 'example.1com', 'v2.api.example.com', 'cdn-77.example.net',
    ];
    $ports = ['', '', '', '', ':80', ':443', ':8080', ':8443', ':3000', ':65535', ':0', ':080', ':'];
    $users = ['', '', '', '', '', '', 'user@', 'user:pass@', ':@', '@'];
    $paths = [
        '', '/', '/index.html', '/a/b/c', '/wiki/Foo_(bar)', '/wiki/Array[0]', '/path;jsessionid=X', '/~user/', '/a%20b',
        '/%E2%9C%93', '/dp/B08N5WRWNW/ref=sr_1_1', '/maps/place/Big+Ben/@51.5,-0.12,17z', '/a/../b/./c', '/search',
    ];
    $queries   = ['', '', '?q=1', '?a[]=1&a[]=2', '?q={"x":1}', '?utm_source=x&utm_medium=y', '?next=https://other.com/x?y=1', '?q=a|b^c', '?gclid=Cj0KCQ'];
    $fragments = ['', '', '', '#top', '#!/route?x=1', '#gid=0'];
    $schemes   = ['https://', 'https://', 'http://', 'HTTPS://'];

    mt_srand(99);

    $generated = 20000;
    $cleaned   = 0;
    $refused   = [];

    for ($count = 0; $count < $generated; $count++)
    {
        $url = $schemes[mt_rand(0, 3)]
            . $users[mt_rand(0, count($users) - 1)]
            . $hosts[mt_rand(0, count($hosts) - 1)]
            . $ports[mt_rand(0, count($ports) - 1)]
            . $paths[mt_rand(0, count($paths) - 1)]
            . $queries[mt_rand(0, count($queries) - 1)]
            . $fragments[mt_rand(0, count($fragments) - 1)];

        $sanitised = g2ml_sanitiseURL($url);

        if ($sanitised === false)
        {
            continue;
        }

        $cleaned++;

        $normal = g2ml_destinationProblem($sanitised);
        $legacy = g2mlParserLayerTest_legacyProblem($sanitised);

        if ($normal !== null || $legacy !== null)
        {
            $refused[$sanitised] = (string) $normal . '/' . (string) $legacy;
        }
    }

    assert_same([], array_slice($refused, 0, 5, true), 'No realistic link may be refused');
    assert_true($cleaned > 5000, 'The generated set must hold many cleaned addresses');

    $branch = 'extra layer ran';

    if (!g2mlParserLayerTest_hasBrowserParser())
    {
        $branch = 'SKIPPED extra layer: no browser-style parser';
    }

    echo 'PHP ' . PHP_VERSION . ': realistic set generated=' . $generated . ' cleaned=' . $cleaned . ' refused=' . count($refused) . ' (' . $branch . ')' . PHP_EOL;
});

// ----------------------------------------------------------------------------
// The extra layer is wired in, and only there.
// ----------------------------------------------------------------------------

test('url parser layer: g2ml_destinationProblem calls the browser-style layer after the plain-PHP rules', function (): void
{
    // The older cleaner is so strict that no address in the generated sets
    // reaches the extra layer and fails it, so this pins the wiring down by
    // reading the function's own source.
    $reflection = new ReflectionFunction('g2ml_destinationProblem');
    $file       = file((string) $reflection->getFileName(), FILE_IGNORE_NEW_LINES);
    assert_true(is_array($file), 'The source file must be readable');

    $body = implode("\n", array_slice($file, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));

    $shapePosition   = strpos($body, 'g2ml_urlHostShapeProblem(');
    $browserPosition = strpos($body, 'g2ml_browserSeesSameHost(');

    assert_true($shapePosition !== false, 'The plain-PHP rules are called');
    assert_true($browserPosition !== false, 'The browser-style layer is called');
    assert_true($shapePosition < $browserPosition, 'The plain-PHP rules run first');
    assert_true(strpos($body, "g2ml_urlParserMode() === 'uri-ext'") !== false, 'The layer runs only in uri-ext mode');
});

test('url parser layer: g2ml_validateLinkDestination refuses with the existing invalid_url code', function (): void
{
    require_once dirname(__DIR__, 1) . '/../web/Go2My.Link/_functions/shorturl_create.php';

    $GLOBALS['g2ml_link_destination_custom_domains_override'] = [];

    foreach (['http://1572395022/', 'http://0x7f.1/', 'https://example.123/', 'https://foo.0x10/', 'http://example.com:0x50/', 'http://xn--./', 'https://xn--googlecom-075n.xn--tda.de/'] as $url)
    {
        $result = g2ml_validateLinkDestination($url);
        assert_false($result['ok'], 'Must be refused: ' . $url);
        assert_same('invalid_url', $result['errorCode'], 'Existing code for ' . $url);
        assert_same('Invalid URL format. Please enter a valid HTTP or HTTPS URL.', $result['error'], 'Existing message for ' . $url);
    }
});

// ----------------------------------------------------------------------------
// The link check when a link is followed (#308, F1b).
// ----------------------------------------------------------------------------

/**
 * Read a repository file for the source tests.
 *
 * @param  string $relativePath  Path from the repository root
 * @return string                The file text
 */
function g2mlParserLayerTest_readSource(string $relativePath): string
{
    $text = file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
    assert_true($text !== false, 'The file must be readable: ' . $relativePath);

    return (string) $text;
}

test('follow-time check: refuses only when the check fails and the setting is on, on every version', function (): void
{
    require_once dirname(__DIR__, 1) . '/../web/G2My.Link/_functions/redirect_resolver.php';

    $GLOBALS['g2ml_link_destination_custom_domains_override'] = [];

    $passing = 'https://example.com/path?x=1';
    $failing = 'http://1572395022/';

    $result = g2ml_followTimeDestinationCheck($passing, false);
    assert_same(['problem' => '', 'refuse' => false], $result, 'Passing, setting off');

    $result = g2ml_followTimeDestinationCheck($passing, true);
    assert_same(['problem' => '', 'refuse' => false], $result, 'Passing, setting on');

    $result = g2ml_followTimeDestinationCheck($failing, false);
    assert_same('number_host', $result['problem'], 'Failing, setting off: the reason is still found for the log');
    assert_false($result['refuse'], 'Failing, setting off: the visitor is never refused');

    $result = g2ml_followTimeDestinationCheck($failing, true);
    assert_same('number_host', $result['problem'], 'Failing, setting on: the reason');
    assert_true($result['refuse'], 'Failing, setting on: refused');

    $result = g2ml_followTimeDestinationCheck('http://example.com:0x50/', true);
    assert_same('odd_port', $result['problem'], 'An odd port');
    assert_true($result['refuse'], 'An odd port is refused when on');

    $result = g2ml_followTimeDestinationCheck('not a url', true);
    assert_same('not_valid', $result['problem'], 'Text that is not an address');

    // The reason is a short word and never holds the address.
    $result = g2ml_followTimeDestinationCheck('http://1572395022/secret-token', true);
    assert_false(str_contains($result['problem'], 'secret-token'), 'The reason never holds the address');
});

test('follow-time check: the setting counts as on only for true, 1 and the text 1', function (): void
{
    require_once dirname(__DIR__, 1) . '/../web/G2My.Link/_functions/redirect_resolver.php';

    foreach ([true, 1, '1'] as $on)
    {
        assert_true(g2ml_destinationCheckIsOn($on), 'On: ' . var_export($on, true));
    }

    foreach (['0', 0, false, '', null, 'yes', 'true', '2', 1.0, []] as $off)
    {
        assert_false(g2ml_destinationCheckIsOn($off), 'Off: ' . var_export($off, true));
    }

    $info = g2mlParserLayerTest_readSource('web/Go2My.Link/public_html/pages/info/index.php');
    assert_true(
        str_contains($info, "(\$enforceValue === true || \$enforceValue === 1 || \$enforceValue === '1')"),
        'The info page uses the same rule'
    );

    $index = g2mlParserLayerTest_readSource('web/G2My.Link/public_html/index.php');
    assert_true(
        str_contains($index, "g2ml_destinationCheckIsOn(getSetting('redirect.enforce_destination_check', '0'))"),
        'The redirect page reads the setting with a default of off'
    );
});

test('follow-time check: the redirect page runs it first, before validateDestination and the click count', function (): void
{
    $index = g2mlParserLayerTest_readSource('web/G2My.Link/public_html/index.php');

    $success = strpos($index, "if (\$status === 'success' && \$destination !== null");
    $check   = strpos($index, '$destinationCheck = g2ml_followTimeDestinationCheck(');
    $validate = strpos($index, '= validateDestination(');
    $update  = strpos($index, 'UPDATE tblShortURLs');
    $refuse  = strpos($index, "'destination_refused'");

    assert_true($success !== false && $check !== false && $validate !== false && $update !== false && $refuse !== false, 'All markers are found');
    assert_true($success < $check, 'The check is inside the success branch');
    assert_true($check < $validate, 'The check comes before validateDestination()');
    assert_true($check < $update, 'The check comes before the click-count UPDATE');
    assert_true($refuse < $update, 'A refusal ends before the click-count UPDATE');

    // The warning line and the refusal branch, which ends at its own exit.
    $refuseStart = strpos($index, "if (\$destinationCheck['refuse'])");
    assert_true($refuseStart !== false, 'The refusal branch is found');
    $refuseEnd = strpos($index, 'exit;', (int) $refuseStart);
    assert_true($refuseEnd !== false, 'The refusal branch ends in exit');
    $refusal = substr($index, (int) $check, (int) $refuseEnd + 5 - (int) $check);
    assert_true(str_contains($refusal, 'http_response_code(404)'), 'The refusal is a 404');
    assert_true(str_contains($refusal, "'404.php'"), 'The refusal uses the existing 404 page');
    assert_true(str_contains($refusal, 'exit;'), 'The refusal stops the page');
    assert_false(str_contains($refusal, 'buildRedirectResponse'), 'A refusal never redirects');
    assert_false(str_contains($refusal, "'redirect', 'success'"), 'A refusal is not logged as a click');
    assert_false(str_contains($refusal, 'destinationURL'), 'The refusal log never holds the address');
    assert_true(str_contains($refusal, 'g2ml_destinationCheckLogLine('), 'The warning line is written');
    assert_true(str_contains($refusal, "redirect.destination_refused_title"), 'Title key');
    assert_true(str_contains($refusal, "redirect.destination_refused_message"), 'Message key');
});

test('follow-time check: validateDestination, the SSRF code and the info helper do not use it', function (): void
{
    $resolver = g2mlParserLayerTest_readSource('web/G2My.Link/_functions/redirect_resolver.php');

    $start = strpos($resolver, 'function validateDestination(');
    $end   = strpos($resolver, 'function buildRedirectResponse(');
    assert_true($start !== false && $end !== false && $start < $end, 'validateDestination() is found');

    $body = substr($resolver, (int) $start, (int) $end - (int) $start);
    assert_false(str_contains($body, 'g2ml_followTimeDestinationCheck'), 'validateDestination() does not call the check');
    assert_false(str_contains($body, 'g2ml_destinationProblem'), 'validateDestination() does not call the link check');

    $display = g2mlParserLayerTest_readSource('web/Go2My.Link/_functions/info_display.php');
    assert_false(str_contains($display, 'g2ml_destinationProblem'), 'The info helper stays pure');
});

test('follow-time check: new Uri appears nowhere in the web code', function (): void
{
    $root     = dirname(__DIR__, 2) . '/web';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    $checked  = 0;

    foreach ($iterator as $file)
    {
        $path = $file->getPathname();

        if (substr($path, -4) !== '.php' || str_contains($path, '/_libraries/'))
        {
            continue;
        }

        $checked++;
        $text = (string) file_get_contents($path);
        assert_false(preg_match('/new\s+\\\\?Uri\b/', $text) === 1, 'No constructor call on the browser-style parser in ' . $path);
    }

    assert_true($checked > 50, 'Many files were checked');
});

test('follow-time check: seed 074 has the setting off by default and the three texts matching the code', function (): void
{
    $seed = g2mlParserLayerTest_readSource('web/_sql/seeds/074_destination_check.sql');

    assert_true(
        preg_match("/\\('redirect\\.enforce_destination_check', 'System', NULL,\\s*'0', '0', '/", $seed) === 1,
        'The setting is System scope and defaults to 0'
    );
    assert_true(str_contains($seed, "'boolean', 0, 1)"), 'It is a boolean and editable');
    assert_true(str_contains($seed, 'ON DUPLICATE KEY UPDATE'), 'It can be re-run');
    assert_true(str_contains($seed, 'INSERT IGNORE INTO tblTranslations'), 'Texts are INSERT IGNORE');

    $index = g2mlParserLayerTest_readSource('web/G2My.Link/public_html/index.php');
    $info  = g2mlParserLayerTest_readSource('web/Go2My.Link/public_html/pages/info/index.php');

    $texts = [
        'redirect.destination_refused_title'   => ['Link Cannot Be Opened', $index],
        'redirect.destination_refused_message' => ["This short link's destination address is not valid, so we cannot send you there.", $index],
        'info.destination_refused'             => ["This link's destination address is not valid, so the link will not open.", $info],
    ];

    foreach ($texts as $key => $pair)
    {
        $english  = $pair[0];
        $code     = $pair[1];
        $inSeed   = "('en-GB', '" . $key . "', '" . str_replace("'", "''", $english) . "', ";
        $inCode   = "'" . str_replace("'", "\\'", $english) . "'";

        assert_true(str_contains($seed, $inSeed), 'Seed has ' . $key . ' with the expected English');
        assert_true(str_contains($seed, "', 1);") || str_contains($seed, "', 1),"), 'Verified rows');
        assert_true(str_contains($code, "__('" . $key . "')"), 'The code uses the key ' . $key);
        assert_true(str_contains($code, $inCode), 'The code fallback matches the seed for ' . $key);
    }
});

test('follow-time check: the warning line never holds a control character or the address', function (): void
{
    require_once dirname(__DIR__, 1) . '/../web/G2My.Link/_functions/redirect_resolver.php';

    $line = g2ml_destinationCheckLogLine("ab\x1b[31mc\x07\x08\x7f\x01\n", 'number_host');

    assert_same(0, preg_match('/[\x00-\x1f\x7f]/', $line), 'No byte below 0x20 or 0x7F');
    assert_true(str_contains($line, 'ab%1B%5B31mc'), 'The code is percent-encoded');
    assert_same(
        '[Go2My.Link] WARNING: short link spring-sale has a destination that fails the link check (odd_port).',
        g2ml_destinationCheckLogLine('spring-sale', 'odd_port'),
        'A normal code reads the same as before'
    );

    $index = g2mlParserLayerTest_readSource('web/G2My.Link/public_html/index.php');
    assert_true(str_contains($index, 'error_log(g2ml_destinationCheckLogLine($shortCode,'), 'The page uses the safe line');
    assert_true(str_contains($index, "'shortCode' => rawurlencode(\$shortCode)"), 'The refusal log holds the encoded code');
});

test('follow-time check: a fault in the check cannot break a redirect', function (): void
{
    $index = g2mlParserLayerTest_readSource('web/G2My.Link/public_html/index.php');

    $try   = strpos($index, "try\n    {\n        \$destinationCheck = g2ml_followTimeDestinationCheck(");
    $catch = strpos($index, 'catch (\\Throwable $checkError)');
    $default = strpos($index, "\$destinationCheck = ['problem' => '', 'refuse' => false];");

    assert_true($default !== false && $try !== false && $catch !== false, 'The default, try and catch are present');
    assert_true($default < $try && $try < $catch, 'The default is set before the try');
    assert_true(strpos($index, 'the link check failed to run') > $catch, 'A failure is logged');
});

test('follow-time check: the info page checks only plain, non-empty address links', function (): void
{
    $info = g2mlParserLayerTest_readSource('web/Go2My.Link/public_html/pages/info/index.php');

    assert_true(str_contains($info, "\$destinationType !== 'alias'"), 'Alias links are skipped');
    assert_true(str_contains($info, "\$destinationToCheck !== ''"), 'Empty destinations are skipped');
    assert_true(str_contains($info, 'g2ml_destinationProblem($destinationToCheck)'), 'The checked text is the non-empty one');

    // Run the real block for each row with the setting forced on.
    $start = strpos($info, "\$destinationType = 'url';");
    $end   = strpos($info, '// Determine link status for display');
    assert_true($start !== false && $end !== false, 'The block is found');
    $block = substr($info, (int) $start, (int) $end - (int) $start);
    $block = substr($block, 0, (int) strrpos($block, "// ====="));
    $block = str_replace("getSetting('redirect.enforce_destination_check', '0')", "'1'", $block);
    $block = str_replace("function_exists('getSetting') && ", '', $block);
    assert_true(str_contains($block, "\$enforceValue = '1';"), 'The setting read was replaced');

    $GLOBALS['g2ml_link_destination_custom_domains_override'] = [];

    $rows = [
        'alias row, empty destination'  => [['destinationType' => 'alias', 'destinationURL' => null], false],
        'alias row, stale failing text' => [['destinationType' => 'alias', 'destinationURL' => 'http://1572395022/'], false],
        'url row, empty destination'    => [['destinationType' => 'url', 'destinationURL' => ''], false],
        'url row, null destination'     => [['destinationType' => 'url', 'destinationURL' => null], false],
        'url row, failing address'      => [['destinationType' => 'url', 'destinationURL' => 'http://1572395022/'], true],
        'url row, passing address'      => [['destinationType' => 'url', 'destinationURL' => 'https://example.com/'], false],
    ];

    foreach ($rows as $name => $case)
    {
        $linkData           = $case[0];
        $destinationDisplay = 'shown';
        $destinationRefused = false;

        eval($block);

        assert_same($case[1], $destinationRefused, $name);

        if ($case[1])
        {
            assert_true($destinationDisplay !== 'shown', 'The notice replaces the destination: ' . $name);
        }
        else
        {
            assert_same('shown', $destinationDisplay, 'Nothing changes: ' . $name);
        }
    }
});
