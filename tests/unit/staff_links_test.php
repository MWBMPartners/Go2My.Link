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
 * 🧪 Staff screen: find any short link and switch it off or on (#325)
 * ============================================================================
 *
 * No database. The page is run in a clean child process with stand-ins for the
 * database functions, which record every call.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      #325
 * ============================================================================
 */

declare(strict_types=1);

$g2mlStaffLinksFile = dirname(__DIR__, 2) . '/web/Go2My.Link/_functions/staff_links.php';

if (is_file($g2mlStaffLinksFile))
{
    require_once $g2mlStaffLinksFile;
}

/**
 * A stand-in link row.
 *
 * @param  array<string, mixed> $changes
 * @return array<string, mixed>
 */
function _g2mlStaffTestLink(array $changes = []): array
{
    return array_merge([
        'urlUID' => 41, 'orgHandle' => '[default]', 'shortCode' => 'abc123',
        'destinationURL' => 'https://bad.example/?a=1&b="<script>alert(1)</script>',
        'redirectAlias' => null, 'isActive' => 1, 'startDate' => null, 'endDate' => null,
        'clickCount' => 1234, 'lastClickAt' => null, 'createdAt' => '2026-01-02 03:04:05',
        'createdByUserUID' => 9, 'staffDisabledAt' => null, 'staffDisabledByUserUID' => null,
        'staffDisableReason' => null, 'staffPreviousIsActive' => null, 'orgName' => 'Default <Org>', 'ownerEmail' => 'owner@example.com',
        'ownerName' => 'Owner <b>', 'staffEmail' => null,
    ], $changes);
}

/**
 * Run the real staff page in a child process.
 *
 * @param  string               $role    The signed-in person's role.
 * @param  string               $method  GET or POST.
 * @param  array<string, string> $get
 * @param  array<string, string> $post   '_csrf_token' is added when valid.
 * @param  array<string, mixed> $link    The row the stand-in database returns.
 * @return array{html: string, status: mixed, queries: array<int, string>, writes: array<int, string>}|null
 */
function _g2mlStaffTestPage(string $role, string $method, array $get, array $post = [], array $link = [], bool $validToken = true, bool $sendTwice = false): ?array
{
    $root = dirname(__DIR__, 2);
    $row  = _g2mlStaffTestLink($link);

    $script  = '<?php' . "\n" . 'declare(strict_types=1);' . "\n";
    $script .= 'define(\'ENCRYPTION_SALT\', str_repeat(\'a1b2c3d4\', 8));' . "\n";
    $script .= '$_SESSION = array(\'user_role\' => ' . var_export($role, true) . ', \'user_uid\' => 5);' . "\n";
    $script .= '$GLOBALS[\'queries\'] = array(); $GLOBALS[\'writes\'] = array();' . "\n";
    $script .= 'function requireAuth(string $minRole = \'User\'): void {}' . "\n";
    $script .= 'function hasMinimumRole(string $userRole, string $requiredRole): bool { return $userRole === \'GlobalAdmin\' || $userRole === $requiredRole; }' . "\n";
    $script .= 'function logActivity(mixed ...$arguments): bool { return true; }' . "\n";
    $script .= 'function getCurrentUser(): array { return array(\'userUID\' => 5); }' . "\n";
    $script .= 'function getDefaultShortDomain(string $org): string { return \'g2my.link\'; }' . "\n";
    $script .= 'function dbSelect(string $sql, string $types = \'\', array $params = array()): array|false { $GLOBALS[\'queries\'][] = $sql; return array(' . var_export($row, true) . '); }' . "\n";
    $script .= 'function dbSelectOne(string $sql, string $types = \'\', array $params = array()): array|null|false { $rows = dbSelect($sql, $types, $params); if (str_contains($sql, \'tblOrgShortDomains\')) { return null; } return $rows[0]; }' . "\n";
    $script .= 'function dbUpdate(string $sql, string $types = \'\', array $params = array()): int|false { $GLOBALS[\'writes\'][] = $sql; return 1; }' . "\n";
    $script .= 'require_once ' . var_export($root . '/web/_functions/security.php', true) . ';' . "\n";
    $script .= 'require_once ' . var_export($root . '/web/_functions/org.php', true) . ';' . "\n";
    $script .= '$_SERVER[\'REQUEST_METHOD\'] = ' . var_export($method, true) . ';' . "\n";
    $script .= '$_GET = ' . var_export($get, true) . ';' . "\n";
    $script .= '$_POST = ' . var_export($post, true) . ';' . "\n";

    if ($method === 'POST' && $validToken)
    {
        $script .= '$_POST[\'_csrf_token\'] = g2ml_generateCSRFToken(\'staff_link_state_\' . ($_POST[\'url_uid\'] ?? \'\'));' . "\n";
    }

    $script .= 'ob_start();' . "\n";
    $script .= 'require ' . var_export($root . '/web/Go2My.Link/_admin/public_html/pages/staff/links/index.php', true) . ';' . "\n";
    $script .= '$html = ob_get_clean();' . "\n";

    if ($sendTwice)
    {
        // The browser shows the second answer.
        $script .= 'ob_start();' . "\n";
        $script .= 'require ' . var_export($root . '/web/Go2My.Link/_admin/public_html/pages/staff/links/index.php', true) . ';' . "\n";
        $script .= '$html = ob_get_clean();' . "\n";
    }

    $script .= 'echo json_encode(array(\'html\' => $html, \'status\' => http_response_code(), \'queries\' => $GLOBALS[\'queries\'], \'writes\' => $GLOBALS[\'writes\']));' . "\n";

    $tempFile = tempnam(sys_get_temp_dir(), 'g2ml_staff_page_');

    if ($tempFile === false)
    {
        return null;
    }

    file_put_contents($tempFile, $script);
    $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tempFile) . ' 2>/dev/null');
    unlink($tempFile);

    if (!is_string($output))
    {
        return null;
    }

    $decoded = json_decode(trim($output), true);

    if (!is_array($decoded))
    {
        return null;
    }

    return $decoded;
}

test('staff links: the search reads a bare code or a short address (#325)', function (): void
{
    assert_same(['code' => 'abc123', 'domain' => ''], g2ml_staffParseLinkSearch('  abc123 '));
    assert_same(['code' => 'spring-sale_1', 'domain' => 'g2my.link'], g2ml_staffParseLinkSearch('g2my.link/spring-sale_1'));
    assert_same(['code' => 'abc', 'domain' => 'g2my.link'], g2ml_staffParseLinkSearch('HTTPS://G2My.Link/abc/?utm=1#x'));
    assert_same(['code' => 'abc', 'domain' => 'acme.link'], g2ml_staffParseLinkSearch('http://acme.link:8080/abc'));
});

test('staff links: the search refuses text with no single code (#325)', function (): void
{
    foreach (['', '   ', 'two words', 'https://g2my.link/', 'g2my.link/a/b', str_repeat('a', 51), "ab\ncd", 'http:///x'] as $input)
    {
        assert_same(null, g2ml_staffParseLinkSearch($input), 'Must be refused: ' . json_encode($input));
    }
});

test('staff links: the status says why a link does or does not work (#325)', function (): void
{
    assert_same('staff_off', g2ml_staffLinkStatus(_g2mlStaffTestLink(['isActive' => 0, 'staffDisabledAt' => '2026-01-01 00:00:00'])));
    assert_same('off', g2ml_staffLinkStatus(_g2mlStaffTestLink(['isActive' => 0])));
    assert_same('scheduled', g2ml_staffLinkStatus(_g2mlStaffTestLink(['startDate' => '2999-01-01 00:00:00'])));
    assert_same('ended', g2ml_staffLinkStatus(_g2mlStaffTestLink(['endDate' => '2000-01-01 00:00:00'])));
    assert_same('on', g2ml_staffLinkStatus(_g2mlStaffTestLink()));
});

test('staff links: anyone but a GlobalAdmin gets a 403 and no look-up or change (#325)', function (): void
{
    foreach (['User', 'Admin'] as $role)
    {
        $get  = ['q' => 'abc123', 'link' => '41', 'change' => 'off'];
        $page = _g2mlStaffTestPage($role, 'GET', $get);
        assert_true($page !== null, 'The page must run');
        assert_same(403, $page['status']);
        assert_contains('Access Denied', $page['html']);
        assert_false(str_contains($page['html'], 'owner@example.com'), 'No link details for ' . $role);
        assert_same([], $page['queries']);

        $post = _g2mlStaffTestPage($role, 'POST', $get, ['url_uid' => '41', 'change' => 'off']);
        assert_true($post !== null, 'The page must run');
        assert_same(403, $post['status']);
        assert_same([], $post['writes'], 'A ' . $role . ' must not be able to change a link');
        assert_same([], $post['queries']);
    }
});

test('staff links: the destination is escaped text, never a link or a loaded address (#325)', function (): void
{
    $page = _g2mlStaffTestPage('GlobalAdmin', 'GET', ['q' => 'abc123']);
    assert_true($page !== null, 'The page must run');

    $html = $page['html'];
    assert_contains('https://bad.example/?a=1&amp;b=&quot;&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    assert_false(str_contains($html, '<script>'), 'The destination must not add HTML');
    assert_true(preg_match('/(href|src|action)="[^"]*bad\.example/', $html) !== 1, 'The destination must never be a link or a source');
    assert_contains('Owner &lt;b&gt; &lt;owner@example.com&gt;', $html);
    assert_contains('Default &lt;Org&gt; ([default])', $html);
    assert_contains('1,234', $html);
    assert_contains('href="/staff/links?q=abc123&amp;link=41&amp;change=off"', $html);
    assert_same([], $page['writes']);
});

test('staff links: the confirmation step changes nothing and carries a per-link token (#325)', function (): void
{
    $page = _g2mlStaffTestPage('GlobalAdmin', 'GET', ['q' => 'abc123', 'link' => '41', 'change' => 'off']);
    assert_true($page !== null, 'The page must run');
    assert_same([], $page['writes'], 'A GET must never change a link');
    assert_contains('Switch this link off?', $page['html']);
    assert_contains('name="_csrf_token"', $page['html']);
    assert_contains('<label for="staff-link-reason"', $page['html']);
});

test('staff links: a block can only be removed where there is one, and the confirmation says what follows (#325)', function (): void
{
    $off = _g2mlStaffTestPage('GlobalAdmin', 'GET', ['q' => 'abc123'], [], ['isActive' => 0]);
    assert_true($off !== null, 'The page must run');
    assert_false(str_contains($off['html'], 'change=unblock'), 'A link staff did not block has no block to remove');
    assert_contains('change=off', $off['html']);

    $get  = ['q' => 'abc123', 'link' => '41', 'change' => 'unblock'];
    $none = _g2mlStaffTestPage('GlobalAdmin', 'GET', $get, [], ['isActive' => 0]);
    assert_true($none !== null, 'The page must run');
    assert_false(str_contains($none['html'], 'name="change"'), 'No confirmation form');

    foreach ([1 => 'before the block: switched on.', 0 => 'before the block: switched off.'] as $previous => $words)
    {
        $page = _g2mlStaffTestPage('GlobalAdmin', 'GET', $get, [], ['isActive' => 0, 'staffDisabledAt' => '2026-01-01 00:00:00', 'staffPreviousIsActive' => $previous]);
        assert_true($page !== null, 'The page must run');
        assert_contains($words, $page['html']);
        assert_contains('Remove the staff block?', $page['html']);
        assert_same([], $page['writes']);
    }
});

test('staff links: a confirmed change needs a valid token (#325)', function (): void
{
    $post = ['url_uid' => '41', 'change' => 'off', 'reason' => 'phishing'];

    $good = _g2mlStaffTestPage('GlobalAdmin', 'POST', ['q' => 'abc123'], $post);
    assert_true($good !== null, 'The page must run');
    assert_same(1, count($good['writes']));
    assert_contains('The link is switched off.', $good['html']);

    $bad = _g2mlStaffTestPage('GlobalAdmin', 'POST', ['q' => 'abc123'], $post + ['_csrf_token' => 'wrong'], [], false);
    assert_true($bad !== null, 'The page must run');
    assert_same([], $bad['writes']);
    assert_contains('Your session has expired.', $bad['html']);

    // A double click: the same confirmation sent twice changes once and gets
    // the same answer both times.
    $twice = _g2mlStaffTestPage('GlobalAdmin', 'POST', ['q' => 'abc123'], $post, [], true, true);
    assert_true($twice !== null, 'The page must run');
    assert_same(1, count($twice['writes']));
    assert_contains('The link is switched off.', $twice['html']);
    assert_false(str_contains($twice['html'], 'Your session has expired.'), 'A double click is not an expired session');
});

test('staff links: the menu entry is only for a GlobalAdmin (#325)', function (): void
{
    $nav = (string) file_get_contents(dirname(__DIR__, 2) . '/web/_includes/nav.php');

    assert_true(
        preg_match('/hasMinimumRole\([^{]*\'GlobalAdmin\'\)\)\s*\{\s*\?>\s*<li><a class="dropdown-item" href="https:\/\/admin\.go2my\.link\/staff\/links">/', $nav) === 1,
        'The staff menu entry must sit inside the GlobalAdmin check'
    );
});

test('staff links: the redirect page ignores any address for a staff-disabled link (#325)', function (): void
{
    $source = (string) file_get_contents(dirname(__DIR__, 2) . '/web/G2My.Link/public_html/index.php');

    assert_true(preg_match('/if \(\$status === \'staff_disabled\'\)\s*\{\s*\$destination = null;\s*\}/', $source) === 1, 'The fallback must be dropped');
    assert_true(preg_match('/case \'inactive\':\s*case \'staff_disabled\':\s*http_response_code\(410\);/', $source) === 1, 'The disabled page must be shown');
});

test('staff links: seed 079 holds every new key with the same English as the code (#325)', function (): void
{
    $root = dirname(__DIR__, 2) . '/web/';
    $seed = (string) file_get_contents($root . '_sql/seeds/079_staff_link_switch_translations.sql');

    preg_match_all("/\\('en-GB', '([^']+)', '((?:[^']|'')*)'/", $seed, $matches, PREG_SET_ORDER);

    $rows = [];

    foreach ($matches as $match)
    {
        $rows[$match[1]] = str_replace("''", "'", $match[2]);
    }

    assert_contains('INSERT IGNORE INTO `tblTranslations`', $seed);

    $page = (string) file_get_contents($root . 'Go2My.Link/_admin/public_html/pages/staff/links/index.php');
    preg_match_all('/g2ml_staffText\(\s*\'(staff_links\.[a-z0-9_]+)\',\s*\'((?:[^\'\\\\]|\\\\.)*)\'/', $page, $used, PREG_SET_ORDER);
    assert_true(count($used) > 30, 'The page wording was not read');

    $usedKeys = [];

    foreach ($used as $use)
    {
        $usedKeys[$use[1]] = true;
        assert_same(str_replace("\\'", "'", $use[2]), $rows[$use[1]] ?? null, 'Seed 079 differs for ' . $use[1]);
    }

    $others = [
        'info.staff_blocked_destination'  => 'Go2My.Link/public_html/pages/info/index.php',
        'info.status_staff_blocked'       => 'Go2My.Link/public_html/pages/info/index.php',
        'edit_link.staff_disabled_notice' => 'Go2My.Link/_admin/public_html/pages/links/edit/index.php',
        'links.status_staff_disabled'     => 'Go2My.Link/_admin/public_html/pages/links/index.php',
        'nav.staff_links'                 => '_includes/nav.php',
        'redirect.link_disabled_message'  => 'G2My.Link/public_html/index.php',
    ];

    foreach ($others as $key => $file)
    {
        $source = (string) file_get_contents($root . $file);
        assert_contains('__(\'' . $key . '\')', $source);
        assert_true(isset($rows[$key]), 'Seed 079 has no row for ' . $key);
        assert_contains($rows[$key], $source, 'The English fallback differs for ' . $key);
    }

    $expected = array_merge(array_keys($usedKeys), array_keys($others));
    $actual   = array_keys($rows);
    sort($expected);
    sort($actual);
    assert_same($expected, $actual, 'Seed 079 must hold exactly the keys used');
});
