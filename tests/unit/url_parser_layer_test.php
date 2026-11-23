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
