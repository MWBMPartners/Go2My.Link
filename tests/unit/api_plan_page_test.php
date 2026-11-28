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
 * 🧪 Unit tests — the API Keys page and messages when the plan has no API
 *                 access (#328, owner decision D-025)
 * ============================================================================
 *
 * The page is run in a separate PHP process with stand-ins for the database
 * functions, so each answer of g2ml_apiPlanAccess() can be chosen. Whether
 * that function answers correctly is tested against a real database in
 * tests/integration/api_plan_access_test.php.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      #328
 * ============================================================================
 */

declare(strict_types=1);

/**
 * Run the API Keys page once and return what it printed.
 *
 * @param  string $access  What the stand-in g2ml_apiPlanAccess() answers for
 *                         the signed-in user (7). The owner of key 2 (user 9)
 *                         is always allowed, as a global administrator is.
 * @param  bool   $hasKeys Whether the organisation already has keys.
 * @param  int    $ownerBelongs  The ownerBelongs value both keys carry.
 * @param  int    $ownerIsAdmin  The ownerIsAdmin value both keys carry.
 * @return array{status: int, html: string}
 */
function g2mlApiPlanPageTest_render(string $access, bool $hasKeys, int $ownerBelongs = 1, int $ownerIsAdmin = 1): array
{
    $root = dirname(__DIR__, 2);

    $script  = '$_SESSION = array();' . "\n";
    $script .= 'require ' . var_export($root . '/web/_functions/security.php', true) . ';' . "\n";
    $script .= 'require ' . var_export($root . '/web/_includes/accessibility.php', true) . ';' . "\n";
    $script .= 'function getCurrentUser() { return array(\'orgHandle\' => \'acme\', \'userUID\' => 7); }' . "\n";
    $script .= 'function canManageOrg($org) { return true; }' . "\n";
    $script .= 'function g2ml_mainSiteURL($path) { return \'https://main.test\' . $path; }' . "\n";
    $script .= 'function g2ml_apiValidScopesList() { return array(\'urls:read\'); }' . "\n";
    $script .= 'function g2ml_apiKeyIsExpired($expiresAt, $now) { return false; }' . "\n";
    $script .= 'function g2ml_apiPlanAccess($userUID, $orgHandle) { if ($userUID === 9) { return \'allowed\'; } return ' . var_export($access, true) . '; }' . "\n";
    $script .= 'function g2ml_apiPlanMessage($answer) { return \'MESSAGE-FOR-\' . $answer; }' . "\n";

    $keys = array();

    if ($hasKeys)
    {
        foreach (array(1 => 7, 2 => 9) as $keyUID => $owner)
        {
            $keys[] = array(
                'apiKeyUID'    => $keyUID,
                'userUID'      => $owner,
                'keyName'      => 'Key ' . $keyUID,
                'apiKeyPrefix' => 'abcd1234',
                'permissions'  => array('urls:read'),
                'createdAt'    => '2026-01-01 00:00:00',
                'lastUsedAt'   => null,
                'expiresAt'    => null,
                'isActive'     => 1,
                'ownerBelongs' => $ownerBelongs,
                'ownerIsAdmin' => $ownerIsAdmin,
            );
        }
    }

    $script .= 'function g2ml_apiListKeys($org) { return ' . var_export($keys, true) . '; }' . "\n";
    $script .= '$_SERVER[\'REQUEST_METHOD\'] = \'GET\';' . "\n";
    $script .= 'ob_start(); require ' . var_export($root . '/web/Go2My.Link/_admin/public_html/pages/api-keys/index.php', true) . '; $html = ob_get_clean();' . "\n";
    $script .= 'echo json_encode(array(\'status\' => (int) http_response_code(), \'html\' => $html));' . "\n";

    $process = proc_open(
        array(PHP_BINARY, '-d', 'display_errors=stderr', '-r', $script),
        array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
        $pipes
    );

    $output = (string) stream_get_contents($pipes[1]);
    $errors = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    $result = json_decode($output, true);

    assert_true(is_array($result), 'The page did not run: ' . $output . $errors);
    assert_same('', trim($errors), 'The page must run without warnings');

    return $result;
}

test('API plan page (#328): a plan without API access gets an explanation and a next step, no create form, status 200', function (): void
{
    $page = g2mlApiPlanPageTest_render('not_included', false);

    assert_true($page['status'] !== 403, 'Not part of the plan is not "access denied"');
    assert_contains('API access is not part of your plan', $page['html']);
    assert_contains('href="/links"', $page['html']);
    assert_contains('https://main.test/contact', $page['html']);
    assert_contains('No API keys created yet.', $page['html']);
    assert_false(str_contains($page['html'], 'value="create_key"'), 'No create form');
    assert_false(str_contains($page['html'], 'href="#create-api-key"'), 'No link to a form that is not there');
    assert_false(stripos($page['html'], 'upgrade') !== false, 'Nothing offers an upgrade that cannot be bought');
});

test('API plan page (#328): earlier keys stay listed and revocable, and only a working key shows as Active', function (): void
{
    $page = g2mlApiPlanPageTest_render('not_included', true);

    assert_contains('Not in plan', $page['html']);
    assert_same(1, substr_count($page['html'], 'bg-success'), 'Only the key owned by user 9 is Active');
    assert_same(2, substr_count($page['html'], 'value="revoke_key"'), 'Both keys can still be revoked');
});

test('API plan page (#328): a plan that cannot be read shows a retry, no create form, and does not call keys Active', function (): void
{
    $page = g2mlApiPlanPageTest_render('check_failed', true);

    assert_contains('We could not check your plan', $page['html']);
    assert_contains('MESSAGE-FOR-check_failed', $page['html']);
    assert_contains('Not checked', $page['html']);
    assert_false(str_contains($page['html'], 'value="create_key"'), 'No create form');
    assert_false(str_contains($page['html'], 'API access is not part of your plan'), 'A fault is not reported as a plan limit');
});

test('API plan page (#328): a key whose owner has left is not shown as Active', function (): void
{
    $page = g2mlApiPlanPageTest_render('allowed', true, 0);

    assert_contains('Owner has left', $page['html']);
    assert_false(str_contains($page['html'], 'bg-success'), 'Neither key is Active');
});

test('API plan page (#328): a key whose owner is no longer an administrator is not shown as Active', function (): void
{
    $page = g2mlApiPlanPageTest_render('allowed', true, 1, 0);

    assert_contains('Owner is not an administrator', $page['html']);
    assert_false(str_contains($page['html'], 'bg-success'), 'Neither key is Active');
});

test('API plan page (#328): a plan with API access shows the create form and a next step when there are no keys', function (): void
{
    $page = g2mlApiPlanPageTest_render('allowed', false);

    assert_contains('value="create_key"', $page['html']);
    assert_contains('href="#create-api-key"', $page['html']);
    assert_false(str_contains($page['html'], 'API access is not part of your plan'));
});
