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
 * 🧪 Unit tests — the shared "access refused" block on dashboard pages (#279)
 * ============================================================================
 *
 * Six dashboard pages (API Keys, Custom Domains, Members, Invite Member,
 * Organisation Settings and Short Domains) used to refuse access with a bare
 * one-line red box. Five had English-only wording, and a person who simply
 * had no organisation yet was told "You do not have permission", which is
 * the wrong reason. They now all call g2ml_renderOrgAccessDenied() in
 * web/_functions/org.php.
 *
 * What these tests check:
 *   - the block says the right thing to each of the two kinds of person;
 *   - the page's own message is escaped, so it cannot inject HTML;
 *   - the response status is set to 403;
 *   - every word goes through __() with a key that seed 067 defines, and the
 *     English written at the call site matches the seeded English;
 *   - all six pages call the shared function and no longer print their own box;
 *   - pages that are not about an organisation (the breach-response page)
 *     refuse with the same block through g2ml_renderAccessDenied(), and no
 *     dashboard page asks requireAuth() for a higher role, whose own refusal
 *     is a bare page with no menu (#306).
 *
 * Four of these tests run PHP in a separate child process. The unit runner
 * loads every test into one shared process where __() is not defined, and a
 * function cannot be undefined again, so defining a stand-in __() here would
 * change every other test's behaviour. The breach-page test also stands in
 * for requireAuth(), hasMinimumRole(), logActivity(), getCurrentUser() and
 * g2ml_breachResponse(), so no database is used and the real password reset
 * can never run. The same technique is used in
 * tests/unit/linkspage_manage_test.php.
 *
 * Not checked here: how the block looks in a browser.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.2.0 — Dashboard fixes (#279)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/security.php';
require_once dirname(__DIR__, 2) . '/web/_functions/org.php';

// ============================================================================
// 🧰 Helpers
// ============================================================================

/**
 * Read a repository file as text, failing the test with a clear message if
 * it cannot be read.
 *
 * @param  string $relativePath  Path from the repository root, using "/".
 * @return string
 */
function _g2mlOrgAccessTestRead(string $relativePath): string
{
    $fullPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    assert_true(is_file($fullPath), 'Expected file not found: ' . $relativePath);

    $text = file_get_contents($fullPath);

    assert_true($text !== false, 'Could not read the file: ' . $relativePath);

    return $text;
}

/**
 * Read the en-GB rows of a seed file into key => English text.
 *
 * @param  string $relativePath  Path from the repository root.
 * @return array<string, string>
 */
function _g2mlOrgAccessTestSeedRows(string $relativePath): array
{
    $text = _g2mlOrgAccessTestRead($relativePath);
    $rows = array();

    $matched = preg_match_all("/\\('en-GB', '([^']+)', '((?:[^']|'')*)'/", $text, $matches, PREG_SET_ORDER);

    assert_true($matched !== false, 'Could not read the rows of ' . $relativePath);

    foreach ($matches as $match)
    {
        $rows[$match[1]] = str_replace("''", "'", $match[2]);
    }

    return $rows;
}

/**
 * Run g2ml_renderOrgAccessDenied() in a clean child process and return what
 * it printed and the response status it set.
 *
 * @param  string $orgHandle      The handle to pass.
 * @param  string $message        The page's own message to pass.
 * @param  bool   $withTranslate  True to define a stand-in __() that returns
 *                                "[T:" . key . "]", so the test can see which
 *                                key each piece of text asked for.
 * @param  bool   $generic        True to call g2ml_renderAccessDenied() (the
 *                                block for pages not about an organisation)
 *                                instead; $orgHandle is then not used.
 * @return array{status: mixed, html: string}|null  Null if the child failed.
 */
function _g2mlOrgAccessTestChild(string $orgHandle, string $message, bool $withTranslate, bool $generic = false): ?array
{
    $root = dirname(__DIR__, 2);

    $script = '<?php' . "\n" . 'declare(strict_types=1);' . "\n";
    $script .= 'ob_start();' . "\n";

    if ($withTranslate === true)
    {
        $script .= 'function __(string $key, array $replacements = []): string' . "\n";
        $script .= '{' . "\n";
        $script .= '    return \'[T:\' . $key . \']\';' . "\n";
        $script .= '}' . "\n";
    }

    $script .= 'require_once ' . var_export($root . '/web/_functions/security.php', true) . ';' . "\n";
    $script .= 'require_once ' . var_export($root . '/web/_functions/org.php', true) . ';' . "\n";
    if ($generic === true)
    {
        $script .= 'g2ml_renderAccessDenied(' . var_export($message, true) . ');' . "\n";
    }
    else
    {
        $script .= 'g2ml_renderOrgAccessDenied(' . var_export($orgHandle, true) . ', ' . var_export($message, true) . ');' . "\n";
    }
    $script .= '$html = ob_get_clean();' . "\n";
    $script .= 'echo json_encode([\'status\' => http_response_code(), \'html\' => $html]);' . "\n";

    $tempFile = tempnam(sys_get_temp_dir(), 'g2ml_org_access_');

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

    $decoded = json_decode($output, true);

    if (!is_array($decoded))
    {
        return null;
    }

    return array(
        'status' => $decoded['status'] ?? null,
        'html'   => (string) ($decoded['html'] ?? ''),
    );
}

/**
 * Run the real breach-response page in a clean child process, as a POST that
 * carries a valid form token, a ticked confirmation box and a reason, and
 * report what happened.
 *
 * The functions the page needs from the rest of the application are stood in
 * for, so no database is used: requireAuth() lets everyone through (the
 * front controller has already checked sign-in), hasMinimumRole() treats
 * only 'GlobalAdmin' as high enough, and g2ml_breachResponse() (the mass
 * password reset) only records that it was called.
 *
 * @param  string $role  The signed-in person's role ('User' or 'GlobalAdmin').
 * @return array{called: bool, status: mixed, form: bool, denied: bool}|null  Null if the child failed.
 */
function _g2mlOrgAccessTestBreachChild(string $role): ?array
{
    $root = dirname(__DIR__, 2);

    $script = '<?php' . "\n" . 'declare(strict_types=1);' . "\n";
    $script .= '$_SESSION = array(\'user_role\' => ' . var_export($role, true) . ', \'user_uid\' => 5);' . "\n";
    $script .= 'function requireAuth(string $minRole = \'User\'): void' . "\n" . '{' . "\n" . '}' . "\n";
    $script .= 'function hasMinimumRole(string $userRole, string $requiredRole): bool' . "\n" . '{' . "\n";
    $script .= '    return $userRole === \'GlobalAdmin\' || $userRole === $requiredRole;' . "\n" . '}' . "\n";
    $script .= 'function logActivity(mixed ...$arguments): void' . "\n" . '{' . "\n" . '}' . "\n";
    $script .= 'function getCurrentUser(): array' . "\n" . '{' . "\n" . '    return array(\'userUID\' => 5);' . "\n" . '}' . "\n";
    $script .= '$GLOBALS[\'breachCalled\'] = false;' . "\n";
    $script .= 'function g2ml_breachResponse(int $userUID, string $reason, ?string $newSalt = null): array' . "\n" . '{' . "\n";
    $script .= '    $GLOBALS[\'breachCalled\'] = true;' . "\n";
    $script .= '    return array(\'success\' => false, \'error\' => \'stand-in\');' . "\n" . '}' . "\n";
    $script .= 'require_once ' . var_export($root . '/web/_functions/security.php', true) . ';' . "\n";
    $script .= 'require_once ' . var_export($root . '/web/_functions/org.php', true) . ';' . "\n";
    $script .= '$_SERVER[\'REQUEST_METHOD\'] = \'POST\';' . "\n";
    $script .= '$_POST = array(\'form_type\' => \'breach_response\', \'_csrf_token\' => g2ml_generateCSRFToken(\'breach_response_form\'), \'confirm_breach\' => \'1\', \'breach_reason\' => \'test\');' . "\n";
    $script .= 'ob_start();' . "\n";
    $script .= 'require ' . var_export($root . '/web/Go2My.Link/_admin/public_html/pages/security/breach-response.php', true) . ';' . "\n";
    $script .= '$html = ob_get_clean();' . "\n";
    $script .= 'echo json_encode(array(\'called\' => $GLOBALS[\'breachCalled\'], \'status\' => http_response_code(), \'form\' => str_contains($html, \'breach-response-form\'), \'denied\' => str_contains($html, \'Access Denied\')));' . "\n";

    $tempFile = tempnam(sys_get_temp_dir(), 'g2ml_breach_page_');

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

    return array(
        'called' => ($decoded['called'] ?? null) === true,
        'status' => $decoded['status'] ?? null,
        'form'   => ($decoded['form'] ?? null) === true,
        'denied' => ($decoded['denied'] ?? null) === true,
    );
}

// ============================================================================
// 🏢 Someone with no organisation yet ('[default]')
// ============================================================================

test('access refused: a person with no organisation is told so and offered a way to create one', function (): void
{
    $html = g2ml_orgAccessDeniedHTML('[default]', 'You do not have permission to manage members.');

    assert_contains('You are not in an organisation yet', $html);
    assert_contains('API keys, domains and members belong to an organisation. Create one to use this page.', $html);
    assert_contains('href="/org/create"', $html);
    assert_contains('Create an organisation', $html);
    assert_contains('btn btn-primary', $html);
    assert_contains('<h1 ', $html);
    assert_contains('aria-hidden="true"', $html);
});

test('access refused: a person with no organisation is NOT told they lack permission', function (): void
{
    $html = g2ml_orgAccessDeniedHTML('[default]', 'You do not have permission to manage members.');

    assert_false(strpos($html, 'do not have permission') !== false, 'The wrong reason must not be shown to someone with no organisation');
    assert_false(strpos($html, 'Access Denied') !== false, 'The "Access Denied" heading is for people who belong to an organisation');
});

// ============================================================================
// 🔒 Someone who belongs to an organisation but may not manage it
// ============================================================================

test('access refused: a member who may not manage the organisation sees the page\'s own message', function (): void
{
    $html = g2ml_orgAccessDeniedHTML('acme', 'You do not have permission to manage domains.');

    assert_contains('Access Denied', $html);
    assert_contains('You do not have permission to manage domains.', $html);
    assert_contains('href="/"', $html);
    assert_contains('Back to Dashboard', $html);
    assert_contains('fas fa-lock', $html);
    assert_contains('aria-hidden="true"', $html);
    assert_contains('<h1 ', $html);
    assert_false(strpos($html, '/org/create') !== false, 'A member of an organisation must not be sent to create one');
});

test('access refused: the page\'s own message is escaped', function (): void
{
    $html = g2ml_orgAccessDeniedHTML('acme', '<script>alert(1)</script> & "quoted"');

    assert_false(strpos($html, '<script>') !== false, 'The message must not be able to add HTML');
    assert_contains('&lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;quoted&quot;', $html);
});

// ============================================================================
// 🌍 Translation: every word goes through __() with a key that seed 067 defines
// ============================================================================

test('access refused: the English written at the call site matches seed 067', function (): void
{
    // __() is not defined in this shared process, so the plain-English
    // fallback text is what comes out here.
    $seedRows = _g2mlOrgAccessTestSeedRows('web/_sql/seeds/067_dashboard_access_translations.sql');

    $noOrganisation = g2ml_orgAccessDeniedHTML('[default]', 'unused');
    $hasOrganisation = g2ml_orgAccessDeniedHTML('acme', 'unused');

    assert_contains($seedRows['access.not_in_org_heading'], $noOrganisation);
    assert_contains($seedRows['access.not_in_org_message'], $noOrganisation);
    assert_contains($seedRows['access.create_org_button'], $noOrganisation);
    assert_contains($seedRows['access.denied_heading'], $hasOrganisation);
    assert_contains($seedRows['access.back_to_dashboard'], $hasOrganisation);
});

test('access refused: with __() present, each piece of text asks for its own seeded key', function (): void
{
    $seedRows = _g2mlOrgAccessTestSeedRows('web/_sql/seeds/067_dashboard_access_translations.sql');

    $noOrganisation = _g2mlOrgAccessTestChild('[default]', 'unused', true);
    $hasOrganisation = _g2mlOrgAccessTestChild('acme', 'unused', true);

    assert_true($noOrganisation !== null, 'The child process did not return a result');
    assert_true($hasOrganisation !== null, 'The child process did not return a result');

    $expected = array(
        'access.not_in_org_heading' => (string) $noOrganisation['html'],
        'access.not_in_org_message' => (string) $noOrganisation['html'],
        'access.create_org_button'  => (string) $noOrganisation['html'],
        'access.denied_heading'     => (string) $hasOrganisation['html'],
        'access.back_to_dashboard'  => (string) $hasOrganisation['html'],
    );

    foreach ($expected as $key => $html)
    {
        assert_contains('[T:' . $key . ']', $html, 'The block does not ask __() for ' . $key);
        assert_true(array_key_exists($key, $seedRows), 'Seed 067 does not define ' . $key);
    }
});

test('access refused: the response status is set to 403 for both kinds of person', function (): void
{
    $noOrganisation = _g2mlOrgAccessTestChild('[default]', 'unused', false);
    $hasOrganisation = _g2mlOrgAccessTestChild('acme', 'unused', false);

    assert_true($noOrganisation !== null, 'The child process did not return a result');
    assert_true($hasOrganisation !== null, 'The child process did not return a result');

    assert_same(403, $noOrganisation['status']);
    assert_same(403, $hasOrganisation['status']);
    assert_contains('You are not in an organisation yet', (string) $noOrganisation['html']);
});

// ============================================================================
// 📄 The six pages must use the shared block
// ============================================================================

$g2mlOrgAccessTestPages = array(
    'web/Go2My.Link/_admin/public_html/pages/api-keys/index.php' => array(
        'key'      => 'apikeys.error_forbidden',
        'seed'     => 'web/_sql/seeds/010_phase6_translations.sql',
        'fallback' => 'You do not have permission to manage API keys.',
    ),
    'web/Go2My.Link/_admin/public_html/pages/org/domains/index.php' => array(
        'key'      => 'org.domains_error_forbidden',
        'seed'     => 'web/_sql/seeds/067_dashboard_access_translations.sql',
        'fallback' => 'You do not have permission to manage domains.',
    ),
    'web/Go2My.Link/_admin/public_html/pages/org/members/index.php' => array(
        'key'      => 'org.members_error_forbidden',
        'seed'     => 'web/_sql/seeds/067_dashboard_access_translations.sql',
        'fallback' => 'You do not have permission to manage members.',
    ),
    'web/Go2My.Link/_admin/public_html/pages/org/members/invite/index.php' => array(
        'key'      => 'org.invite_error_forbidden',
        'seed'     => 'web/_sql/seeds/067_dashboard_access_translations.sql',
        'fallback' => 'You do not have permission to invite members.',
    ),
    'web/Go2My.Link/_admin/public_html/pages/org/settings/index.php' => array(
        'key'      => 'org.settings_error_forbidden',
        'seed'     => 'web/_sql/seeds/067_dashboard_access_translations.sql',
        'fallback' => 'You do not have permission to manage organisation settings.',
    ),
    'web/Go2My.Link/_admin/public_html/pages/org/short-domains/index.php' => array(
        'key'      => 'org.short_domains_error_forbidden',
        'seed'     => 'web/_sql/seeds/067_dashboard_access_translations.sql',
        'fallback' => 'You do not have permission to manage short domains.',
    ),
);

foreach ($g2mlOrgAccessTestPages as $g2mlOrgAccessTestPagePath => $g2mlOrgAccessTestPageInfo)
{
    test('access refused: ' . $g2mlOrgAccessTestPagePath . ' uses the shared block with a seeded message', function () use ($g2mlOrgAccessTestPagePath, $g2mlOrgAccessTestPageInfo): void
    {
        $source = _g2mlOrgAccessTestRead($g2mlOrgAccessTestPagePath);

        assert_contains('g2ml_renderOrgAccessDenied($orgHandle, $deniedMessage);', $source, 'The page does not call the shared function');
        assert_contains("__('" . $g2mlOrgAccessTestPageInfo['key'] . "')", $source, 'The page does not translate its own message');
        assert_contains("'" . $g2mlOrgAccessTestPageInfo['fallback'] . "'", $source, 'The page lost its English fallback');
        assert_false(strpos($source, "echo '<i class=\"fas fa-lock\" aria-hidden=\"true\"></i> ';") !== false, 'The page still prints its own red box');

        $seedRows = _g2mlOrgAccessTestSeedRows($g2mlOrgAccessTestPageInfo['seed']);

        assert_true(array_key_exists($g2mlOrgAccessTestPageInfo['key'], $seedRows), $g2mlOrgAccessTestPageInfo['seed'] . ' does not define ' . $g2mlOrgAccessTestPageInfo['key']);
        assert_same($g2mlOrgAccessTestPageInfo['fallback'], $seedRows[$g2mlOrgAccessTestPageInfo['key']], 'The English at the call site differs from the seeded English');
    });
}

// ============================================================================
// 🔒 Pages that are not about an organisation (#306)
// ============================================================================

test('access refused: the generic block says "Access Denied", shows the page\'s message and sets 403', function (): void
{
    $plain = _g2mlOrgAccessTestChild('', 'Admins only <b>here</b>.', false, true);
    $translated = _g2mlOrgAccessTestChild('', 'unused', true, true);

    assert_true($plain !== null, 'The child process did not return a result');
    assert_true($translated !== null, 'The child process did not return a result');

    assert_same(403, $plain['status']);
    assert_contains('Access Denied', (string) $plain['html']);
    assert_contains('Admins only &lt;b&gt;here&lt;/b&gt;.', (string) $plain['html'], 'The message must be escaped');
    assert_contains('href="/"', (string) $plain['html'], 'The way back must lead to the dashboard home');
    assert_false(strpos((string) $plain['html'], 'organisation') !== false, 'The generic block must not talk about organisations');

    assert_contains('[T:access.denied_heading]', (string) $translated['html']);
    assert_contains('[T:access.back_to_dashboard]', (string) $translated['html']);
});

test('access refused: the breach-response page refuses non-admins with the generic block and a seeded message', function (): void
{
    $source = _g2mlOrgAccessTestRead('web/Go2My.Link/_admin/public_html/pages/security/breach-response.php');

    assert_false(strpos($source, "requireAuth('GlobalAdmin')") !== false, 'The page uses requireAuth()\'s bare refusal page again');
    assert_contains("if (!hasMinimumRole(\$breachUserRole, 'GlobalAdmin'))", $source, 'The page no longer checks for GlobalAdmin');
    assert_contains('g2ml_renderAccessDenied($breachDeniedMessage);', $source, 'The page does not use the generic block');
    assert_contains("__('error.403_message')", $source, 'The page does not translate its message');

    $seedRows = _g2mlOrgAccessTestSeedRows('web/_sql/seeds/010_phase6_translations.sql');

    assert_true(array_key_exists('error.403_message', $seedRows), 'Seed 010 does not define error.403_message');
    assert_contains("'" . str_replace("'", "\\'", $seedRows['error.403_message']) . "'", $source, 'The English at the call site differs from the seeded English');
});

test('access refused: an ordinary user who posts the breach form gets the refusal and nothing else (#306)', function (): void
{
    $ordinaryUser = _g2mlOrgAccessTestBreachChild('User');
    $globalAdmin  = _g2mlOrgAccessTestBreachChild('GlobalAdmin');

    assert_true($ordinaryUser !== null, 'The child process for an ordinary user did not return a result');
    assert_true($globalAdmin !== null, 'The child process for a GlobalAdmin did not return a result');

    // The same harness reaches the form and the reset for a GlobalAdmin, so a
    // pass below cannot come from the page failing before it got that far.
    assert_true($globalAdmin['form'], 'Control: a GlobalAdmin should see the breach form');
    assert_true($globalAdmin['called'], 'Control: a GlobalAdmin\'s confirmed post should reach the reset');

    assert_same(403, $ordinaryUser['status']);
    assert_true($ordinaryUser['denied'], 'An ordinary user should see "Access Denied"');
    assert_false($ordinaryUser['form'], 'An ordinary user must not see the breach form');
    assert_false($ordinaryUser['called'], 'An ordinary user\'s post must never reach the mass password reset');
});

test('access refused: no dashboard page asks requireAuth() for a role above an ordinary user', function (): void
{
    $pagesFolder = dirname(__DIR__, 2) . '/web/Go2My.Link/_admin/public_html/pages';
    $iterator    = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pagesFolder, FilesystemIterator::SKIP_DOTS));
    $offenders   = array();

    foreach ($iterator as $file)
    {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php')
        {
            $source = (string) file_get_contents($file->getPathname());

            if (preg_match_all("/requireAuth\\(\\s*['\"]([A-Za-z]+)['\"]\\s*\\)/", $source, $matches) > 0)
            {
                foreach ($matches[1] as $role)
                {
                    if ($role !== 'User')
                    {
                        $offenders[] = substr($file->getPathname(), strlen($pagesFolder) + 1) . ' (' . $role . ')';
                    }
                }
            }
        }
    }

    assert_same(array(), $offenders, 'requireAuth() refuses with a bare page with no menu; check the role and call g2ml_renderAccessDenied() instead');
});
