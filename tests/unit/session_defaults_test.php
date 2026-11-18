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
 * 🧪 Unit tests — both session starts set the three options PHP 8.6 changed
 * ============================================================================
 *
 * PHP 8.6 changes three session defaults: strict mode on, the HttpOnly flag
 * on, and SameSite=Lax. The live site (page_init.php) and the installer both
 * set all three themselves, so they behave the same on PHP 8.4, 8.5 and 8.6.
 * These source tests stop a later edit from quietly handing any of the three
 * back to PHP's own default.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      #308
 * ============================================================================
 */

declare(strict_types=1);

/**
 * Give PHP source text back without its comments, so a setting that has only
 * been commented out does not count.
 *
 * @param  string $source  The PHP source text
 * @return string
 */
function g2ml_sessionDefaultsWithoutComments(string $source): string
{
    $result = '';

    foreach (token_get_all($source) as $token)
    {
        if (is_array($token))
        {
            if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT)
            {
                continue;
            }

            $result .= $token[1];
        }
        else
        {
            $result .= $token;
        }
    }

    return $result;
}

/**
 * Say which of the three options a session-start source leaves unset.
 *
 * @param  string $source  The PHP source text
 * @return array<int, string>  The names of the missing settings
 */
function g2ml_sessionDefaultsMissing(string $source): array
{
    $missing = array();

    // Comments are removed first, so text inside a comment never counts.
    $source = g2ml_sessionDefaultsWithoutComments($source);

    $sessionStart = strpos($source, 'session_start();');

    if ($sessionStart === false)
    {
        return array('session_start()');
    }

    $beforeStart = substr($source, 0, $sessionStart);

    if (preg_match("/ini_set\(\s*'session\.use_strict_mode'\s*,\s*'1'\s*\)/", $beforeStart) !== 1)
    {
        $missing[] = 'use_strict_mode';
    }

    if (preg_match("/'httponly'\s*=>\s*true/", $beforeStart) !== 1)
    {
        $missing[] = 'httponly';
    }

    if (preg_match("/'samesite'\s*=>\s*'(Lax|Strict)'/", $beforeStart) !== 1)
    {
        $missing[] = 'samesite';
    }

    return $missing;
}

$g2mlSessionDefaultsFiles = array(
    'page_init.php' => dirname(__DIR__, 2) . '/web/_includes/page_init.php',
    'installer'     => dirname(__DIR__, 2) . '/web/Go2My.Link/public_html/install/index.php',
);

foreach ($g2mlSessionDefaultsFiles as $g2mlSessionDefaultsName => $g2mlSessionDefaultsPath)
{
    test('session defaults: ' . $g2mlSessionDefaultsName . ' sets strict mode, HttpOnly and SameSite before session_start()', function () use ($g2mlSessionDefaultsPath): void
    {
        $source = file_get_contents($g2mlSessionDefaultsPath);

        assert_true($source !== false, 'The file could be read');

        $missing = g2ml_sessionDefaultsMissing((string) $source);

        assert_same(array(), $missing, 'Settings missing before session_start(): ' . implode(', ', $missing));
    });
}

test('session defaults: the check notices when any one setting is removed', function (): void
{
    $good = "<?php\nini_set('session.use_strict_mode', '1');\n"
        . "session_set_cookie_params(['httponly'  => true, 'samesite' => 'Lax']);\nsession_start();\n";

    assert_same(array(), g2ml_sessionDefaultsMissing($good), 'A complete setup passes');

    assert_same(
        array('use_strict_mode'),
        g2ml_sessionDefaultsMissing(str_replace("ini_set('session.use_strict_mode', '1');", '', $good)),
        'Removing strict mode is noticed'
    );
    assert_same(
        array('httponly'),
        g2ml_sessionDefaultsMissing(str_replace("'httponly'  => true, ", '', $good)),
        'Removing HttpOnly is noticed'
    );
    assert_same(
        array('samesite'),
        g2ml_sessionDefaultsMissing(str_replace(", 'samesite' => 'Lax'", '', $good)),
        'Removing SameSite is noticed'
    );
    assert_same(
        array('use_strict_mode'),
        g2ml_sessionDefaultsMissing(str_replace("ini_set('session.use_strict_mode', '1');", "// ini_set('session.use_strict_mode', '1');", $good)),
        'Commenting out strict mode is noticed'
    );
    assert_same(
        array('httponly'),
        g2ml_sessionDefaultsMissing(str_replace("['httponly'  => true, 'samesite' => 'Lax']", "[/* 'httponly'  => true, */ 'samesite' => 'Lax']", $good)),
        'Commenting out HttpOnly is noticed'
    );
    assert_same(
        array('samesite'),
        g2ml_sessionDefaultsMissing(str_replace("'samesite' => 'Lax']", "/* 'samesite' => 'Lax' */]", $good)),
        'Commenting out SameSite is noticed'
    );
    assert_same(
        array('httponly'),
        g2ml_sessionDefaultsMissing(str_replace('true', 'false', $good)),
        'HttpOnly set to false is noticed'
    );
});
