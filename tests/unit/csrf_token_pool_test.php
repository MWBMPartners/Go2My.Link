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
 * 🧪 Checks — several form tokens per form, a form sent twice, and the
 *    cookie banner's token (#291)
 * ============================================================================
 *
 * Issue #291 found three faults that all came from keeping ONE security token
 * (CSRF token) per form name:
 *
 *   1. A form open in two tabs: sending the older one failed with "Your
 *      session has expired", and what had been typed was lost.
 *   2. A double click on "Create key" on the API keys page: the first
 *      request made the key, the second failed, and the browser showed the
 *      second answer, so the new key was never seen.
 *   3. The cookie banner's fallback form sent "1" in place of its token, so
 *      it was always refused; and with no JavaScript the banner did nothing.
 *
 * WHAT IS CHECKED, AND HOW
 *
 *   - The token functions in web/_functions/security.php are called directly:
 *     several tokens per form name, each usable once, each expiring, with
 *     limits on how many are kept.
 *   - The API keys page is run twice in a separate PHP process, with the same
 *     form posted both times, the way a double click sends it.
 *   - The cookie banner is printed in a separate process and its HTML read.
 *   - The consent endpoint and cookie-consent.js are read as text. If the
 *     "node" program is installed, the script's fallback form is also run
 *     with a tiny stand-in page and the token it sends is checked.
 *
 * WHAT THIS CANNOT DO
 *
 * It does not run a real browser, so how a browser handles a real double
 * click, and what the visitor sees, is not checked here. The consent
 * endpoint needs a database, so it is checked by reading it, not by running
 * it. The node check is skipped where node is not installed (the Docker test
 * images do not have it).
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      #291
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/../web/_functions/security.php';

// ============================================================================
// 🧰 Small helpers (every name starts with g2mlCsrfPoolTest_ so nothing can
// clash with another test file, because every test file shares one process).
// ============================================================================

/**
 * The absolute path of a repository file, given its path from the root.
 */
function g2mlCsrfPoolTest_path(string $relativePath): string
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
}

/**
 * The text of a repository file. Fails the test when it cannot be read.
 */
function g2mlCsrfPoolTest_read(string $relativePath): string
{
    $absolutePath = g2mlCsrfPoolTest_path($relativePath);

    assert_true(is_file($absolutePath), 'Expected file not found: ' . $relativePath);

    $contents = file_get_contents($absolutePath);

    assert_true($contents !== false, 'Could not read: ' . $relativePath);

    return (string) $contents;
}

/**
 * Run a program in a child process with a script file and return what it
 * printed. $interpreter is the program to run (PHP or node).
 *
 * @return array{output: string, exitCode: int}
 */
function g2mlCsrfPoolTest_runChild(string $interpreter, string $scriptText, string $extension): array
{
    $tempBase = tempnam(sys_get_temp_dir(), 'g2ml_csrf_pool_');

    if ($tempBase === false)
    {
        return array('output' => '', 'exitCode' => -1);
    }

    $tempFile = $tempBase . $extension;

    try
    {
        file_put_contents($tempFile, $scriptText);

        $outputLines = array();
        $exitCode    = -1;

        exec(escapeshellarg($interpreter) . ' ' . escapeshellarg($tempFile) . ' 2>&1', $outputLines, $exitCode);

        return array('output' => implode("\n", $outputLines), 'exitCode' => $exitCode);
    }
    finally
    {
        if (is_file($tempFile))
        {
            unlink($tempFile);
        }

        if (is_file($tempBase))
        {
            unlink($tempBase);
        }
    }
}

/**
 * Run PHP code (without the opening tag) in a child process. Fails the test
 * when the child does not exit cleanly or prints a PHP message.
 */
function g2mlCsrfPoolTest_runPhp(string $scriptBody): string
{
    $result = g2mlCsrfPoolTest_runChild(PHP_BINARY, '<?php' . "\n" . $scriptBody, '.php');

    assert_same(0, $result['exitCode'], 'The child process did not finish cleanly: ' . $result['output']);
    assert_true(
        preg_match('/(^|\n)(PHP )?(Warning|Notice|Deprecated|Fatal error|Parse error):/', $result['output']) !== 1,
        'The child process printed a PHP message: ' . $result['output']
    );

    return $result['output'];
}

/**
 * How many tokens are stored for one form name.
 */
function g2mlCsrfPoolTest_storedCount(string $formName): int
{
    if (!isset($_SESSION['_csrf_tokens'][$formName]) || !is_array($_SESSION['_csrf_tokens'][$formName]))
    {
        return 0;
    }

    return count($_SESSION['_csrf_tokens'][$formName]);
}

/**
 * How many tokens are stored altogether.
 */
function g2mlCsrfPoolTest_totalStored(): int
{
    $total = 0;

    if (!isset($_SESSION['_csrf_tokens']) || !is_array($_SESSION['_csrf_tokens']))
    {
        return 0;
    }

    foreach ($_SESSION['_csrf_tokens'] as $tokens)
    {
        if (is_array($tokens))
        {
            $total += count($tokens);
        }
    }

    return $total;
}

// ============================================================================
// (1) Several tokens per form name, each usable once
// ============================================================================

test('CSRF pool #291: a form open in two tabs — the OLDER tab can still be sent', function (): void
{
    $olderTab = g2ml_generateCSRFToken('pool_two_tabs');
    $newerTab = g2ml_generateCSRFToken('pool_two_tabs');

    assert_true(g2ml_validateCSRFToken($olderTab, 'pool_two_tabs'), 'The older tab must still work after the form was opened again');
    assert_true(g2ml_validateCSRFToken($newerTab, 'pool_two_tabs'), 'The newer tab must still work after the older one was sent');
});

test('CSRF pool #291: each token still works once only', function (): void
{
    $first  = g2ml_generateCSRFToken('pool_single_use');
    $second = g2ml_generateCSRFToken('pool_single_use');

    assert_true(g2ml_validateCSRFToken($first, 'pool_single_use'), 'First use succeeds');
    assert_false(g2ml_validateCSRFToken($first, 'pool_single_use'), 'The same token must not work a second time');
    assert_true(g2ml_validateCSRFToken($second, 'pool_single_use'), 'The other token is untouched');
    assert_false(g2ml_validateCSRFToken($second, 'pool_single_use'), 'And it too works once only');
    assert_same(0, g2mlCsrfPoolTest_storedCount('pool_single_use'), 'Nothing is left once both are used');
});

test('CSRF pool #291: five tokens are kept per form name; a sixth pushes out the oldest', function (): void
{
    assert_same(5, G2ML_CSRF_TOKENS_PER_FORM, 'The per-form limit is five, as the issue proposed');

    $tokens = array();

    for ($index = 0; $index < 6; $index++)
    {
        $tokens[] = g2ml_generateCSRFToken('pool_six_tabs');
    }

    assert_same(5, g2mlCsrfPoolTest_storedCount('pool_six_tabs'), 'Only five tokens may be stored for one form name');
    assert_false(g2ml_validateCSRFToken($tokens[0], 'pool_six_tabs'), 'The oldest of six must have been pushed out');

    for ($index = 1; $index < 6; $index++)
    {
        assert_true(g2ml_validateCSRFToken($tokens[$index], 'pool_six_tabs'), 'Token ' . ($index + 1) . ' of six must still work');
    }
});

test('CSRF pool #291: showing a form many times never stores more than five tokens for it', function (): void
{
    for ($index = 0; $index < 40; $index++)
    {
        $unusedToken = g2ml_generateCSRFToken('pool_many_reloads');
    }

    assert_same(5, g2mlCsrfPoolTest_storedCount('pool_many_reloads'), 'Forty reloads must leave five tokens, not forty');
});

test('CSRF pool #291: a wrong or old token does not use up the valid ones', function (): void
{
    $valid = g2ml_generateCSRFToken('pool_wrong_first');

    assert_false(g2ml_validateCSRFToken(str_repeat('0', 64), 'pool_wrong_first'), 'A wrong token is refused');
    assert_false(g2ml_validateCSRFToken('', 'pool_wrong_first'), 'An empty token is refused');
    assert_true(g2ml_validateCSRFToken($valid, 'pool_wrong_first'), 'A refused wrong token must not throw away the visitor\'s real one (another tab)');
});

test('CSRF pool #291: an expired token is refused and removed', function (): void
{
    $expired = g2ml_generateCSRFToken('pool_expired', -10);
    $fresh   = g2ml_generateCSRFToken('pool_expired');

    assert_false(g2ml_validateCSRFToken($expired, 'pool_expired'), 'A token past its expiry time must be refused');
    assert_same(1, g2mlCsrfPoolTest_storedCount('pool_expired'), 'The expired token is gone; only the fresh one is left');
    assert_true(g2ml_validateCSRFToken($fresh, 'pool_expired'), 'The fresh token still works');
});

test('CSRF pool #291: a token for one form name never works for another', function (): void
{
    $token = g2ml_generateCSRFToken('pool_form_one');

    assert_false(g2ml_validateCSRFToken($token, 'pool_form_two'), 'A token is tied to its own form name');
    assert_true(g2ml_validateCSRFToken($token, 'pool_form_one'), 'Trying it against the wrong name does not use it up');
});

test('CSRF pool #291: a session started before #291 (one token, old shape) still works', function (): void
{
    $legacyToken = bin2hex(random_bytes(32));

    $_SESSION['_csrf_tokens']['pool_legacy_shape'] = array(
        'token'   => $legacyToken,
        'expires' => time() + 600,
    );

    assert_true(g2ml_validateCSRFToken($legacyToken, 'pool_legacy_shape'), 'A form shown before the change was deployed must still be accepted');
    assert_false(g2ml_validateCSRFToken($legacyToken, 'pool_legacy_shape'), 'It is still single-use');

    $_SESSION['_csrf_tokens']['pool_legacy_shape'] = array(
        'token'   => $legacyToken,
        'expires' => time() + 600,
    );

    $newToken = g2ml_generateCSRFToken('pool_legacy_shape');

    assert_true(g2ml_validateCSRFToken($legacyToken, 'pool_legacy_shape'), 'Making a new token keeps the old-shape one');
    assert_true(g2ml_validateCSRFToken($newToken, 'pool_legacy_shape'), 'The new token works too');
});

test('CSRF pool #291: broken entries in the store are ignored, not fatal', function (): void
{
    $_SESSION['_csrf_tokens']['pool_broken'] = array(
        'not an entry',
        array('token' => array('nested'), 'expires' => time() + 60),
        array('token' => 'abc', 'expires' => 'soon'),
        array('expires' => time() + 60),
    );

    assert_false(g2ml_validateCSRFToken('abc', 'pool_broken'), 'Nothing in a broken entry may validate');
    assert_same(0, g2mlCsrfPoolTest_storedCount('pool_broken'), 'Broken entries are cleared away');
});

// ============================================================================
// (2) The whole store cannot grow without limit
// ============================================================================

test('CSRF pool #291: tidying removes expired tokens and empty form names', function (): void
{
    $savedStore = $_SESSION['_csrf_tokens'];

    $_SESSION['_csrf_tokens'] = array();

    $keep   = g2ml_generateCSRFToken('pool_prune_keep', 3600);
    $unused = g2ml_generateCSRFToken('pool_prune_short', 60);

    // Tidy as if 10 minutes had passed: the 60-second token is gone.
    g2ml_pruneCSRFTokens(time() + 600);

    assert_false(isset($_SESSION['_csrf_tokens']['pool_prune_short']), 'A form name whose tokens have all expired must be removed');
    assert_same(1, g2mlCsrfPoolTest_storedCount('pool_prune_keep'), 'An unexpired token is kept');
    assert_true(g2ml_validateCSRFToken($keep, 'pool_prune_keep'), 'And it still works');

    $_SESSION['_csrf_tokens'] = $savedStore;
});

test('CSRF pool #291: tidying caps the whole store, dropping the tokens that expire soonest', function (): void
{
    $savedStore = $_SESSION['_csrf_tokens'];

    $_SESSION['_csrf_tokens'] = array();

    $limit = G2ML_CSRF_MAX_STORED_TOKENS;
    $now   = time();
    $extra = 50;

    // One token per form name, each expiring one second later than the one
    // before, so "soonest to expire" is the same as "lowest number".
    for ($index = 0; $index < $limit + $extra; $index++)
    {
        $_SESSION['_csrf_tokens']['pool_cap_' . $index] = array(
            array('token' => 'token-' . $index, 'expires' => $now + 1000 + $index),
        );
    }

    g2ml_pruneCSRFTokens($now);

    assert_same($limit, g2mlCsrfPoolTest_totalStored(), 'The store must be cut down to the limit');
    assert_false(isset($_SESSION['_csrf_tokens']['pool_cap_0']), 'The token expiring soonest is dropped first');
    assert_false(isset($_SESSION['_csrf_tokens']['pool_cap_' . ($extra - 1)]), 'The fiftieth soonest is dropped too');
    assert_true(isset($_SESSION['_csrf_tokens']['pool_cap_' . $extra]), 'The fifty-first is kept');
    assert_true(isset($_SESSION['_csrf_tokens']['pool_cap_' . ($limit + $extra - 1)]), 'The newest is kept');

    $_SESSION['_csrf_tokens'] = $savedStore;
});

test('CSRF pool #291: the limit holds the busiest page (about 1,000 forms) with room to spare', function (): void
{
    // The LinksPage editor: up to 200 links, five small forms each.
    assert_true(G2ML_CSRF_MAX_STORED_TOKENS >= 200 * 5 + 100, 'The store limit must hold one full view of the busiest page');
});

test('CSRF pool #291: two tabs of the biggest LinksPage editor both keep every form working', function (): void
{
    if (!function_exists('exec'))
    {
        echo 'SKIPPED: exec disabled' . PHP_EOL;
        return;
    }

    // Three separate processes are three separate requests sharing one
    // session (passed along as a file): tab one shows the editor, tab two
    // shows it again, then a third page view tidies the store. Every form
    // from BOTH tabs must still validate after that.
    $sessionFile = tempnam(sys_get_temp_dir(), 'g2ml_csrf_pool_session_');
    assert_true($sessionFile !== false, 'Setup: could not make a temporary file');
    file_put_contents($sessionFile, serialize(array()));

    $securityPath = var_export(g2mlCsrfPoolTest_path('web/_functions/security.php'), true);
    $sessionPath  = var_export($sessionFile, true);

    // One editor view: 200 links with five forms each, plus five page-wide forms.
    $renderEditor  = '$_SESSION = unserialize(file_get_contents(' . $sessionPath . '));' . "\n";
    $renderEditor .= 'require ' . $securityPath . ';' . "\n";
    $renderEditor .= '$issued = array();' . "\n";
    $renderEditor .= 'foreach (array(\'linkspage_page_settings_9\', \'linkspage_custom_html_9\', \'linkspage_custom_html_upload_9\', \'linkspage_add_item_9\', \'ajax\') as $name) { $issued[] = array($name, g2ml_generateCSRFToken($name)); }' . "\n";
    $renderEditor .= 'for ($item = 1; $item <= 200; $item++) { foreach (array(\'move_up\', \'move_down\', \'toggle\', \'delete\', \'edit\') as $action) { $name = \'linkspage_item_\' . $action . \'_\' . $item; $issued[] = array($name, g2ml_generateCSRFToken($name)); } }' . "\n";
    $renderEditor .= 'file_put_contents(' . $sessionPath . ', serialize($_SESSION));' . "\n";
    $renderEditor .= 'echo json_encode($issued);' . "\n";

    try
    {
        $firstTab  = json_decode(g2mlCsrfPoolTest_runPhp($renderEditor), true);
        $secondTab = json_decode(g2mlCsrfPoolTest_runPhp($renderEditor), true);

        assert_true(is_array($firstTab) && count($firstTab) === 1005, 'Setup: the first tab must issue 1,005 tokens');
        assert_true(is_array($secondTab) && count($secondTab) === 1005, 'Setup: the second tab must issue 1,005 tokens');

        $check  = '$_SESSION = unserialize(file_get_contents(' . $sessionPath . '));' . "\n";
        $check .= 'require ' . $securityPath . ';' . "\n";
        $check .= '$unused = g2ml_generateCSRFToken(\'another_page\');' . "\n";
        $check .= '$issued = json_decode(' . var_export(json_encode(array_merge($firstTab, $secondTab)), true) . ', true);' . "\n";
        $check .= '$failed = 0; foreach ($issued as $pair) { if (!g2ml_validateCSRFToken($pair[1], $pair[0])) { $failed++; } }' . "\n";
        $check .= 'echo json_encode(array(\'checked\' => count($issued), \'failed\' => $failed));' . "\n";

        $result = json_decode(g2mlCsrfPoolTest_runPhp($check), true);

        assert_true(is_array($result), 'The checking process did not print its answer');
        assert_same(2010, $result['checked'], 'Every form from both tabs is checked');
        assert_same(0, $result['failed'], 'No form from either tab may have been dropped by tidying');
    }
    finally
    {
        unlink($sessionFile);
    }
});

test('CSRF pool #291: the store limit holds two full views of the busiest page with room to spare', function (): void
{
    assert_true(G2ML_CSRF_MAX_STORED_TOKENS >= 2 * (200 * 5 + 5) + 500, 'The store limit must hold two tabs of the biggest editor plus other pages');
});

test('CSRF pool #291: g2ml_generateCSRFToken() tidies the store once per request, before its first token', function (): void
{
    $source = g2mlCsrfPoolTest_read('web/_functions/security.php');

    assert_true(
        preg_match('/function g2ml_generateCSRFToken\(.*?static \$storeTidiedThisRequest = false;.*?g2ml_pruneCSRFTokens\(\);.*?random_bytes\(32\)/s', $source) === 1,
        'g2ml_generateCSRFToken() must call g2ml_pruneCSRFTokens() once per request before it makes a token'
    );
});

test('CSRF pool #291: a new request tidies away a stored store that was over the limit', function (): void
{
    if (!function_exists('exec'))
    {
        echo 'SKIPPED: exec disabled' . PHP_EOL;
        return;
    }

    // A fresh process is a fresh request, so its first g2ml_generateCSRFToken()
    // call tidies the store it was handed.
    $script  = '$_SESSION = array(\'_csrf_tokens\' => array());' . "\n";
    $script .= 'require ' . var_export(g2mlCsrfPoolTest_path('web/_functions/security.php'), true) . ';' . "\n";
    $script .= 'for ($i = 0; $i < G2ML_CSRF_MAX_STORED_TOKENS + 300; $i++) { $_SESSION[\'_csrf_tokens\'][\'row_\' . $i] = array(array(\'token\' => \'t\' . $i, \'expires\' => time() + 100 + $i)); }' . "\n";
    $script .= '$_SESSION[\'_csrf_tokens\'][\'old_form\'] = array(array(\'token\' => \'gone\', \'expires\' => time() - 5));' . "\n";
    $script .= '$token = g2ml_generateCSRFToken(\'new_form\');' . "\n";
    $script .= '$total = 0; foreach ($_SESSION[\'_csrf_tokens\'] as $list) { $total += count($list); }' . "\n";
    $script .= 'echo json_encode(array(\'total\' => $total, \'limit\' => G2ML_CSRF_MAX_STORED_TOKENS, \'oldGone\' => !isset($_SESSION[\'_csrf_tokens\'][\'old_form\']), \'newKept\' => isset($_SESSION[\'_csrf_tokens\'][\'new_form\'])));' . "\n";

    $decoded = json_decode(g2mlCsrfPoolTest_runPhp($script), true);

    assert_true(is_array($decoded), 'The child process did not print its answer');
    assert_same($decoded['limit'] + 1, $decoded['total'], 'The store is tidied to the limit, then the one new token is added');
    assert_true($decoded['oldGone'], 'An expired token from an earlier request is removed');
    assert_true($decoded['newKept'], 'The token made in this request is kept');
});

// ============================================================================
// (3) Remembering a result for a form sent twice
// ============================================================================

test('CSRF results #291: a remembered result comes back for the same token, and only for it', function (): void
{
    $token  = g2ml_generateCSRFToken('pool_results');
    $secret = '{"plaintextKey":"g2ml_secret_value_123"}';

    assert_true(g2ml_validateCSRFToken($token, 'pool_results'), 'Setup: the first send is accepted');
    assert_true(g2ml_csrfRememberResult($token, 'pool_results', 'user:7|org:acme', $secret), 'The result must be stored');

    assert_false(g2ml_validateCSRFToken($token, 'pool_results'), 'The second send of the same token is still refused as a token');
    assert_same($secret, g2ml_csrfRecallResult($token, 'pool_results', 'user:7|org:acme'), 'The second send gets the first send\'s result back');
    assert_same($secret, g2ml_csrfRecallResult($token, 'pool_results', 'user:7|org:acme'), 'A third send gets it too (it stays until it expires)');
    assert_same(null, g2ml_csrfRecallResult(str_repeat('f', 64), 'pool_results', 'user:7|org:acme'), 'Any other token gets nothing');
    assert_same(null, g2ml_csrfRecallResult('', 'pool_results', 'user:7|org:acme'), 'An empty token gets nothing');
    assert_same(null, g2ml_csrfRecallResult($token, 'pool_results_other', 'user:7|org:acme'), 'The same token under another form name gets nothing');
    assert_same(null, g2ml_csrfRecallResult($token, 'pool_results', 'user:8|org:acme'), 'A different user, even with the same token, gets nothing');
    assert_same(null, g2ml_csrfRecallResult($token, 'pool_results', 'user:7|org:other'), 'The same user in a different organisation gets nothing');
    assert_same(null, g2ml_csrfRecallResult($token, 'pool_results', ''), 'An empty owner gets nothing');
    assert_same($secret, g2ml_csrfRecallResult($token, 'pool_results', 'user:7|org:acme'), 'Wrong-owner attempts do not remove the real owner\'s result');
});

test('CSRF results #291: the session holds neither the result nor the token in readable form', function (): void
{
    $token  = g2ml_generateCSRFToken('pool_results_sealed');
    $secret = 'g2ml_readable_secret_' . bin2hex(random_bytes(8));

    assert_true(g2ml_validateCSRFToken($token, 'pool_results_sealed'), 'Setup: the first send is accepted');
    assert_true(g2ml_csrfRememberResult($token, 'pool_results_sealed', 'user:7|org:acme', $secret), 'The result must be stored');

    $stored = serialize($_SESSION);

    assert_false(str_contains($stored, $secret), 'The remembered result must be stored encrypted');
    assert_false(str_contains($stored, $token), 'The used token must not be stored at all');
});

test('CSRF results #291: a remembered result expires', function (): void
{
    $token = g2ml_generateCSRFToken('pool_results_expiry');

    assert_true(g2ml_validateCSRFToken($token, 'pool_results_expiry'), 'Setup: the first send is accepted');
    assert_true(g2ml_csrfRememberResult($token, 'pool_results_expiry', 'user:7|org:acme', 'value', -1), 'Stored with a time already past');
    assert_same(null, g2ml_csrfRecallResult($token, 'pool_results_expiry', 'user:7|org:acme'), 'An expired result must not come back');
    assert_false(isset($_SESSION['_csrf_results']['pool_results_expiry']), 'And it is cleared away');
});

test('CSRF results #291: at most five results are kept per form name', function (): void
{
    for ($index = 0; $index < 12; $index++)
    {
        $token = g2ml_generateCSRFToken('pool_results_cap');
        $used  = g2ml_validateCSRFToken($token, 'pool_results_cap');
        assert_true($used, 'Setup: each send is accepted');
        assert_true(g2ml_csrfRememberResult($token, 'pool_results_cap', 'user:7|org:acme', 'value ' . $index), 'Each result is stored');
    }

    assert_same(5, count($_SESSION['_csrf_results']['pool_results_cap']), 'Twelve results must leave five, not twelve');
});

test('CSRF results #291: a result cannot be remembered without an owner', function (): void
{
    $token = g2ml_generateCSRFToken('pool_results_no_owner');
    $used  = g2ml_validateCSRFToken($token, 'pool_results_no_owner');

    assert_true($used, 'Setup: the send is accepted');
    assert_false(g2ml_csrfRememberResult($token, 'pool_results_no_owner', '', 'value'), 'An empty owner must be refused, so a result is always tied to someone');
});

test('CSRF results #291: signing in, or losing a signed-in session, forgets every remembered result', function (): void
{
    $token = g2ml_generateCSRFToken('pool_results_forget');
    $used  = g2ml_validateCSRFToken($token, 'pool_results_forget');

    assert_true($used, 'Setup: the send is accepted');
    assert_true(g2ml_csrfRememberResult($token, 'pool_results_forget', 'user:7|org:acme', 'value'), 'Setup: the result is stored');

    g2ml_csrfForgetResults();

    assert_false(isset($_SESSION['_csrf_results']), 'g2ml_csrfForgetResults() must clear every remembered result');
    assert_same(null, g2ml_csrfRecallResult($token, 'pool_results_forget', 'user:7|org:acme'), 'Nothing comes back after it');

    $auth = g2mlCsrfPoolTest_read('web/_functions/auth.php');
    assert_true(
        preg_match('/function loginUser\(.*?session_regenerate_id\(true\);.*?g2ml_csrfForgetResults\(\);.*?createUserSession\(/s', $auth) === 1,
        'loginUser() must forget remembered results when it starts the new session'
    );

    $pageInit = g2mlCsrfPoolTest_read('web/_includes/page_init.php');
    assert_true(
        preg_match('/!validateUserSession\(\)\)\s*\{.*?unset\(\$_SESSION\[\'email_verified\'\], \$_SESSION\[\'session_token\'\]\);.*?g2ml_csrfForgetResults\(\);/s', $pageInit) === 1,
        'page_init.php must forget remembered results when it drops an invalid signed-in session'
    );
});

// ============================================================================
// (4) The API keys page, with the create form sent twice
// ============================================================================

/**
 * A child-process script that runs the API keys page twice with the same
 * posted create form (a double click), then the same form as a DIFFERENT
 * signed-in user, then once more with a token that was never issued, and
 * prints the four pages and how many keys were made.
 */
function g2mlCsrfPoolTest_apiKeysDoubleSubmitScript(): string
{
    $script  = '$root = ' . var_export(dirname(__DIR__, 2), true) . ';' . "\n";
    $script .= '$_SESSION = array();' . "\n";
    $script .= 'require $root . \'/web/_functions/security.php\';' . "\n";
    $script .= 'require $root . \'/web/_includes/accessibility.php\';' . "\n";
    $script .= '$GLOBALS[\'keysMade\'] = 0;' . "\n";
    $script .= '$GLOBALS[\'signedInUser\'] = 7;' . "\n";
    $script .= 'function getCurrentUser() { return array(\'orgHandle\' => \'acme\', \'userUID\' => $GLOBALS[\'signedInUser\']); }' . "\n";
    $script .= 'function canManageOrg($org) { return true; }' . "\n";
    $script .= 'function g2ml_apiValidScopesList() { return array(\'urls:read\', \'urls:write\'); }' . "\n";
    $script .= 'function g2ml_apiListKeys($org) { return array(); }' . "\n";
    $script .= 'function g2ml_apiValidateScopesInput($scopes) { return array(\'urls:read\'); }' . "\n";
    $script .= 'function g2ml_apiGenerateKey() { $GLOBALS[\'keysMade\']++; return array(\'success\' => true, \'plaintextKey\' => \'g2ml_live_key_number_\' . $GLOBALS[\'keysMade\'], \'apiKeyPrefix\' => \'g2ml_live\'); }' . "\n";
    $script .= '$_SERVER[\'REQUEST_METHOD\'] = \'POST\';' . "\n";
    $script .= '$_POST = array(' . "\n";
    $script .= '    \'_csrf_token\' => g2ml_generateCSRFToken(\'api_keys_create\'),' . "\n";
    $script .= '    \'action_type\' => \'create_key\',' . "\n";
    $script .= '    \'key_name\'    => \'Build server\',' . "\n";
    $script .= '    \'scopes\'      => array(\'urls:read\'),' . "\n";
    $script .= '    \'expires_at\'  => \'\',' . "\n";
    $script .= ');' . "\n";
    $script .= '$pages = array();' . "\n";
    $script .= 'ob_start(); require $root . \'/web/Go2My.Link/_admin/public_html/pages/api-keys/index.php\'; $pages[] = ob_get_clean();' . "\n";
    $script .= 'ob_start(); require $root . \'/web/Go2My.Link/_admin/public_html/pages/api-keys/index.php\'; $pages[] = ob_get_clean();' . "\n";
    $script .= '$GLOBALS[\'signedInUser\'] = 8;' . "\n";
    $script .= 'ob_start(); require $root . \'/web/Go2My.Link/_admin/public_html/pages/api-keys/index.php\'; $pages[] = ob_get_clean();' . "\n";
    $script .= '$GLOBALS[\'signedInUser\'] = 7;' . "\n";
    $script .= '$_POST[\'_csrf_token\'] = str_repeat(\'a\', 64);' . "\n";
    $script .= 'ob_start(); require $root . \'/web/Go2My.Link/_admin/public_html/pages/api-keys/index.php\'; $pages[] = ob_get_clean();' . "\n";
    $script .= 'echo json_encode(array(\'pages\' => $pages, \'keysMade\' => $GLOBALS[\'keysMade\']));' . "\n";

    return $script;
}

test('API keys #291: a double-clicked "Create key" shows the new key on the second answer and makes only one key', function (): void
{
    if (!function_exists('exec'))
    {
        echo 'SKIPPED: exec disabled' . PHP_EOL;
        return;
    }

    $decoded = json_decode(g2mlCsrfPoolTest_runPhp(g2mlCsrfPoolTest_apiKeysDoubleSubmitScript()), true);

    assert_true(is_array($decoded) && isset($decoded['pages']) && count($decoded['pages']) === 4, 'The child process did not print the four pages');

    $firstPage  = (string) $decoded['pages'][0];
    $secondPage = (string) $decoded['pages'][1];
    $otherUser  = (string) $decoded['pages'][2];
    $thirdPage  = (string) $decoded['pages'][3];

    assert_contains('g2ml_live_key_number_1', $firstPage, 'Setup: the first request shows the key it made');

    assert_false(str_contains($secondPage, 'Session expired'), 'The second copy of the form must not answer "Session expired"');
    assert_contains('g2ml_live_key_number_1', $secondPage, 'The second copy must show the key the first copy made, because that is the answer the browser shows');
    assert_contains('API Key Created: Build server', $secondPage, 'The second copy shows the one-time key panel');
    assert_same(1, $decoded['keysMade'], 'Only one key may be made, however many copies are sent');

    assert_contains('Session expired', $otherUser, 'A different user sending the same form must be refused');
    assert_false(str_contains($otherUser, 'g2ml_live_key_number_'), 'A different user must never see the first user\'s new key');

    assert_contains('Session expired', $thirdPage, 'A token that was never issued is still refused');
    assert_false(str_contains($thirdPage, 'g2ml_live_key_number_'), 'A refused token must never show a key');
});

// ============================================================================
// (5) The cookie banner sends a real token, with or without JavaScript
// ============================================================================

/**
 * Print the cookie banner in a separate process, with a known token, and
 * return its HTML.
 */
function g2mlCsrfPoolTest_bannerHtml(): string
{
    $includes = g2mlCsrfPoolTest_path('web/_includes');

    $script  = 'define(\'G2ML_INCLUDES\', ' . var_export($includes, true) . ');' . "\n";
    $script .= 'function getSetting($key, $default = null) { return $default; }' . "\n";
    $script .= 'function g2ml_hasValidConsent() { return false; }' . "\n";
    $script .= 'function g2ml_generateCSRFToken($name) { return \'banner-token-\' . $name; }' . "\n";
    $script .= 'function g2ml_mainSiteURL($path) { return $path; }' . "\n";
    $script .= 'ob_start();' . "\n";
    $script .= 'require G2ML_INCLUDES . \'/cookie_banner.php\';' . "\n";
    $script .= 'echo json_encode(array(\'html\' => ob_get_clean()));' . "\n";

    $decoded = json_decode(g2mlCsrfPoolTest_runPhp($script), true);

    if (!is_array($decoded) || !isset($decoded['html']))
    {
        return '';
    }

    return (string) $decoded['html'];
}

test('cookie banner #291: without JavaScript, its buttons send a real form with the token', function (): void
{
    if (!function_exists('exec'))
    {
        echo 'SKIPPED: exec disabled' . PHP_EOL;
        return;
    }

    $html = g2mlCsrfPoolTest_bannerHtml();

    assert_true(preg_match('~<form id="g2ml-cookie-banner-form" method="post" action="/api/consent/">(.*?)</form>~s', $html, $formMatch) === 1, 'The banner must hold a form that posts to /api/consent/');

    $form = $formMatch[1];

    assert_true(preg_match('~<input type="hidden" id="g2ml-consent-csrf" name="csrf_token" value="banner-token-cookie_consent">~', $form) === 1, 'The form must carry the token under the name the endpoint reads');
    assert_true(preg_match('~<button type="submit" name="consent_choice" value="all"[^>]*id="g2ml-cookie-accept-all"~', $form) === 1, '"Accept All" must be a submit button that sends consent_choice=all');
    assert_true(preg_match('~<button type="submit" name="consent_choice" value="essential"[^>]*id="g2ml-cookie-reject"~', $form) === 1, '"Essential Only" must be a submit button that sends consent_choice=essential');
});

test('cookie banner #291: the consent endpoint understands the form\'s choice and sends a refused form back', function (): void
{
    $source = g2mlCsrfPoolTest_read('web/Go2My.Link/public_html/api/consent/index.php');

    assert_contains('$data[\'consent_choice\']', $source, 'The endpoint must read consent_choice');
    assert_true(preg_match('/if \(\$bannerChoice === \'all\'\)\s*\{\s*\$given = true;/', $source) === 1, '"all" must give every optional category');
    assert_true(preg_match('/elseif \(\$bannerChoice === \'essential\'\)\s*\{\s*\$given = false;/', $source) === 1, '"essential" must refuse every optional category');
    assert_true(preg_match('/if \(!is_string\(\$csrfToken\)\)\s*\{\s*\$csrfToken = \'\';/', $source) === 1, 'A token that is not text must be treated as no token');
    assert_true(
        preg_match('/g2ml_validateCSRFToken\(\$csrfToken, \'cookie_consent\'\)\).*?if \(!\$isJSON\)\s*\{\s*header\(\'Location: \' \. \$referer, true, 303\);\s*exit;/s', $source) === 1,
        'A refused form sent by a browser must be sent back to its page, not shown raw JSON'
    );

    $csrfPosition    = strpos($source, 'g2ml_validateCSRFToken($csrfToken');
    $recordPosition  = strpos($source, 'g2ml_recordConsent(');
    $refererPosition = strpos($source, '$referer = g2ml_safeSameHostReturnPath(');

    assert_true($csrfPosition !== false && $recordPosition !== false && $csrfPosition < $recordPosition, 'The token must be checked before anything is recorded');
    assert_true($refererPosition !== false && $refererPosition < $csrfPosition, 'The address to go back to must be worked out before the token check uses it');
});

test('cookie banner #291: cookie-consent.js sends the token unchanged in its fallback form, and stops the real form', function (): void
{
    $script = g2mlCsrfPoolTest_read('web/Go2My.Link/public_html/js/cookie-consent.js');

    assert_true(preg_match('/function submitViaForm\(data\) \{(.*?)\n    \}\n/s', $script, $bodyMatch) === 1, 'submitViaForm() was not found');

    $body = $bodyMatch[1];

    assert_true(
        preg_match('/if \(key === \'csrf_token\'\) \{\s*input\.value = data\[key\];\s*\} else if \(data\[key\]\) \{\s*input\.value = \'1\';/', $body) === 1,
        'submitViaForm() must send csrf_token as it is, and only turn the yes/no answers into 1 or 0'
    );

    $preventCount = preg_match_all('/addEventListener\(\'click\', function \(event\) \{\s*event\.preventDefault\(\);\s*submitConsent\(/', $script);
    assert_same(2, $preventCount, 'Both banner buttons are submit buttons now, so both click handlers must stop the normal form send');
});

test('cookie banner #291: running the fallback form in node sends the page\'s real token (skipped without node)', function (): void
{
    if (!function_exists('exec'))
    {
        echo 'SKIPPED: exec disabled' . PHP_EOL;
        return;
    }

    $nodePath   = '';
    $whichLines = array();
    $whichCode  = -1;

    exec('command -v node 2>/dev/null', $whichLines, $whichCode);

    if ($whichCode === 0 && count($whichLines) > 0)
    {
        $nodePath = trim($whichLines[0]);
    }

    if ($nodePath === '')
    {
        echo 'SKIPPED: node is not installed' . PHP_EOL;
        return;
    }

    $consentScript = g2mlCsrfPoolTest_read('web/Go2My.Link/public_html/js/cookie-consent.js');

    // A tiny stand-in page: just the elements and calls the script uses. There
    // is no fetch(), so the script goes straight to its fallback form.
    $harness = <<<'JS'
var elements = {};
var submitted = [];
function makeElement(id) {
    var element = {
        id: id, value: '', checked: false, style: {}, offsetHeight: 40, children: [], listeners: {},
        appendChild: function (child) { this.children.push(child); },
        addEventListener: function (name, handler) { this.listeners[name] = handler; },
        submit: function () { submitted.push(this); },
        focus: function () {}
    };
    elements[id] = element;
    return element;
}
makeElement('g2ml-cookie-banner');
makeElement('g2ml-cookie-accept-all');
makeElement('g2ml-cookie-reject');
makeElement('g2ml-consent-csrf').value = 'real-token-from-page';
global.window = { addEventListener: function () {} };
// Newer node versions have their own fetch(); remove it so the script uses its fallback form.
global.fetch = undefined;
global.document = {
    getElementById: function (id) { if (elements[id]) { return elements[id]; } return null; },
    createElement: function () { return makeElement('created'); },
    documentElement: { style: { setProperty: function () {}, removeProperty: function () {} } },
    body: { appendChild: function () {} }
};
JS;

    $harness .= "\n" . $consentScript . "\n";
    $harness .= <<<'JS'
var prevented = false;
elements['g2ml-cookie-accept-all'].listeners.click({ preventDefault: function () { prevented = true; } });
var sent = {};
submitted[0].children.forEach(function (input) { sent[input.name] = input.value; });
console.log(JSON.stringify({ prevented: prevented, forms: submitted.length, sent: sent }));
JS;

    $result = g2mlCsrfPoolTest_runChild($nodePath, $harness, '.js');

    assert_same(0, $result['exitCode'], 'node did not run the script cleanly: ' . $result['output']);

    $decoded = json_decode($result['output'], true);

    assert_true(is_array($decoded), 'node did not print its answer: ' . $result['output']);
    assert_true($decoded['prevented'], 'The click must stop the real form being sent as well');
    assert_same(1, $decoded['forms'], 'Exactly one fallback form is sent');
    assert_same('real-token-from-page', $decoded['sent']['csrf_token'], 'The fallback form must send the page\'s real token, not "1"');
    assert_same('1', $decoded['sent']['analytics'], 'The yes/no answers still go as 1 or 0');
    assert_same('1', $decoded['sent']['essential'], 'Essential is always yes');
});

test('consent return path #291: only a path on the same host comes back; tricks give the home page', function (): void
{
    $host = 'go2my.link';

    assert_same('/pricing?plan=pro', g2ml_safeSameHostReturnPath('https://go2my.link/pricing?plan=pro#top', $host), 'A same-host page comes back as its path and query, without the #part');
    assert_same('/ok', g2ml_safeSameHostReturnPath('https://GO2MY.LINK/ok', $host), 'Capital letters in the host do not matter');
    assert_same('/', g2ml_safeSameHostReturnPath('https://go2my.link', $host), 'No path means the home page');
    assert_same('/x', g2ml_safeSameHostReturnPath('http://localhost:8080/x', 'localhost:8080'), 'A port must match the request\'s own');

    $tricks = array(
        null,
        '',
        '/\\evil.com',
        'https://go2my.link/\\evil.com',
        'https://go2my.link\\@evil.com/',
        'https:evil.com',
        'https:/evil.com',
        '//evil.com/',
        'https://evil.com/',
        'https://go2my.link.evil.com/',
        'https://user@go2my.link/',
        'https://user:pass@go2my.link/',
        'https://go2my.link//evil.com',
        'https://admin.go2my.link/',
        'https://go2my.link:8443/',
        'javascript:alert(1)',
        'ftp://go2my.link/',
        "https://go2my.link/\r\nSet-Cookie: x=1",
        "https://go2my.link/\t/evil.com",
        'https://go2my.link/has space',
        '/pricing',
    );

    foreach ($tricks as $referer)
    {
        assert_same('/', g2ml_safeSameHostReturnPath($referer, $host), 'Must give the home page for: ' . var_export($referer, true));
    }

    assert_same('/', g2ml_safeSameHostReturnPath('https://go2my.link/ok', ''), 'No request host means the home page');
});

test('consent return path #291: the consent endpoint uses it, with no list of full addresses left', function (): void
{
    $source = g2mlCsrfPoolTest_read('web/Go2My.Link/public_html/api/consent/index.php');

    assert_contains('$referer = g2ml_safeSameHostReturnPath($refererHeader, $requestHost);', $source, 'The endpoint must work out the return path with g2ml_safeSameHostReturnPath()');
    assert_false(str_contains($source, '$allowedHosts'), 'The old list of hosts, which let a full address through unchecked, must be gone');
    assert_same(2, substr_count($source, "header('Location: ' . \$referer"), 'Both redirects (accepted and refused) must use that path');
});
