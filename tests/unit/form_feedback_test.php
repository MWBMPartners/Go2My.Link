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
 * 🧪 Checks — form feedback, kept text and confirm boxes (#286)
 * ============================================================================
 *
 * Issue #286 fixed four problems with the site's forms:
 *
 *   1. Only one form showed progress, and any other form could be sent twice.
 *      A shared script, js/forms.js, now switches the buttons off and shows
 *      "Working…". Its translated label travels in a data attribute on the
 *      script tag that web/_includes/footer.php prints.
 *   2. The contact form, the API key form and the LinksPage "Add a Link" form
 *      lost what the person had typed after a failed send.
 *   3. The Members page changed a role the moment its dropdown moved. It now
 *      has a "Change role" button and a confirm box.
 *   4. Unpublishing a LinksPage had no confirm box.
 *
 * WHAT IS CHECKED, AND HOW
 *
 * The pages cannot be run inside this test process, because they need a
 * database and many global functions. So there are two kinds of check:
 *
 *   - Reading the page's source text, for the parts that can be seen there
 *     (the footer's script tag, an onsubmit attribute, a missing onchange).
 *   - Running the real page file in a separate PHP process with the few
 *     functions it needs replaced by simple stand-ins, then reading the HTML
 *     it prints. This proves what the visitor would actually see: their own
 *     text back in the right fields, escaped exactly once.
 *
 * A separate process is used so the stand-in functions never leak into the
 * other tests in this run.
 *
 * WHAT THIS CANNOT DO
 *
 * It does not run js/forms.js (this suite has no JavaScript engine), so what
 * the script does in a browser is not tested here. It does not prove the seed
 * file has been imported into a real database either.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.0.0 — #286
 * ============================================================================
 */

declare(strict_types=1);

// ============================================================================
// 🧰 Small helpers (every name starts with g2mlFormFeedbackTest_ so nothing
// can clash with another test file, because every test file shares one PHP
// process).
// ============================================================================

/**
 * The absolute path of a file in the repository, given its path from the root.
 */
function g2mlFormFeedbackTest_path(string $relativePath): string
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
}

/**
 * The text of a repository file. Fails the test when it cannot be read.
 */
function g2mlFormFeedbackTest_read(string $relativePath): string
{
    $absolutePath = g2mlFormFeedbackTest_path($relativePath);

    assert_true(is_file($absolutePath), 'Expected file not found: ' . $relativePath);

    $contents = file_get_contents($absolutePath);

    assert_true($contents !== false, 'Could not read: ' . $relativePath);

    return (string) $contents;
}

/**
 * Run a PHP snippet in a child process and return what it printed.
 *
 * @param  string $scriptBody  PHP code without the opening tag.
 * @return array{output: string, exitCode: int}
 */
function g2mlFormFeedbackTest_runChild(string $scriptBody): array
{
    $tempFile = tempnam(sys_get_temp_dir(), 'g2ml_form_feedback_');

    if ($tempFile === false)
    {
        return array('output' => '', 'exitCode' => -1);
    }

    try
    {
        file_put_contents($tempFile, '<?php' . "\n" . $scriptBody);

        $outputLines = array();
        $exitCode    = -1;

        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tempFile) . ' 2>&1', $outputLines, $exitCode);

        return array('output' => implode("\n", $outputLines), 'exitCode' => $exitCode);
    }
    finally
    {
        unlink($tempFile);
    }
}

/**
 * Whether the child-process checks can run here. Some hosts switch exec() off.
 */
function g2mlFormFeedbackTest_childAvailable(): bool
{
    return function_exists('exec');
}

/**
 * The PHP lines every page-rendering child process starts with: the real
 * security and form-field helpers, a fresh session array, and the repository
 * root in $root.
 */
function g2mlFormFeedbackTest_childPreamble(): string
{
    $script  = '$root = ' . var_export(dirname(__DIR__, 2), true) . ';' . "\n";
    $script .= '$_SESSION = array();' . "\n";
    $script .= 'require $root . \'/web/_functions/security.php\';' . "\n";
    $script .= 'require $root . \'/web/_includes/accessibility.php\';' . "\n";

    return $script;
}

/**
 * The HTML a page printed, or a failed assertion that shows the child's
 * output when it did not exit cleanly.
 */
function g2mlFormFeedbackTest_renderedHtml(string $scriptBody): string
{
    $result = g2mlFormFeedbackTest_runChild($scriptBody);

    assert_same(0, $result['exitCode'], 'The page did not render cleanly: ' . $result['output']);
    assert_true(
        preg_match('/(^|\n)(PHP )?(Warning|Notice|Deprecated|Fatal error|Parse error):/', $result['output']) !== 1,
        'The page printed a PHP message: ' . $result['output']
    );

    return $result['output'];
}

/**
 * What the page should print for a typed value: escaped once.
 */
function g2mlFormFeedbackTest_once(string $typed): string
{
    return htmlspecialchars($typed, ENT_QUOTES, 'UTF-8');
}

/**
 * The text of the first <form> that holds the given marker text, from its
 * opening tag to its closing tag. Returns an empty string when none does.
 */
function g2mlFormFeedbackTest_formContaining(string $html, string $marker): string
{
    $matchCount = preg_match_all('/<form\b.*?<\/form>/s', $html, $matches);

    if ($matchCount === false || $matchCount === 0)
    {
        return '';
    }

    foreach ($matches[0] as $formHtml)
    {
        if (str_contains($formHtml, $marker))
        {
            return $formHtml;
        }
    }

    return '';
}

/**
 * The text a confirm box would show, taken from a form's onsubmit attribute.
 * The attribute is HTML-decoded, then the JavaScript string inside
 * return confirm("..."); is decoded as JSON, the same two steps a browser
 * takes. Returns null when the form has no such attribute.
 */
function g2mlFormFeedbackTest_confirmText(string $formHtml): ?string
{
    if (preg_match('/onsubmit="([^"]*)"/', $formHtml, $attributeMatch) !== 1)
    {
        return null;
    }

    $handler = html_entity_decode($attributeMatch[1], ENT_QUOTES, 'UTF-8');

    if (preg_match('/^return confirm\((".*")\);$/s', $handler, $handlerMatch) !== 1)
    {
        return null;
    }

    $decoded = json_decode($handlerMatch[1], true);

    if (!is_string($decoded))
    {
        return null;
    }

    return $decoded;
}

// ============================================================================
// (1) The shared script, and how every page loads it
// ============================================================================

test('form feedback: footer.php loads js/forms.js with defer and a translated label (#286)', function (): void
{
    $footer = g2mlFormFeedbackTest_read('web/_includes/footer.php');

    // The tag holds a PHP block with its own ">" characters, so the match runs
    // up to "defer></script>" instead of stopping at the first ">".
    $found = preg_match('/<script src="\/js\/forms\.js"(?:(?!<\/script>).)*\bdefer><\/script>/s', $footer, $tag);

    assert_same(1, $found, 'footer.php must print a <script src="/js/forms.js" ... defer> tag');

    assert_contains('data-g2ml-working-label="', $tag[0], 'The label must travel in a data attribute on the script tag');
    assert_contains("__('common.working')", $tag[0], 'The label must come from the translation system');
    assert_contains('htmlspecialchars(', $tag[0], 'The label must be escaped where it is printed');

    $appPosition   = strpos($footer, '<script src="/js/app.js" defer></script>');
    $formsPosition = strpos($footer, '<script src="/js/forms.js"');

    assert_true($appPosition !== false && $formsPosition !== false && $appPosition < $formsPosition, 'forms.js must load after app.js, in the same custom-scripts block');
});

test('form feedback: forms.js carries the behaviour the brief asks for (#286)', function (): void
{
    $script = g2mlFormFeedbackTest_read('web/Go2My.Link/public_html/js/forms.js');

    $expectedPieces = array(
        "document.addEventListener('submit', handleSubmit, false)" => 'listens for every form submit, after inline onsubmit confirms (bubble phase, not capture)',
        "button.setAttribute('autocomplete', 'off')" => 'stops Firefox keeping a button disabled after Back',
        'event.defaultPrevented'              => 'leaves a cancelled submit alone, so confirm boxes still win',
        "setAttribute('aria-busy', 'true')"   => 'marks the form as busy for assistive technology',
        'data-g2ml-no-busy'                   => 'lets a form opt out',
        'data-g2ml-working-label'             => 'reads the translated label from the script tag',
        "addEventListener('pageshow'"         => 'frees the buttons when a page comes back from the back-forward cache',
        'global-status'                       => 'announces the change in the polite live region',
        'copyButtonPairToHiddenField'         => 'keeps a named submit button\'s name and value',
    );

    foreach ($expectedPieces as $piece => $reason)
    {
        assert_contains($piece, $script, 'forms.js should contain ' . $piece . ' (' . $reason . ')');
    }

    assert_false(str_contains($script, 'jQuery'), 'forms.js must be plain JavaScript, with no jQuery');
    assert_false(str_contains($script, '=>'), 'forms.js must not use arrow functions (house style)');
});

test('form feedback: only the homepage shorten form opts out of the shared script (#286)', function (): void
{
    $pageRoots = array(
        g2mlFormFeedbackTest_path('web/Go2My.Link/public_html/pages'),
        g2mlFormFeedbackTest_path('web/Go2My.Link/_admin/public_html/pages'),
    );

    $optedOut = array();

    foreach ($pageRoots as $pageRoot)
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($pageRoot, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo)
        {
            if ($fileInfo->getExtension() !== 'php')
            {
                continue;
            }

            $contents = file_get_contents($fileInfo->getPathname());

            if ($contents !== false && str_contains($contents, 'data-g2ml-no-busy'))
            {
                $optedOut[] = basename($fileInfo->getPathname());
            }
        }
    }

    assert_same(array('home.php'), $optedOut, 'Only the homepage shorten form handles its own progress; no form here downloads a file by submitting');

    $home = g2mlFormFeedbackTest_read('web/Go2My.Link/public_html/pages/home.php');

    assert_true(preg_match('/<form[^>]*id="shorten-form"[^>]*data-g2ml-no-busy="true"/s', $home) === 1, 'The shorten form must carry data-g2ml-no-busy="true"');
});

// ============================================================================
// (2) Typed text survives a failed send
// ============================================================================

test('form feedback: the contact form hands every posted value back, escaped once (#286)', function (): void
{
    $source = g2mlFormFeedbackTest_read('web/Go2My.Link/public_html/pages/contact/index.php');

    foreach (array('contact_name', 'contact_email', 'contact_subject', 'contact_message') as $fieldName)
    {
        assert_contains("'value'", $source, 'The contact form must pass a value to formField()');
        assert_true(
            preg_match('/\'name\'\s*=>\s*\'' . $fieldName . '\'.*?\'value\'\s*=>\s*\$postedValues\[\'' . $fieldName . '\'\]/s', $source) === 1,
            'The ' . $fieldName . ' field must pass its posted value back to formField()'
        );
    }

    assert_contains('is_string($_POST[$postedKey])', $source, 'Posted values must be read with an is_string() guard');
    assert_false(str_contains($source, 'htmlspecialchars($postedValues'), 'The page must not escape before formField(), which escapes once itself');
});

test('form feedback: a failed contact send shows the visitor their own text, escaped once (#286)', function (): void
{
    if (!g2mlFormFeedbackTest_childAvailable())
    {
        echo 'SKIPPED: exec disabled' . PHP_EOL;
        return;
    }

    $typedName    = 'O\'Brien "Q" <b>&amp;';
    $typedSubject = 'Sub <i>ject</i> & more';
    $typedMessage = "Line one </textarea><script>alert(1)</script>\nLine two 'x' \"y\" &amp;";

    $script  = g2mlFormFeedbackTest_childPreamble();
    $script .= 'function getSetting(string $key, $default = \'\') { return $default; }' . "\n";
    $script .= 'function logActivity() { }' . "\n";
    $script .= '$_SERVER[\'REQUEST_METHOD\'] = \'POST\';' . "\n";
    $script .= '$_POST = array(' . "\n";
    $script .= '    \'_csrf_token\'     => g2ml_generateCSRFToken(\'contact_form\'),' . "\n";
    $script .= '    \'contact_name\'    => ' . var_export($typedName, true) . ',' . "\n";
    $script .= '    \'contact_email\'   => \'not-an-email\',' . "\n";
    $script .= '    \'contact_subject\' => ' . var_export($typedSubject, true) . ',' . "\n";
    $script .= '    \'contact_message\' => ' . var_export($typedMessage, true) . ',' . "\n";
    $script .= ');' . "\n";
    $script .= 'ob_start();' . "\n";
    $script .= 'require $root . \'/web/Go2My.Link/public_html/pages/contact/index.php\';' . "\n";
    $script .= 'echo ob_get_clean();' . "\n";

    $html = g2mlFormFeedbackTest_renderedHtml($script);

    assert_contains('Please enter a valid email address.', $html, 'The send must have failed on the email address');
    assert_contains('id="contact-name"', $html);
    assert_contains('value="' . g2mlFormFeedbackTest_once($typedName) . '"', $html, 'The name must come back, escaped once');
    assert_contains('value="not-an-email"', $html, 'The email must come back');
    assert_contains('value="' . g2mlFormFeedbackTest_once($typedSubject) . '"', $html, 'The subject must come back, escaped once');
    assert_contains('>' . g2mlFormFeedbackTest_once($typedMessage) . '</textarea>', $html, 'The message must come back inside the textarea, escaped once');

    assert_false(str_contains($html, g2mlFormFeedbackTest_once(g2mlFormFeedbackTest_once($typedName))), 'The name must not be escaped twice');
    assert_false(str_contains($html, '<script>alert(1)</script>'), 'Typed markup must never reach the page unescaped');
});

test('form feedback: a first visit to the contact form shows empty fields (#286)', function (): void
{
    if (!g2mlFormFeedbackTest_childAvailable())
    {
        echo 'SKIPPED: exec disabled' . PHP_EOL;
        return;
    }

    $script  = g2mlFormFeedbackTest_childPreamble();
    $script .= 'function getSetting(string $key, $default = \'\') { return $default; }' . "\n";
    $script .= '$_SERVER[\'REQUEST_METHOD\'] = \'GET\';' . "\n";
    $script .= 'ob_start();' . "\n";
    $script .= 'require $root . \'/web/Go2My.Link/public_html/pages/contact/index.php\';' . "\n";
    $script .= 'echo ob_get_clean();' . "\n";

    $html = g2mlFormFeedbackTest_renderedHtml($script);

    assert_contains('id="contact-name"', $html);
    assert_true(preg_match('/id="contact-name"[^>]*value=""/', $html) === 1, 'The name field must start empty');
    assert_contains('></textarea>', $html, 'The message box must start empty');
});

/**
 * The key name the API key tests type: quotes, angle brackets and an
 * ampersand, so a missing or doubled escape shows up.
 */
function g2mlFormFeedbackTest_typedKeyName(): string
{
    return 'My \'key\' "q" <b>&amp;';
}

/**
 * A child-process script that renders the API keys page after one create
 * attempt. $createSucceeds picks what the stand-in key maker answers.
 */
function g2mlFormFeedbackTest_apiKeysScript(bool $createSucceeds): string
{
    $script  = g2mlFormFeedbackTest_childPreamble();
    $script .= 'function getCurrentUser() { return array(\'orgHandle\' => \'acme\', \'userUID\' => 7); }' . "\n";
    $script .= 'function canManageOrg($org) { return true; }' . "\n";
    $script .= 'function g2ml_apiValidScopesList() { return array(\'urls:read\', \'urls:write\', \'analytics:read\'); }' . "\n";
    $script .= 'function g2ml_apiListKeys($org) { return array(); }' . "\n";

    if ($createSucceeds)
    {
        $script .= 'function g2ml_apiValidateScopesInput($scopes) { return array(\'urls:read\'); }' . "\n";
        $script .= 'function g2ml_apiGenerateKey() { return array(\'success\' => true, \'plaintextKey\' => \'g2ml_test_key\', \'apiKeyPrefix\' => \'g2ml_test\'); }' . "\n";
    }
    else
    {
        // No valid permission comes back, so the page answers "select at least
        // one permission" and shows the form again.
        $script .= 'function g2ml_apiValidateScopesInput($scopes) { return array(); }' . "\n";
        $script .= 'function g2ml_apiGenerateKey() { return array(\'success\' => false, \'error\' => \'not used\'); }' . "\n";
    }

    $script .= '$_SERVER[\'REQUEST_METHOD\'] = \'POST\';' . "\n";
    $script .= '$_POST = array(' . "\n";
    $script .= '    \'_csrf_token\' => g2ml_generateCSRFToken(\'api_keys_create\'),' . "\n";
    $script .= '    \'action_type\' => \'create_key\',' . "\n";
    $script .= '    \'key_name\'    => ' . var_export(g2mlFormFeedbackTest_typedKeyName(), true) . ',' . "\n";
    $script .= '    \'scopes\'      => array(\'urls:read\', \'analytics:read\', array(\'nested\'), \'not-a-permission\'),' . "\n";
    $script .= '    \'expires_at\'  => \'2030-01-01\',' . "\n";
    $script .= ');' . "\n";
    $script .= 'ob_start();' . "\n";
    $script .= 'require $root . \'/web/Go2My.Link/_admin/public_html/pages/api-keys/index.php\';' . "\n";
    $script .= 'echo ob_get_clean();' . "\n";

    return $script;
}

test('form feedback: the API key form passes the key name and permissions back (#286)', function (): void
{
    $source = g2mlFormFeedbackTest_read('web/Go2My.Link/_admin/public_html/pages/api-keys/index.php');

    assert_true(
        preg_match('/\'name\'\s*=>\s*\'key_name\'.*?\'value\'\s*=>\s*\$postedKeyName\b/s', $source) === 1,
        'The key name field must pass the posted name to formField()'
    );
    assert_contains('in_array($scopeValue, $postedScopes, true)', $source, 'A permission box must be re-ticked by matching against the posted list');
    assert_contains('is_string($_POST[\'key_name\'])', $source, 'The key name must be read with an is_string() guard');
    assert_false(str_contains($source, 'htmlspecialchars($postedKeyName'), 'The page must not escape the name before formField(), which escapes once itself');
});

test('form feedback: a failed API key create keeps the name, the ticked permissions and the expiry date (#286)', function (): void
{
    if (!g2mlFormFeedbackTest_childAvailable())
    {
        echo 'SKIPPED: exec disabled' . PHP_EOL;
        return;
    }

    $html = g2mlFormFeedbackTest_renderedHtml(g2mlFormFeedbackTest_apiKeysScript(false));

    assert_contains('Please select at least one permission.', $html, 'The create must have failed');
    assert_contains('value="' . g2mlFormFeedbackTest_once(g2mlFormFeedbackTest_typedKeyName()) . '"', $html, 'The key name must come back, escaped once');
    assert_true(preg_match('/value="urls:read"\s+checked="checked"/', $html) === 1, 'urls:read was ticked and must be ticked again');
    assert_true(preg_match('/value="analytics:read"\s+checked="checked"/', $html) === 1, 'analytics:read was ticked and must be ticked again');
    assert_true(preg_match('/value="urls:write"\s*>/', $html) === 1, 'urls:write was not ticked and must stay unticked');
    assert_false(str_contains($html, 'not-a-permission'), 'A posted value that is not a real permission must never be printed');
    assert_true(preg_match('/name="expires_at"\s+value="2030-01-01"/', $html) === 1, 'The expiry date must come back');
});

test('form feedback: after a successful API key create the form is empty again (#286)', function (): void
{
    if (!g2mlFormFeedbackTest_childAvailable())
    {
        echo 'SKIPPED: exec disabled' . PHP_EOL;
        return;
    }

    $html = g2mlFormFeedbackTest_renderedHtml(g2mlFormFeedbackTest_apiKeysScript(true));

    assert_contains('g2ml_test_key', $html, 'The one-time key panel must be shown');
    assert_false(str_contains($html, 'checked="checked"'), 'No permission box may be ticked after a successful create');
    assert_true(preg_match('/id="key-name"[^>]*name="key_name"[^>]*>/', $html, $nameField) === 1, 'The key name field must be on the page');
    assert_false(str_contains($nameField[0], 'value="My'), 'The name field must be empty after a successful create');
    assert_false(str_contains($html, 'value="2030-01-01"'), 'The expiry date must be empty after a successful create');
});

/**
 * A child-process script that renders the LinksPage edit page after one
 * "Add a Link" attempt. The stand-ins answer for the database.
 *
 * @param bool   $addSucceeds  What the stand-in "add item" answers.
 * @param string $source       The posted item_source, "manual" or "shorturl".
 */
function g2mlFormFeedbackTest_linksPageEditScript(bool $addSucceeds, string $source): string
{
    $script  = g2mlFormFeedbackTest_childPreamble();
    $script .= 'define(\'G2ML_LINKSPAGE_MANAGE_SOCIAL_NETWORKS\', array(\'twitter\' => \'X\'));' . "\n";
    $script .= 'function getCurrentUser() { return array(\'orgHandle\' => \'acme\', \'userUID\' => 7); }' . "\n";
    $script .= 'function g2ml_linkspageManageGetPageForOwner($pageUID, $userUID) { return array(' . "\n";
    $script .= '    \'pageUID\' => 5, \'slug\' => \'jane\', \'pageTitle\' => \'Jane\', \'pageDescription\' => null,' . "\n";
    $script .= '    \'avatarPath\' => null, \'templateUID\' => null, \'themeColour\' => null, \'backgroundColour\' => null,' . "\n";
    $script .= '    \'fontFamily\' => null, \'showSocialIcons\' => 0, \'socialLinks\' => null, \'isPublished\' => 0,' . "\n";
    $script .= '    \'customHTML\' => null, \'customCSS\' => null, \'customHtml\' => null, \'customCss\' => null); }' . "\n";
    $script .= 'function g2ml_linkspageManageListSystemTemplates() { return array(); }' . "\n";
    $script .= 'function g2ml_linkspageManageListShortURLsForUser($userUID) { return array(' . "\n";
    $script .= '    array(\'urlUID\' => 11, \'shortCode\' => \'abc\', \'title\' => \'First\'),' . "\n";
    $script .= '    array(\'urlUID\' => 12, \'shortCode\' => \'def\', \'title\' => null)); }' . "\n";
    $script .= 'function g2ml_linkspageManageListItemsForPage($pageUID, $userUID) { return array(); }' . "\n";
    $script .= 'function g2ml_linkspageCustomHtmlAllowedForOrg($org) { return false; }' . "\n";
    $script .= 'function g2ml_linkspageManageRenderTemplateCardThumbnail() { return \'\'; }' . "\n";

    if ($addSucceeds)
    {
        $script .= 'function g2ml_linkspageManageAddItem($userUID, $pageUID, $data) { return array(\'success\' => true); }' . "\n";
    }
    else
    {
        $script .= 'function g2ml_linkspageManageAddItem($userUID, $pageUID, $data) { return array(\'success\' => false, \'error\' => \'Please enter a title.\'); }' . "\n";
    }

    $script .= '$_SERVER[\'REQUEST_METHOD\'] = \'POST\';' . "\n";
    $script .= '$_GET[\'id\'] = \'5\';' . "\n";
    $script .= '$_POST = array(' . "\n";
    $script .= '    \'_csrf_token\'       => g2ml_generateCSRFToken(\'linkspage_add_item_5\'),' . "\n";
    $script .= '    \'action_type\'       => \'add_item\',' . "\n";
    $script .= '    \'item_source\'       => ' . var_export($source, true) . ',' . "\n";
    $script .= '    \'url_uid\'           => \'12\',' . "\n";
    $script .= '    \'manual_url\'        => ' . var_export('https://example.com/?a=1&b="2"', true) . ',' . "\n";
    $script .= '    \'item_title\'        => ' . var_export('Jane\'s <b>link</b> & co', true) . ',' . "\n";
    $script .= '    \'item_description\'  => ' . var_export('About </textarea> "q"', true) . ',' . "\n";
    $script .= '    \'item_icon\'         => ' . var_export('https://example.com/i.png?x=1&y=2', true) . ',' . "\n";
    $script .= '    \'requires_age_gate\' => \'1\',' . "\n";
    $script .= ');' . "\n";
    $script .= 'ob_start();' . "\n";
    $script .= 'require $root . \'/web/Go2My.Link/_admin/public_html/pages/linkspage/edit/index.php\';' . "\n";
    $script .= 'echo ob_get_clean();' . "\n";

    return $script;
}

test('form feedback: the LinksPage "Add a Link" form passes its posted values back (#286)', function (): void
{
    $source = g2mlFormFeedbackTest_read('web/Go2My.Link/_admin/public_html/pages/linkspage/edit/index.php');

    foreach (array('new-item-title' => 'item_title', 'new-item-description' => 'item_description', 'new-item-icon' => 'item_icon') as $fieldID => $fieldName)
    {
        assert_true(
            preg_match('/\'id\'\s*=>\s*\'' . $fieldID . '\'.*?\'value\'\s*=>\s*\$postedItemFields\[\'' . $fieldName . '\'\]/s', $source) === 1,
            'The ' . $fieldID . ' field must pass its posted value back to formField()'
        );
    }

    assert_contains('is_string($_POST[$postedItemKey])', $source, 'Posted values must be read with an is_string() guard');
    assert_contains('$postedItemFields[\'manual_url\']', $source, 'The manual address must be printed back');
});

test('form feedback: a failed "Add a Link" keeps the typed text and the chosen source (#286)', function (): void
{
    if (!g2mlFormFeedbackTest_childAvailable())
    {
        echo 'SKIPPED: exec disabled' . PHP_EOL;
        return;
    }

    $html = g2mlFormFeedbackTest_renderedHtml(g2mlFormFeedbackTest_linksPageEditScript(false, 'manual'));

    assert_contains('Please enter a title.', $html, 'The add must have failed');
    assert_contains('value="' . g2mlFormFeedbackTest_once('Jane\'s <b>link</b> & co') . '"', $html, 'The title must come back, escaped once');
    assert_contains('>' . g2mlFormFeedbackTest_once('About </textarea> "q"') . '</textarea>', $html, 'The description must come back, escaped once');
    assert_contains('value="' . g2mlFormFeedbackTest_once('https://example.com/i.png?x=1&y=2') . '"', $html, 'The icon address must come back, escaped once');
    assert_contains('value="' . g2mlFormFeedbackTest_once('https://example.com/?a=1&b="2"') . '"', $html, 'The manual address must come back, escaped once');
    assert_true(preg_match('/id="source-manual"[^>]*checked="checked"/s', $html) === 1, 'The manual source must still be chosen');
    assert_true(preg_match('/id="source-shorturl"[^>]*checked/s', $html) !== 1, 'The short-URL source must not be chosen');
    assert_true(preg_match('/id="new-item-requires-age-gate"[^>]*checked="checked"/', $html) === 1, 'The age-gate box must stay ticked');
    assert_false(str_contains($html, '</textarea> "q"'), 'Typed markup must never reach the page unescaped');
});

test('form feedback: a failed "Add a Link" with a short URL chosen keeps that choice (#286)', function (): void
{
    if (!g2mlFormFeedbackTest_childAvailable())
    {
        echo 'SKIPPED: exec disabled' . PHP_EOL;
        return;
    }

    $html = g2mlFormFeedbackTest_renderedHtml(g2mlFormFeedbackTest_linksPageEditScript(false, 'shorturl'));

    assert_true(preg_match('/id="source-shorturl"[^>]*checked="checked"/s', $html) === 1, 'The short-URL source must still be chosen');
    assert_true(preg_match('/id="source-manual"[^>]*checked/s', $html) !== 1, 'The manual source must not be chosen');
    assert_contains('<option value="12" selected="selected">', $html, 'The chosen short URL must still be selected');
    assert_false(str_contains($html, '<option value="11" selected'), 'Only the chosen short URL may be selected');
});

test('form feedback: after a successful "Add a Link" the form is empty again (#286)', function (): void
{
    if (!g2mlFormFeedbackTest_childAvailable())
    {
        echo 'SKIPPED: exec disabled' . PHP_EOL;
        return;
    }

    $html = g2mlFormFeedbackTest_renderedHtml(g2mlFormFeedbackTest_linksPageEditScript(true, 'manual'));

    assert_contains('Link saved.', $html, 'The add must have succeeded');
    assert_false(str_contains($html, 'Jane&#039;s'), 'The typed title must not be shown again after a successful add');
    assert_false(str_contains($html, 'example.com/i.png'), 'The typed icon address must not be shown again after a successful add');
    assert_false(str_contains($html, 'id="new-item-requires-age-gate" name="requires_age_gate" value="1" checked'), 'The age-gate box must be cleared after a successful add');
});

// ============================================================================
// (3) The Members page: a role changes only through a button and a confirm
// ============================================================================

/**
 * A child-process script that renders the Members page for two members.
 * $translated swaps in a stand-in __() whose answers contain quotes and angle
 * brackets, to prove the confirm text is made safe for the page.
 */
function g2mlFormFeedbackTest_membersScript(bool $translated): string
{
    $script = g2mlFormFeedbackTest_childPreamble();

    if ($translated)
    {
        $script .= 'function __($key, $replacements = array()) {' . "\n";
        $script .= '    if ($key === \'org.members_change_role_confirm\') { return "Change this member\'s \\"role\\" <now> & go?"; }' . "\n";
        $script .= '    return $key;' . "\n";
        $script .= '}' . "\n";
    }

    $script .= 'function getCurrentUser() { return array(\'orgHandle\' => \'acme\', \'userUID\' => 1, \'role\' => \'Admin\'); }' . "\n";
    $script .= 'function canManageOrg($org) { return true; }' . "\n";
    $script .= 'function getOrganisation($org) { return array(\'orgName\' => \'Acme\'); }' . "\n";
    $script .= 'function getOrgMembers($org) { return array(' . "\n";
    $script .= '    array(\'userUID\' => 2, \'displayName\' => \'Jane "J" O\\\'Neil <x>\', \'firstName\' => \'Jane\', \'lastName\' => \'O\', \'email\' => \'j@example.com\', \'role\' => \'User\', \'isSuspended\' => 0, \'lastLoginAt\' => null),' . "\n";
    $script .= '    array(\'userUID\' => 3, \'displayName\' => null, \'firstName\' => \'\', \'lastName\' => \'\', \'email\' => \'k@example.com\', \'role\' => \'Admin\', \'isSuspended\' => 0, \'lastLoginAt\' => null)); }' . "\n";
    $script .= 'function getPendingInvitations($org) { return array(); }' . "\n";
    $script .= 'function getUserAccountTypes($userUID, $org) { return array(); }' . "\n";
    $script .= '$_SERVER[\'REQUEST_METHOD\'] = \'GET\';' . "\n";
    $script .= 'ob_start();' . "\n";
    $script .= 'require $root . \'/web/Go2My.Link/_admin/public_html/pages/org/members/index.php\';' . "\n";
    $script .= 'echo ob_get_clean();' . "\n";

    return $script;
}

test('form feedback: the members role control is a submit-button form, not an onchange (#286)', function (): void
{
    $source = g2mlFormFeedbackTest_read('web/Go2My.Link/_admin/public_html/pages/org/members/index.php');

    assert_false(str_contains($source, 'onchange'), 'No control on the Members page may send a form from an onchange handler');
    assert_false(str_contains($source, 'this.form.submit()'), 'No control on the Members page may call form.submit() from inline script');

    $found = preg_match('/<form\b(?:(?!<\/form>).)*value="change_role"(?:(?!<\/form>).)*<\/form>/s', $source, $roleForm);

    assert_same(1, $found, 'The change_role form must still be on the page');

    assert_contains('<select', $roleForm[0], 'The role form must still have its dropdown');
    assert_true(preg_match('/<button type="submit"/', $roleForm[0]) === 1, 'The role form must have its own submit button');
    assert_contains('onsubmit="<?php echo $changeRoleConfirmAttr; ?>"', $roleForm[0], 'The role form must ask for confirmation, like the Remove form');
    assert_contains("g2ml_csrfField('org_member_change_role_' . (int) \$member['userUID'])", $roleForm[0], 'The per-row CSRF form name from #147 must be kept');
});

test('form feedback: each rendered role form has a dropdown, a labelled button and a confirm (#286)', function (): void
{
    if (!g2mlFormFeedbackTest_childAvailable())
    {
        echo 'SKIPPED: exec disabled' . PHP_EOL;
        return;
    }

    $html = g2mlFormFeedbackTest_renderedHtml(g2mlFormFeedbackTest_membersScript(false));

    assert_false(str_contains($html, 'onchange'), 'The page must send no form on change');

    $matchCount = preg_match_all('/<form\b(?:(?!<\/form>).)*value="change_role"(?:(?!<\/form>).)*<\/form>/s', $html, $matches);

    assert_same(2, $matchCount, 'There must be one role form per member');

    foreach ($matches[0] as $roleForm)
    {
        assert_contains('<select', $roleForm, 'Each role form needs its dropdown');
        assert_contains('name="_csrf_token"', $roleForm, 'Each role form needs its CSRF token');
        assert_contains('name="user_uid"', $roleForm, 'Each role form must say which member it is for');
        assert_true(preg_match('/<button type="submit"[^>]*>Change role</', $roleForm) === 1, 'Each role form needs a visible "Change role" button');
        assert_same(
            'Change the role of this member? Their access to the organisation changes as soon as you confirm.',
            g2mlFormFeedbackTest_confirmText($roleForm),
            'Each role form must ask before it sends'
        );
    }

    assert_contains(g2mlFormFeedbackTest_once('Jane "J" O\'Neil <x>'), $matches[0][0], 'The member\'s name must reach the screen-reader text, escaped once');
    assert_contains('k@example.com', $matches[0][1], 'A member with no name falls back to the email address in the screen-reader text');
});

test('form feedback: a translated confirm text with quotes and brackets is made safe for the page (#286)', function (): void
{
    if (!g2mlFormFeedbackTest_childAvailable())
    {
        echo 'SKIPPED: exec disabled' . PHP_EOL;
        return;
    }

    $html = g2mlFormFeedbackTest_renderedHtml(g2mlFormFeedbackTest_membersScript(true));

    $roleForm = g2mlFormFeedbackTest_formContaining($html, 'value="change_role"');

    assert_true($roleForm !== '', 'The role form must be on the page');
    assert_same(
        'Change this member\'s "role" <now> & go?',
        g2mlFormFeedbackTest_confirmText($roleForm),
        'The text must survive both the HTML attribute and the JavaScript string unchanged'
    );
    assert_false(str_contains($roleForm, '<now>'), 'The confirm text must not put raw angle brackets in the page');
});

// ============================================================================
// (4) The LinksPage list: unpublishing asks first
// ============================================================================

/**
 * A child-process script that renders the LinksPage list with one published
 * and one draft page.
 */
function g2mlFormFeedbackTest_linksPageListScript(bool $translated): string
{
    $script = g2mlFormFeedbackTest_childPreamble();

    if ($translated)
    {
        $script .= 'function __($key, $replacements = array()) {' . "\n";
        $script .= '    if ($key === \'linkspage.unpublish_confirm\') { return "Unpublish Jane\'s \\"page\\" <now> & go?"; }' . "\n";
        $script .= '    return $key;' . "\n";
        $script .= '}' . "\n";
    }

    $script .= 'function getCurrentUser() { return array(\'orgHandle\' => \'acme\', \'userUID\' => 1); }' . "\n";
    $script .= 'function g2ml_linkspageManageListPagesForUser($userUID) { return array(' . "\n";
    $script .= '    array(\'pageUID\' => 5, \'slug\' => \'jane\', \'pageTitle\' => \'Jane\', \'isPublished\' => 1, \'updatedAt\' => \'2026-01-01 00:00:00\'),' . "\n";
    $script .= '    array(\'pageUID\' => 6, \'slug\' => \'draft\', \'pageTitle\' => \'Draft\', \'isPublished\' => 0, \'updatedAt\' => \'2026-01-01 00:00:00\')); }' . "\n";
    $script .= 'function g2ml_linkspageManageCountActivePagesForUser($userUID) { return 2; }' . "\n";
    $script .= 'function g2ml_checkLimit($org, $key, $count) { return array(\'allowed\' => true, \'limit\' => null); }' . "\n";
    $script .= '$_SERVER[\'REQUEST_METHOD\'] = \'GET\';' . "\n";
    $script .= 'ob_start();' . "\n";
    $script .= 'require $root . \'/web/Go2My.Link/_admin/public_html/pages/linkspage/index.php\';' . "\n";
    $script .= 'echo ob_get_clean();' . "\n";

    return $script;
}

test('form feedback: the LinksPage unpublish form has a confirm, in the same pattern as delete (#286)', function (): void
{
    $source = g2mlFormFeedbackTest_read('web/Go2My.Link/_admin/public_html/pages/linkspage/index.php');

    $found = preg_match('/<form\b(?:(?!<\/form>).)*value="unpublish_page"(?:(?!<\/form>).)*<\/form>/s', $source, $unpublishForm);

    assert_same(1, $found, 'The unpublish form must still be on the page');
    assert_contains('onsubmit="<?php echo $unpublishConfirmAttr; ?>"', $unpublishForm[0], 'The unpublish form must ask for confirmation');
    assert_contains('return confirm(', $source, 'The page keeps its confirm pattern');
});

test('form feedback: only unpublish and delete ask first on the rendered LinksPage list (#286)', function (): void
{
    if (!g2mlFormFeedbackTest_childAvailable())
    {
        echo 'SKIPPED: exec disabled' . PHP_EOL;
        return;
    }

    $html = g2mlFormFeedbackTest_renderedHtml(g2mlFormFeedbackTest_linksPageListScript(false));

    $unpublishForm = g2mlFormFeedbackTest_formContaining($html, 'value="unpublish_page"');
    $publishForm   = g2mlFormFeedbackTest_formContaining($html, 'value="publish_page"');

    assert_true($unpublishForm !== '', 'The published page must have an unpublish form');
    assert_true($publishForm !== '', 'The draft page must have a publish form');

    assert_same(
        'Unpublish this LinksPage? Visitors will no longer be able to see it at its public address. You can publish it again later.',
        g2mlFormFeedbackTest_confirmText($unpublishForm),
        'Unpublishing must ask first'
    );
    assert_false(str_contains($publishForm, 'onsubmit'), 'Publishing puts a page online, which is safe, so it does not ask');
});

test('form feedback: a translated unpublish confirm with quotes and brackets is made safe (#286)', function (): void
{
    if (!g2mlFormFeedbackTest_childAvailable())
    {
        echo 'SKIPPED: exec disabled' . PHP_EOL;
        return;
    }

    $html = g2mlFormFeedbackTest_renderedHtml(g2mlFormFeedbackTest_linksPageListScript(true));

    $unpublishForm = g2mlFormFeedbackTest_formContaining($html, 'value="unpublish_page"');

    assert_same(
        'Unpublish Jane\'s "page" <now> & go?',
        g2mlFormFeedbackTest_confirmText($unpublishForm),
        'The text must survive both the HTML attribute and the JavaScript string unchanged'
    );
    assert_false(str_contains($unpublishForm, '<now>'), 'The confirm text must not put raw angle brackets in the page');
});

// ============================================================================
// (5) The seed file holds every new string, with the same English as the page
// ============================================================================

/**
 * The en-GB rows of seed 071 as key => value, with SQL's doubled quote
 * turned back into one. Also asserts each row is marked verified.
 *
 * @return array<string, string>
 */
function g2mlFormFeedbackTest_seedRows(): array
{
    $seed = g2mlFormFeedbackTest_read('web/_sql/seeds/071_form_feedback_translations.sql');

    $matchCount = preg_match_all(
        '/\(\'en-GB\',\s*\'([^\']+)\',\s*\'((?:[^\']|\'\')*)\',\s*\'((?:[^\']|\'\')*)\',\s*(\d)\)/u',
        $seed,
        $matches,
        PREG_SET_ORDER
    );

    assert_true($matchCount !== false && $matchCount > 0, 'Seed 071 must have en-GB rows');

    $rows = array();

    foreach ($matches as $match)
    {
        assert_same('1', $match[4], 'Seed row ' . $match[1] . ' must be marked verified');

        $rows[$match[1]] = str_replace("''", "'", $match[2]);
    }

    return $rows;
}

/**
 * The plain-English default written beside a __('key') call in a PHP file:
 * the first single-quoted string in the else branch after the call.
 * Returns null when it cannot be found.
 */
function g2mlFormFeedbackTest_fallbackFor(string $source, string $key): ?string
{
    $callPosition = strpos($source, "__('" . $key . "')");

    if ($callPosition === false)
    {
        return null;
    }

    $rest = substr($source, $callPosition);

    if (preg_match('/else\s*\{\s*(?:echo|\$\w+\s*=)\s*\'((?:[^\'\\\\]|\\\\.)*)\'\s*;/su', $rest, $match) !== 1)
    {
        return null;
    }

    return str_replace(array("\\'", '\\\\'), array("'", '\\'), $match[1]);
}

test('form feedback: seed 071 holds exactly the new keys, and the pages use each one with the same English (#286)', function (): void
{
    $keysAndFiles = array(
        'common.working'                  => 'web/_includes/footer.php',
        'org.members_new_role_label'      => 'web/Go2My.Link/_admin/public_html/pages/org/members/index.php',
        'org.members_change_role_button'  => 'web/Go2My.Link/_admin/public_html/pages/org/members/index.php',
        'org.members_change_role_confirm' => 'web/Go2My.Link/_admin/public_html/pages/org/members/index.php',
        'linkspage.unpublish_confirm'     => 'web/Go2My.Link/_admin/public_html/pages/linkspage/index.php',
    );

    $seedRows = g2mlFormFeedbackTest_seedRows();

    $seedKeys     = array_keys($seedRows);
    $expectedKeys = array_keys($keysAndFiles);
    sort($seedKeys);
    sort($expectedKeys);

    assert_same($expectedKeys, $seedKeys, 'Seed 071 must hold exactly the keys these pages use — add a new key here and to the seed together');

    foreach ($keysAndFiles as $key => $relativePath)
    {
        $source   = g2mlFormFeedbackTest_read($relativePath);
        $fallback = g2mlFormFeedbackTest_fallbackFor($source, $key);

        assert_true($fallback !== null, $relativePath . ' must read ' . $key . ' through __() with a plain-English default beside it');
        assert_same($seedRows[$key], $fallback, 'The English default in ' . $relativePath . ' for ' . $key . ' must match the seed');
    }
});

test('form feedback: seed 071 follows the seed-file layout and the number is not shared (#286)', function (): void
{
    $seed = g2mlFormFeedbackTest_read('web/_sql/seeds/071_form_feedback_translations.sql');

    assert_contains('USE `mwtools_Go2MyLink`;', $seed);
    assert_contains('INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)', $seed);

    $sameNumber = glob(g2mlFormFeedbackTest_path('web/_sql/seeds') . DIRECTORY_SEPARATOR . '071_*.sql');

    assert_true($sameNumber !== false && count($sameNumber) === 1, 'Only one seed file may use the number 071');
});

test('form feedback: no other seed file already holds one of seed 071\'s keys (#286)', function (): void
{
    $seedFolder = dirname(__DIR__, 2) . '/web/_sql/seeds';
    $problems   = array();

    foreach (array_keys(g2mlFormFeedbackTest_seedRows()) as $key)
    {
        foreach (glob($seedFolder . '/*.sql') as $seedFile)
        {
            if (basename($seedFile) === '071_form_feedback_translations.sql')
            {
                continue;
            }

            if (strpos((string) file_get_contents($seedFile), "'" . $key . "'") !== false)
            {
                $problems[] = $key . ' is also in ' . basename($seedFile);
            }
        }
    }

    assert_same(0, count($problems), implode(' | ', $problems));
});
