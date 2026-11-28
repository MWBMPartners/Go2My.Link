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
 * 🧪 One of several processes that act at the same moment (#327)
 * ============================================================================
 *
 * Used only by tests/integration/link_limit_test.php. It waits until the given
 * moment, then creates one link, or switches one on, with the real functions,
 * and prints "ok", "limit", "busy" or "error".
 *
 * Usage:
 *   php link_limit_worker.php create    <startAt> <orgHandle> <userUID>
 *   php link_limit_worker.php switch_on <startAt> <urlUID>
 *
 * The database comes from the G2ML_TEST_DB_* environment variables.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      #327
 * ============================================================================
 */

declare(strict_types=1);

$g2mlDefaults = [
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_USER' => 'root',
    'DB_PASS' => '',
    'DB_NAME' => 'mwtools_Go2MyLink',
];

foreach ($g2mlDefaults as $constant => $default)
{
    $value = getenv('G2ML_TEST_' . $constant);

    if ($value === false || $value === '')
    {
        $value = $default;
    }

    if ($constant === 'DB_PORT')
    {
        $value = (int) $value;
    }

    define($constant, $value);
}

define('DB_CHARSET', 'utf8mb4');

$_SESSION = [];
$web      = dirname(__DIR__, 2) . '/web/';

foreach (['db_connect', 'db_query', 'security', 'settings', 'activity_logger', 'entitlements'] as $file)
{
    require_once $web . '_functions/' . $file . '.php';
}

require_once $web . 'Go2My.Link/_functions/shorturl_create.php';

// Connect before waiting, so every process starts its work together.
getDB();

$startAt = (float) $argv[2];

while (microtime(true) < $startAt)
{
    usleep(1000);
}

$outcome = 'error';

if ($argv[1] === 'create')
{
    $result = createShortURL('https://example.com/race', ['orgHandle' => $argv[3], 'userUID' => (int) $argv[4]]);

    if ($result['success'] === true)
    {
        $outcome = 'ok';
    }
    elseif (($result['limitReached'] ?? false) === true)
    {
        $outcome = 'limit';
    }
    elseif (($result['limitBusy'] ?? false) === true)
    {
        $outcome = 'busy';
    }
}
else
{
    $gate = g2ml_linkLimitOpenForSwitchOn((int) $argv[3]);

    if ($gate['switchOn'] === true)
    {
        if (dbUpdate('UPDATE `tblShortURLs` SET `isActive` = 1 WHERE `urlUID` = ?', 'i', [(int) $argv[3]]) !== false)
        {
            $outcome = 'ok';
        }
    }
    elseif ($gate['reason'] !== '')
    {
        $outcome = $gate['reason'];
    }

    g2ml_linkLimitClose($gate);
}

echo $outcome;
